<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MobileBootstrapResource;
use App\Models\User;
use App\Services\BibleReferenceService;
use App\Services\ReadingFormService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
use Illuminate\Http\Request;

class MobileBootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        BibleReferenceService $bibleReferenceService,
        ReadingFormService $readingFormService,
        StreakStateService $streakStateService,
        UserStatisticsService $userStatisticsService,
    ): MobileBootstrapResource {
        /** @var User $user */
        $user = $request->user();
        $today = today();
        $includeDeuterocanonical = $user->includesDeuterocanonicalBooks();
        $recentBooks = $readingFormService->getRecentBooksForForm($user);
        $hasReadToday = $readingFormService->hasReadToday($user);
        $streaks = $userStatisticsService->getStreakStatistics($user);

        return new MobileBootstrapResource([
            'user' => $user,
            'today' => $today->toDateString(),
            'yesterday' => $today->copy()->subDay()->toDateString(),
            'books' => $bibleReferenceService->listBibleBooks(
                includeDeuterocanonical: $includeDeuterocanonical,
            ),
            'recent_book_ids' => array_column($recentBooks, 'id'),
            'has_read_today' => $hasReadToday,
            'streaks' => $streaks,
            'streak_state' => $streakStateService->determineStreakState(
                currentStreak: $streaks['current_streak'],
                hasReadToday: $hasReadToday,
            ),
            'reading_summary' => $userStatisticsService->getReadingSummary($user),
        ]);
    }
}
