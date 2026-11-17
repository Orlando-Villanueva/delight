<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AchievementsController extends Controller
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $shelf = $this->achievementService->getShelfData($user);
        $latest = $shelf['latest'];
        $categoryLabels = config('achievements.categories', []);
        $earnedByCategory = $shelf['earned'];
        $categories = [];

        foreach ($categoryLabels as $key => $label) {
            $achievements = $earnedByCategory->get($key, collect())
                ->map(fn (array $group): array => $this->mapAchievement($group, $user))
                ->values()
                ->all();

            if ($achievements !== []) {
                $categories[] = [
                    'key' => $key,
                    'label' => app()->getLocale() === 'fr' ? __('achievements.categories.'.$key) : $label,
                    'achievements' => $achievements,
                ];
            }
        }

        $nextGoals = [
            'books' => collect($shelf['next_goals']['books'])
                ->map(fn (array $goal): array => [
                    'key' => 'book:'.$goal['book_id'],
                    'name' => $this->localizedGoalBookName($goal),
                    'chaptersRead' => $goal['chapters_read'],
                    'totalChapters' => $goal['total_chapters'],
                    'chaptersRemaining' => $goal['chapters_remaining'],
                    'missingChapters' => $goal['missing_chapters'],
                    'completedCount' => $goal['completed_count'],
                    'progressPercent' => $goal['progress_percent'],
                    'icon' => $goal['icon'],
                ])
                ->all(),
            'progress' => collect($shelf['next_goals']['progress'])
                ->map(fn (array $goal): array => [
                    'key' => $goal['achievement_key'],
                    'name' => $this->achievementService->localizedPayload($goal)['display_name'],
                    'description' => $this->achievementService->localizedPayload($goal)['description'],
                    'icon' => $goal['icon'],
                    'current' => $goal['current'],
                    'target' => $goal['target'],
                    'progressPercent' => $goal['progress_percent'],
                    'kind' => match (true) {
                        $goal['achievement_key'] === 'first_month' => 'reading_days',
                        str_starts_with($goal['achievement_key'], 'reading_streak_') => 'longest_streak',
                        str_starts_with($goal['achievement_key'], 'bible_progress_') => 'bible_progress',
                        default => 'milestone',
                    },
                    'unit' => str_starts_with($goal['achievement_key'], 'bible_progress_') ? 'percent' : 'days',
                ])
                ->all(),
        ];

        $latestAchievement = null;
        if ($latest) {
            $latestAchievement = [
                'name' => app()->getLocale() === 'fr' ? $this->achievementService->localizedCopy($latest)['display_name'] : $latest->display_name,
                'icon' => $latest->icon,
                'earnedOn' => $latest->earned_at->toDateString(),
            ];
        }

        return Inertia::render('Achievements', [
            'badgeBaseUrl' => asset('images/achievements'),
            'categories' => $categories,
            'nextGoals' => $nextGoals,
            'latestAchievement' => $latestAchievement,
        ]);
    }

    private function mapAchievement(array $group, User $user): array
    {
        $achievement = $group['achievement'];
        $metadata = $achievement->metadata ?? [];
        $completionDates = $group['completion_dates'];
        $passage = trim((string) ($metadata['passage'] ?? ''));
        $readingDate = $metadata['date_read'] ?? null;
        $groupKey = match ($achievement->achievement_key) {
            'book_completed' => 'book:'.($metadata['book_id'] ?? $achievement->context_key),
            'testament_completed' => 'testament:'.($metadata['testament'] ?? $achievement->context_key),
            'bible_completed' => 'bible:'.($metadata['collection_key'] ?? $achievement->context_key),
            default => $achievement->context_key,
        };
        $reading = null;
        if ($achievement->achievement_key === 'first_reading' && $passage !== '' && $readingDate) {
            $reading = [
                'passage' => app()->getLocale() === 'fr' ? $this->achievementService->localizedReadingPassage($metadata, $user) : $passage,
                'date' => Carbon::parse($readingDate)->toDateString(),
            ];
        }

        return [
            'id' => $achievement->achievement_key.':'.$groupKey,
            'key' => $achievement->achievement_key,
            'name' => app()->getLocale() === 'fr' ? $this->achievementService->localizedCopy($achievement)['display_name'] : $achievement->display_name,
            'description' => app()->getLocale() === 'fr' ? $this->achievementService->localizedCopy($achievement)['description'] : $achievement->description,
            'icon' => $achievement->icon,
            'count' => $group['count'],
            'earnedOn' => $achievement->earned_at->toDateString(),
            'reading' => $reading,
            'completions' => $completionDates
                ->map(fn (array $completion): array => [
                    'completedOn' => $completion['completed_on']?->toDateString(),
                    'earnedOn' => $completion['earned_at']->toDateString(),
                ])
                ->all(),
        ];
    }

    private function localizedGoalBookName(array $goal): string
    {
        if (app()->getLocale() === 'fr') {
            return $this->achievementService->localizedBookName((int) $goal['book_id']) ?? $goal['book_name'];
        }

        return $goal['book_name'];
    }
}
