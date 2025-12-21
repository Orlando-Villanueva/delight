<?php

namespace App\Http\Controllers;

use App\Services\ReadingFormService;
use App\Services\StreakStateService;
use App\Services\UserStatisticsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ReadingFormService $readingFormService,
        private UserStatisticsService $statisticsService,
        private StreakStateService $streakStateService
    ) {}

    /**
     * Display the dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get reading status for today
        $hasReadToday = $this->readingFormService->hasReadToday($user);

        // Get dashboard statistics
        $stats = $this->statisticsService->getDashboardStatistics($user);

        // Extract weekly goal data for easier access in views
        $weeklyGoal = $stats['weekly_goal'];
        $weeklyJourney = $stats['weekly_journey'] ?? ($weeklyGoal['journey'] ?? null);

        // Get monthly calendar data for calendar widget
        $calendarData = $this->statisticsService->getMonthlyCalendarData($user);

        // Compute streak state and classes for the component
        $streakState = $this->streakStateService->determineStreakState(
            $stats['streaks']['current_streak'],
            $hasReadToday
        );
        $streakStateClasses = $this->streakStateService->getStateClasses($streakState);

        $recordStatus = data_get($stats, 'streaks.record_status', 'none');
        $recordJustBroken = data_get($stats, 'streaks.record_just_broken', false);
        $messagePayload = $this->streakStateService->getMessagePayload(
            $stats['streaks']['current_streak'],
            $streakState,
            $stats['streaks']['longest_streak'],
            $hasReadToday,
            $recordStatus,
            $recordJustBroken
        );
        $streakMessage = $messagePayload['message'];
        $streakMessageTone = $messagePayload['tone'] ?? 'default';

        // Return appropriate view based on request type
        return response()->htmx('dashboard', 'dashboard-content', compact('hasReadToday', 'streakState', 'streakStateClasses', 'streakMessage', 'streakMessageTone', 'stats', 'weeklyGoal', 'weeklyJourney', 'calendarData'));
    }
}
