<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\AnnualRecapService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingFormService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
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
        private AchievementService $achievementService
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
            'bookProgress' => $stats['book_progress'],
            'recentActivity' => collect($stats['recent_activity'])
                ->take(10)
                ->map(fn (array $reading): array => [
                    'id' => (int) $reading['id'],
                    'passageText' => (string) $reading['passage_text'],
                    'dateRead' => $this->dateString($reading['date_read']),
                    'timeAgo' => (string) $reading['time_ago'],
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
            'monthName' => (string) $calendarData['monthName'],
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
                'displayName' => (string) $latest->display_name,
                'description' => (string) $latest->description,
                'icon' => (string) $latest->icon,
                'earnedAt' => $latest->earned_at?->toIso8601String(),
            ] : null,
            'milestone' => $milestone['milestone'] === null ? null : [
                'key' => (string) $milestone['milestone']['achievement_key'],
                'contextKey' => (string) $milestone['milestone']['context_key'],
                'displayName' => (string) $milestone['milestone']['display_name'],
                'description' => (string) $milestone['milestone']['description'],
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
}
