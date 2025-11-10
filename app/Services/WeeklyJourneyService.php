<?php

namespace App\Services;

use App\Enums\WeeklyJourneyDayState;
use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class WeeklyJourneyService
{
    private const int DAYS_IN_WEEK = 7;

    private const int FIRST_DAY_OF_WEEK = Carbon::SUNDAY;

    private const int LAST_DAY_OF_WEEK = Carbon::SATURDAY;

    private const int WEEKLY_TARGET = 7;

    public function getWeeklyJourneyData(User $user, ?int $currentProgressOverride = null): array
    {
        if (! $user || ! $user->id) {
            throw new InvalidArgumentException('Valid user with ID required');
        }

        try {
            $today = now();
            $weekStart = $today->copy()->startOfWeek(self::FIRST_DAY_OF_WEEK);
            $weekEnd = $today->copy()->endOfWeek(self::LAST_DAY_OF_WEEK);

            $distinctReadingDates = $this->getDistinctReadingDatesForWeek($user, $weekStart, $weekEnd);
            $currentProgress = $currentProgressOverride ?? count($distinctReadingDates);
            $days = $this->buildWeeklyDayMap($weekStart, $distinctReadingDates, $today);

            return $this->formatJourneyPayload($days, $weekStart, $weekEnd, $currentProgress);
        } catch (Throwable $exception) {
            Log::error('Error building weekly journey data', [
                'user_id' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return $this->getDefaultJourneyData();
        }
    }

    private function getDistinctReadingDatesForWeek(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        return ReadingLog::forUser($user->id)
            ->dateRange($weekStart->toDateString(), $weekEnd->toDateString())
            ->select('date_read')
            ->distinct()
            ->pluck('date_read')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();
    }

    private function buildWeeklyDayMap(Carbon $weekStart, array $readDates, Carbon $today): array
    {
        $readLookup = array_fill_keys($readDates, true);
        $days = [];

        for ($offset = 0; $offset < self::DAYS_IN_WEEK; $offset++) {
            $date = $weekStart->copy()->addDays($offset);
            $dateString = $date->toDateString();
            $isRead = isset($readLookup[$dateString]);
            $state = WeeklyJourneyDayState::resolve($date, $today, $isRead);

            $days[] = [
                'date' => $dateString,
                'dow' => $date->dayOfWeek,
                'isToday' => $date->isSameDay($today),
                'read' => $isRead,
                'state' => $state->value,
            ];
        }

        return $days;
    }

    private function formatWeekRange(Carbon $weekStart, Carbon $weekEnd): string
    {
        if ($weekStart->isSameMonth($weekEnd)) {
            return sprintf('%s–%s', $weekStart->format('M j'), $weekEnd->format('j'));
        }

        return sprintf('%s–%s', $weekStart->format('M j'), $weekEnd->format('M j'));
    }

    private function isCtaEnabled(?array $todaySlot): bool
    {
        return $todaySlot !== null;
    }

    private function getDefaultJourneyData(): array
    {
        $today = now();
        $weekStart = $today->copy()->startOfWeek(self::FIRST_DAY_OF_WEEK);
        $weekEnd = $today->copy()->endOfWeek(self::LAST_DAY_OF_WEEK);
        $days = $this->buildWeeklyDayMap($weekStart, [], $today);
        return $this->formatJourneyPayload($days, $weekStart, $weekEnd, 0);
    }

    private function formatJourneyPayload(array $days, Carbon $weekStart, Carbon $weekEnd, int $currentProgress): array
    {
        $normalizedDays = $this->appendAccessibilityMetadata($days);
        $todaySlot = collect($normalizedDays)->firstWhere('isToday', true);
        $ctaEnabled = $this->isCtaEnabled($todaySlot);

        return [
            'currentProgress' => $currentProgress,
            'days' => $normalizedDays,
            'today' => $todaySlot,
            'weekRangeText' => $this->formatWeekRange($weekStart, $weekEnd),
            'weeklyTarget' => self::WEEKLY_TARGET,
            'ctaEnabled' => $ctaEnabled,
            'ctaVisible' => $this->shouldShowCta($ctaEnabled, $todaySlot, $currentProgress),
            'status' => $this->buildPerfectWeekStatus($currentProgress),
            'journeyAltText' => $this->buildJourneyAltText($currentProgress),
        ];
    }

    private function appendAccessibilityMetadata(array $days): array
    {
        return collect($days)
            ->map(function (array $day, int $index) {
                $dateString = $day['date'] ?? null;
                $date = $dateString ? Carbon::parse($dateString) : null;
                $formattedDate = $date ? $date->format('D M j') : sprintf('Day %d', $index + 1);
                $state = WeeklyJourneyDayState::tryFrom($day['state'] ?? '') ?? (($day['read'] ?? false)
                    ? WeeklyJourneyDayState::COMPLETE
                    : WeeklyJourneyDayState::UPCOMING);
                $stateDescription = $state->description();
                $label = sprintf('%s — %s', $formattedDate, $stateDescription);

                return array_merge($day, [
                    'title' => $label,
                    'ariaLabel' => $label,
                ]);
            })
            ->all();
    }

    private function shouldShowCta(bool $ctaEnabled, ?array $todaySlot, int $currentProgress): bool
    {
        if (! $ctaEnabled || ! $todaySlot) {
            return false;
        }

        $todayRead = (bool) ($todaySlot['read'] ?? false);

        return ! $todayRead && $currentProgress < self::WEEKLY_TARGET;
    }

    private function buildPerfectWeekStatus(int $currentProgress): ?array
    {
        if ($currentProgress < self::WEEKLY_TARGET) {
            return null;
        }

        return [
            'state' => 'perfect',
            'label' => 'Perfect week',
            'microcopy' => 'You did it—enjoy some rest!',
            'chipClasses' => 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/40 dark:text-amber-100 dark:border-amber-800',
            'microcopyClasses' => 'text-amber-600 dark:text-amber-300 font-semibold',
            'showCrown' => true,
        ];
    }

    private function buildJourneyAltText(int $currentProgress): string
    {
        $clampedProgress = max(0, min($currentProgress, self::WEEKLY_TARGET));

        return sprintf('%d of %d days logged this week.', $clampedProgress, self::WEEKLY_TARGET);
    }
}
