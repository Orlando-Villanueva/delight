<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\AnnualRecapService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingPlanService;
use App\Services\UserStatisticsService;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function __construct(
        private ReadingPlanService $readingPlanService,
        private ReadingCalendarService $readingCalendar,
        private UserStatisticsService $userStatistics,
        private AnnualRecapService $annualRecapService
    ) {}

    /**
     * Show account settings.
     */
    public function edit(): View
    {
        $timezone = $this->readingCalendar->timezoneFor(auth()->user());
        $timezones = array_unique([...DateTimeZone::listIdentifiers(), $timezone]);
        sort($timezones);

        $referenceTime = now()->toImmutable();

        return view('settings.edit', [
            'readingTimezone' => $timezone,
            'readingTimezoneOptions' => array_combine($timezones, array_map(
                fn (string $zone): string => str_replace('_', ' ', basename($zone))
                    .' — '.$zone.' (UTC'.$referenceTime->setTimezone($zone)->format('P').')',
                $timezones,
            )),
        ]);
    }

    /**
     * Update account settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $wasIncludingDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $wasReadingTimezone = $user->reading_timezone;
        $oldAccountNow = $this->readingCalendar->nowFor($user);
        $validated = $request->validated();
        $updates = [];

        if (array_key_exists('include_deuterocanonical', $validated)) {
            $updates['deuterocanonical_books_enabled_at'] = $request->boolean('include_deuterocanonical') ? now() : null;
        }

        if (array_key_exists('daily_reading_reminder_enabled', $validated)) {
            $updates['daily_reading_reminder_enabled_at'] = $request->boolean('daily_reading_reminder_enabled') ? now() : null;
        }

        if (array_key_exists('streak_warning_enabled', $validated)) {
            $updates['streak_warning_enabled_at'] = $request->boolean('streak_warning_enabled') ? now() : null;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        if (array_key_exists('reading_timezone', $validated) && $user->reading_timezone !== $validated['reading_timezone']) {
            $this->userStatistics->invalidateUserCache($user);
            $this->readingCalendar->changeTimezone($user, $validated['reading_timezone']);
            $this->userStatistics->invalidateUserCache($user);
        }

        Cache::forget($this->readingCalendar->cacheKey($user, "user_dashboard_stats_{$user->id}"));

        $freshUser = $user->fresh();
        $pausedCatholicCanonicalPlan = false;

        $readingTimezoneChanged = $wasReadingTimezone !== $freshUser->reading_timezone;
        $canonChanged = $wasIncludingDeuterocanonical !== $freshUser->includesDeuterocanonicalBooks();

        if ($readingTimezoneChanged || $canonChanged) {
            $newAccountYear = $this->readingCalendar->nowFor($freshUser)->year;
            $recapYears = [$newAccountYear];

            if ($readingTimezoneChanged) {
                $oldAccountYear = $oldAccountNow->year;
                $recapYears = [
                    $oldAccountYear,
                    $oldAccountYear - 1,
                    $newAccountYear,
                    $newAccountYear - 1,
                ];
            }

            if ($canonChanged) {
                $persistedRecapYears = $freshUser->annualRecaps()
                    ->pluck('year')
                    ->map(fn (mixed $year): int => (int) $year)
                    ->all();

                $recapYears = array_values(array_unique([
                    ...$persistedRecapYears,
                    ...$recapYears,
                ]));
            }

            $this->annualRecapService->invalidateForYears(
                $freshUser,
                ...$recapYears
            );
        }

        if ($wasIncludingDeuterocanonical && ! $freshUser->includesDeuterocanonicalBooks()) {
            $pausedCatholicCanonicalPlan = $this->readingPlanService->pauseActiveCatholicCanonicalPlan($user);
            $freshUser = $user->fresh();
        }

        if ($request->expectsJson()) {
            $response = [
                'include_deuterocanonical' => $freshUser->includesDeuterocanonicalBooks(),
                'daily_reading_reminder_enabled' => $freshUser->hasDailyReadingReminderEnabled(),
                'streak_warning_enabled' => $freshUser->hasStreakWarningEnabled(),
                'reading_timezone' => $this->readingCalendar->timezoneFor($freshUser),
            ];

            if ($pausedCatholicCanonicalPlan) {
                $response['plans_navigation_html'] = $this->readingPlanService->getPlansNavigationFragment($freshUser);
            }

            return response()->json($response, Response::HTTP_OK);
        }

        if ($pausedCatholicCanonicalPlan) {
            return redirect()->route('settings.edit')
                ->with('status', 'Settings saved. Your Catholic Canonical reading plan has been paused.');
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
