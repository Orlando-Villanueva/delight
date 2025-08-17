<?php

namespace App\Services;

use Carbon\Carbon;

class StreakStateService
{

    /**
     * Determine the visual state of the streak counter component
     * 
     * @param int $currentStreak
     * @param bool $hasReadToday
     * @param Carbon|null $currentTime
     * @return string
     */
    public function determineStreakState(int $currentStreak, bool $hasReadToday, ?Carbon $currentTime = null): string
    {
        $currentTime = $currentTime ?? now();

        // Inactive state: current streak is 0
        if ($currentStreak === 0) {
            return 'inactive';
        }

        // Warning state: has streak but hasn't read today and it's past warning time (6 PM)
        if ($currentStreak > 0 && !$hasReadToday && $currentTime->hour >= 18) {
            // Mark that user entered warning state today for acknowledgment tracking
            $this->markWarningStateToday();
            return 'warning';
        }

        // Active state: has streak and either read today or not past warning time
        return 'active';
    }

    /**
     * Get the CSS classes for a given streak state
     * 
     * @param string $state
     * @return array
     */
    public function getStateClasses(string $state): array
    {
        $stateClasses = [
            'inactive' => 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-[#D1D7E0] dark:border-gray-700 shadow-lg',
            'active' => 'bg-gradient-to-br from-[#3366CC] to-[#2952A3] text-white shadow-lg',
            'warning' => 'bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg'
        ];

        $iconColors = [
            'inactive' => 'text-gray-400 dark:text-gray-500',
            'active' => 'text-accent-500',
            'warning' => 'text-orange-200'
        ];

        return [
            'background' => $stateClasses[$state] ?? $stateClasses['active'],
            'icon' => $iconColors[$state] ?? $iconColors['active'],
            'showIcon' => $state !== 'inactive',
            'opacity' => $state === 'inactive' ? 'opacity-70' : 'opacity-90',
            'border' => $state === 'inactive' ? 'border-[#D1D7E0] dark:border-gray-600' : 'border-white/20'
        ];
    }

    /**
     * Select appropriate message based on current streak, state, and longest streak
     * 
     * @param int $currentStreak
     * @param string $state
     * @param int $longestStreak
     * @param bool $hasReadToday
     * @return string
     */
    public function selectMessage(int $currentStreak, string $state, int $longestStreak = 0, bool $hasReadToday = false): string
    {
        switch ($state) {
            case 'inactive':
                return $this->selectInactiveMessage($longestStreak);
            
            case 'warning':
                return $this->selectWarningMessage($currentStreak);
            
            case 'active':
                // Check for milestones first - they always take priority
                $milestoneMessages = config('streak_messages.milestone.' . $currentStreak);
                if ($milestoneMessages) {
                    return $this->selectActiveMessage($currentStreak);
                }
                
                // Check if user was in warning state today and then read to save their streak
                if ($hasReadToday && $this->wasInWarningStateToday()) {
                    return $this->selectAcknowledgmentMessage();
                }
                
                return $this->selectActiveMessage($currentStreak);
            
            default:
                return $this->selectActiveMessage($currentStreak);
        }
    }

    /**
     * Select message for inactive state (0 streak)
     * 
     * @param int $longestStreak
     * @return string
     */
    private function selectInactiveMessage(int $longestStreak): string
    {
        $messageType = $longestStreak > 0 ? 'withHistory' : 'default';
        $messages = config('streak_messages.inactive.' . $messageType);
        
        return $this->rotateMessage($messages, 'inactive_' . $messageType);
    }

    /**
     * Select message for active state (1+ streak)
     * 
     * @param int $currentStreak
     * @return string
     */
    private function selectActiveMessage(int $currentStreak): string
    {
        // Check if this is a milestone day first
        $milestoneMessages = config('streak_messages.milestone.' . $currentStreak);
        if ($milestoneMessages) {
            return $this->rotateMessage($milestoneMessages, 'milestone_' . $currentStreak);
        }
        
        // Otherwise use regular range-based messages
        $range = $this->getStreakRange($currentStreak);
        $messages = config('streak_messages.active.' . $range);
        
        return $this->rotateMessage($messages, 'active_' . $range);
    }

    /**
     * Select message for warning state
     * 
     * @param int $currentStreak
     * @return string
     */
    private function selectWarningMessage(int $currentStreak): string
    {
        $messages = config('streak_messages.warning');
        $message = $this->rotateMessage($messages, 'warning');
        
        // Replace {streak} placeholder with actual streak value
        return str_replace('{streak}', $currentStreak, $message);
    }

    /**
     * Select acknowledgment message for when user has read today
     * 
     * @return string
     */
    private function selectAcknowledgmentMessage(): string
    {
        $messages = config('streak_messages.acknowledge');
        return $this->rotateMessage($messages, 'acknowledge');
    }

    /**
     * Determine the streak range for message selection
     * 
     * @param int $streak
     * @return string|int
     */
    private function getStreakRange(int $streak): string|int
    {
        if ($streak === 1) {
            return 1;
        } elseif ($streak >= 2 && $streak <= 6) {
            return '2-6';
        } elseif ($streak >= 7 && $streak <= 14) {
            return '7-13';
        } elseif ($streak >= 15 && $streak <= 21) {
            return '15-20';
        } elseif ($streak >= 22 && $streak <= 30) {
            return '22-29';
        } elseif ($streak >= 31 && $streak <= 59) {
            return '31-59';
        } elseif ($streak >= 61 && $streak <= 89) {
            return '61-89';
        } elseif ($streak >= 91 && $streak <= 119) {
            return '91-119';
        } elseif ($streak >= 121 && $streak <= 149) {
            return '121-149';
        } elseif ($streak >= 151 && $streak <= 179) {
            return '151-179';
        } elseif ($streak >= 181 && $streak <= 209) {
            return '181-209';
        } elseif ($streak >= 211 && $streak <= 239) {
            return '211-239';
        } elseif ($streak >= 241 && $streak <= 269) {
            return '241-269';
        } elseif ($streak >= 271 && $streak <= 299) {
            return '271-299';
        } elseif ($streak >= 301 && $streak <= 329) {
            return '301-329';
        } elseif ($streak >= 331 && $streak <= 364) {
            return '331-364';
        } else {
            return '365+';
        }
    }

    /**
     * Rotate between messages to prevent user desensitization
     * Uses a simple hash-based rotation to ensure consistency per user/day
     * 
     * @param array $messages
     * @param string $context
     * @return string
     */
    private function rotateMessage(array $messages, string $context): string
    {
        // Create a seed based on current date and context to ensure same message per day
        // but different messages across days and contexts
        $dateString = now()->format('Y-m-d');
        $seed = hash('crc32b', $dateString . '_' . $context);
        $index = hexdec(substr($seed, 0, 8)) % count($messages);
        
        return $messages[$index];
    }

    /**
     * Check if user was in warning state today (after 6 PM before reading)
     * This is used to show acknowledgment messages only after the user "saved" their streak
     * 
     * @return bool
     */
    private function wasInWarningStateToday(): bool
    {
        // Check if there's a cached warning state flag for today
        $cacheKey = 'warning_state_' . auth()->id() . '_' . now()->format('Y-m-d');
        return cache()->has($cacheKey);
    }

    /**
     * Mark that user was in warning state today
     * This should be called when the user enters warning state (after 6 PM without reading)
     * 
     * @return void
     */
    public function markWarningStateToday(): void
    {
        $cacheKey = 'warning_state_' . auth()->id() . '_' . now()->format('Y-m-d');
        // Cache until end of day - will automatically clear at midnight
        $minutesUntilMidnight = now()->diffInMinutes(now()->endOfDay());
        cache()->put($cacheKey, true, $minutesUntilMidnight);
    }
}
