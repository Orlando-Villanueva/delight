<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\AnnualRecapService;
use App\Services\BibleReferenceService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingFormService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private ReadingFormService $readingFormService,
        private UserStatisticsService $statisticsService,
        private StreakStateService $streakStateService,
        private ReadingCalendarService $readingCalendar,
        private AnnualRecapService $recapService,
        private AchievementService $achievementService,
        private BibleReferenceService $bibleReferenceService
    ) {}

    /**
     * Display the authenticated reader dashboard.
     */
    public function index(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $hasReadToday = $this->readingFormService->hasReadToday($user);
        $stats = $this->statisticsService->getDashboardStatistics($user);
        $accountNow = $this->readingCalendar->nowFor($user);

        $streakState = $this->streakStateService->determineStreakState(
            $stats['streaks']['current_streak'],
            $hasReadToday,
            $accountNow
        );
        $recordStatus = data_get($stats, 'streaks.record_status', 'none');
        $recordJustBroken = data_get($stats, 'streaks.record_just_broken', false);
        $messagePayload = $this->streakStateService->getMessagePayload(
            $stats['streaks']['current_streak'],
            $streakState,
            $stats['streaks']['longest_streak'],
            $hasReadToday,
            $recordStatus,
            $recordJustBroken,
            $accountNow
        );
        $recapCard = $this->recapService->getDashboardCardState($accountNow);
        $calendarData = $this->statisticsService->getMonthlyCalendarData($user);
        $dashboardMilestone = $this->achievementService->getDashboardMilestone($user, $stats['book_progress']);

        $props = [
            'hasReadToday' => $hasReadToday,
            'streak' => [
                'currentStreak' => (int) $stats['streaks']['current_streak'],
                'longestStreak' => (int) $stats['streaks']['longest_streak'],
                'activity14DayCounts' => $this->normalizeActivityCounts($stats['streaks']['recent_reading_activity_series'] ?? []),
                'currentStreakCounts' => $this->normalizeActivityCounts($stats['streaks']['current_streak_series'] ?? []),
                'presentation' => [
                    'state' => $streakState,
                    'classes' => $this->streakStateService->getStateClasses($streakState),
                    'message' => $messagePayload['message'],
                    'tone' => $messagePayload['tone'] ?? 'default',
                    'label' => $messagePayload['label'] ?? null,
                    'showCta' => $messagePayload['show_cta'] ?? false,
                    'recordStatus' => $recordStatus,
                    'recordJustBroken' => $recordJustBroken,
                ],
            ],
            'summary' => [
                'daysRead' => (int) $stats['reading_summary']['total_reading_days'],
                'totalChapters' => (int) $stats['reading_summary']['total_readings'],
                'bookCompletions' => (int) $stats['book_progress']['book_completions'],
                'averageChaptersPerDay' => (float) $stats['reading_summary']['average_chapters_per_day'],
                'elapsedDays' => (int) $stats['reading_summary']['days_since_first_reading'],
            ],
            'calendar' => $this->normalizeCalendar($calendarData, $accountNow),
            'bookProgress' => $this->localizedBookProgress($stats['book_progress'], $user),
            'recentActivity' => collect($stats['recent_activity'])
                ->take(10)
                ->map(fn (array $reading): array => [
                    'id' => (int) $reading['id'],
                    'passageText' => $this->localizedRecentPassage($reading, $user),
                    'dateRead' => $this->dateString($reading['date_read']),
                    'timeAgo' => $this->localizedTimeAgo((string) $reading['time_ago']),
                ])
                ->values()
                ->all(),
            'milestone' => $this->normalizeMilestone($dashboardMilestone),
            'badgeBaseUrl' => asset('images/achievements'),
            'recap' => [
                'show' => (bool) $recapCard['show'],
                'year' => (int) $recapCard['year'],
                'endLabel' => (string) $recapCard['end_label'],
                'isFinal' => (bool) $recapCard['is_final'],
                'url' => route('recap.show', ['year' => $recapCard['year']]),
            ],
            'onboarding' => [
                'show' => $user->needsOnboarding(),
                'canRemind' => $user->marketing_emails_opted_out_at === null,
                'dismissUrl' => route('onboarding.dismiss'),
                'remindUrl' => route('onboarding.remind'),
                'plansUrl' => route('plans.index'),
            ],
        ];

        return $request->expectsJson()
            ? response()->json($props)
            : Inertia::render('Dashboard', $props);
    }

    /**
     * @param  array<int, array{date: string, count: int}>  $series
     * @return array<int, array{date: string, count: int}>
     */
    private function normalizeActivityCounts(array $series): array
    {
        return collect($series)
            ->map(fn (array $entry): array => [
                'date' => (string) $entry['date'],
                'count' => (int) $entry['count'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $calendarData
     * @return array<string, mixed>
     */
    private function normalizeCalendar(array $calendarData, CarbonInterface $accountNow): array
    {
        $today = $accountNow->toDateString();

        return [
            'monthName' => Carbon::createFromDate((int) $calendarData['year'], (int) $calendarData['month'], 1)
                ->locale(app()->getLocale())->isoFormat(app()->getLocale() === 'fr' ? 'MMMM YYYY' : 'MMMM YYYY'),
            'days' => collect($calendarData['calendar'])
                ->map(function (?array $day) use ($today): ?array {
                    if ($day === null) {
                        return null;
                    }

                    $date = $this->dateString($day['dateString'] ?? $day['date']);

                    return [
                        'date' => $date,
                        'day' => (int) $day['day'],
                        'readingCount' => (int) $day['readingCount'],
                        'hasRead' => (bool) $day['hasReading'],
                        'isToday' => $date === $today,
                        'isFuture' => $date > $today,
                    ];
                })
                ->values()
                ->all(),
            'thisMonthReadings' => (int) $calendarData['thisMonthReadings'],
            'thisMonthChapters' => (int) $calendarData['thisMonthChapters'],
            'successRate' => (int) $calendarData['successRate'],
            'daysPassedInMonth' => (int) $calendarData['daysPassedInMonth'],
        ];
    }

    /**
     * @param  array{latest: ?UserAchievement, milestone: ?array<string, mixed>}  $milestone
     * @return array{latest: ?array<string, mixed>, milestone: ?array<string, mixed>}
     */
    private function normalizeMilestone(array $milestone): array
    {
        $latest = $milestone['latest'];

        return [
            'latest' => $latest instanceof UserAchievement ? [
                'displayName' => $this->achievementService->localizedCopy($latest)['display_name'],
                'description' => $this->achievementService->localizedCopy($latest)['description'],
                'icon' => (string) $latest->icon,
                'earnedAt' => $latest->earned_at?->toIso8601String(),
            ] : null,
            'milestone' => $milestone['milestone'] === null ? null : [
                'key' => (string) $milestone['milestone']['achievement_key'],
                'contextKey' => (string) $milestone['milestone']['context_key'],
                'displayName' => $this->achievementService->localizedPayload($milestone['milestone'])['display_name'],
                'description' => $this->achievementService->localizedPayload($milestone['milestone'])['description'],
                'icon' => (string) $milestone['milestone']['icon'],
                'style' => (string) $milestone['milestone']['style'],
                'current' => (int) $milestone['milestone']['current'],
                'target' => (int) $milestone['milestone']['target'],
                'progressPercent' => (int) $milestone['milestone']['progress_percent'],
            ],
        ];
    }

    private function dateString(mixed $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
    }

    private function localizedBookProgress(array $progress, User $user): array
    {
        foreach (['old_testament', 'new_testament', 'deuterocanonical'] as $testamentKey) {
            if (! is_array($progress[$testamentKey] ?? null)) {
                continue;
            }

            if (app()->getLocale() === 'fr') {
                $progress[$testamentKey]['testament'] = match ($testamentKey) {
                    'old_testament' => __('reader.testaments.old'),
                    'new_testament' => __('reader.testaments.new'),
                    default => __('reader.testaments.deuterocanonical'),
                };
            }
            $progress[$testamentKey]['processed_books'] = collect($progress[$testamentKey]['processed_books'] ?? [])
                ->map(function (array $book) use ($user): array {
                    if (app()->getLocale() === 'fr') {
                        $book['name'] = $this->bibleReferenceService->getBibleBook(
                            (int) $book['book_id'], 'fr', $user->includesDeuterocanonicalBooks() || (int) $book['book_id'] > 66
                        )['name'] ?? $book['name'];
                    }

                    return $book;
                })->all();
        }

        return $progress;
    }

    private function localizedRecentPassage(array $reading, User $user): string
    {
        if (app()->getLocale() !== 'fr') {
            return (string) $reading['passage_text'];
        }

        preg_match('/(\d+)(?:\s*[-–]\s*(\d+))?\s*$/u', (string) $reading['passage_text'], $chapters);
        $start = isset($chapters[1]) ? (int) $chapters[1] : (int) $reading['chapter'];
        $end = isset($chapters[2]) ? (int) $chapters[2] : $start;

        return $this->bibleReferenceService->formatBibleReferenceRange(
            (int) $reading['book_id'],
            $start,
            $end,
            'fr',
            $user->includesDeuterocanonicalBooks() || (int) $reading['book_id'] > 66
        );
    }

    private function localizedTimeAgo(string $timeAgo): string
    {
        if (app()->getLocale() !== 'fr') {
            return $timeAgo;
        }

        if ($timeAgo === 'just now') {
            return __('reader.time_ago.just_now');
        }

        if (preg_match('/^(\d+) (minute|minutes|hour|hours|day|days) ago$/', $timeAgo, $matches)) {
            $singular = in_array($matches[2], ['minute', 'hour', 'day'], true);
            $unit = match (true) {
                str_starts_with($matches[2], 'minute') => $singular ? 'minute' : 'minutes',
                str_starts_with($matches[2], 'hour') => $singular ? 'hour' : 'hours',
                default => $singular ? 'day' : 'days',
            };

            return __('reader.time_ago.'.$unit, ['count' => $matches[1]]);
        }

        return $timeAgo;
    }
}
