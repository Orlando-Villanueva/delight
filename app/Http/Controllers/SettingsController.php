<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\AnnualRecapService;
use App\Services\ReadingPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function __construct(
        private ReadingPlanService $readingPlanService
    ) {}

    /**
     * Show account settings.
     */
    public function edit(): View
    {
        return view('settings.edit');
    }

    /**
     * Update account settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $wasIncludingDeuterocanonical = $user->includesDeuterocanonicalBooks();
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

        if (array_key_exists('push_notification_timezone', $validated)) {
            $updates['push_notification_timezone'] = $validated['push_notification_timezone'] ?: $user->pushNotificationTimezone();
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        Cache::forget("user_dashboard_stats_{$user->id}");

        $freshUser = $user->fresh();
        $pausedCatholicCanonicalPlan = false;

        if ($wasIncludingDeuterocanonical !== $freshUser->includesDeuterocanonicalBooks()) {
            Cache::forget(AnnualRecapService::cacheKeyFor($user, now()->year));
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
                'push_notification_timezone' => $freshUser->pushNotificationTimezone(),
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
