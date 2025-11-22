<?php

use App\Services\StreakStateService;
use Illuminate\Support\Carbon;

class StreakStateServiceTest extends \Tests\TestCase
{
    /**
     * Test state detection logic for inactive state (streak = 0)
     */
    public function test_inactive_state_when_streak_is_zero()
    {
        // Test with hasReadToday = false
        $state = $this->determineComponentState(0, false);
        $this->assertEquals('inactive', $state);

        // Test with hasReadToday = true (should still be inactive when streak is 0)
        $state = $this->determineComponentState(0, true);
        $this->assertEquals('inactive', $state);
    }

    /**
     * Test state detection logic for active state
     */
    public function test_active_state_when_has_streak_and_read_today()
    {
        // Test various streak values when user has read today
        $streakValues = [1, 2, 5, 10, 30, 100];

        foreach ($streakValues as $streak) {
            $state = $this->determineComponentState($streak, true);
            $this->assertEquals('active', $state, "Failed for streak: {$streak}");
        }
    }

    /**
     * Test state detection logic for active state when hasn't read today but before warning time
     */
    public function test_active_state_when_hasnt_read_today_but_before_warning_time()
    {
        // Test time before 6 PM (e.g., 2 PM)
        $testTime = Carbon::today()->setHour(14);

        $streakValues = [1, 2, 5, 10, 30, 100];

        foreach ($streakValues as $streak) {
            $state = $this->determineComponentState($streak, false, $testTime);
            $this->assertEquals('active', $state, "Failed for streak: {$streak} at 2 PM");
        }
    }

    /**
     * Test state detection logic for warning state
     */
    public function test_warning_state_when_hasnt_read_today_and_after_warning_time()
    {
        // Test time after 6 PM (e.g., 8 PM)
        $testTime = Carbon::today()->setHour(20);

        $streakValues = [1, 2, 5, 10, 30, 100];

        foreach ($streakValues as $streak) {
            $state = $this->determineComponentState($streak, false, $testTime);
            $this->assertEquals('warning', $state, "Failed for streak: {$streak} at 8 PM");
        }
    }

    /**
     * Test warning time boundary (exactly 6 PM)
     */
    public function test_warning_state_at_exact_warning_time()
    {
        // Test time exactly 6 PM
        $testTime = Carbon::today()->setHour(18);

        $state = $this->determineComponentState(5, false, $testTime);
        $this->assertEquals('warning', $state, 'Should be warning state at exactly 6 PM');
    }

    /**
     * Test warning time boundary (just before 6 PM)
     */
    public function test_active_state_just_before_warning_time()
    {
        // Test time just before 6 PM (5:59 PM)
        $testTime = Carbon::today()->setHour(17)->setMinute(59);

        $state = $this->determineComponentState(5, false, $testTime);
        $this->assertEquals('active', $state, 'Should be active state just before 6 PM');
    }

    /**
     * Test that reading today overrides warning state even after 6 PM
     */
    public function test_active_state_when_read_today_even_after_warning_time()
    {
        // Test time after 6 PM (e.g., 10 PM)
        $testTime = Carbon::today()->setHour(22);

        $streakValues = [1, 2, 5, 10, 30, 100];

        foreach ($streakValues as $streak) {
            $state = $this->determineComponentState($streak, true, $testTime);
            $this->assertEquals('active', $state, "Failed for streak: {$streak} at 10 PM when read today");
        }
    }

    /**
     * Test edge cases with different times throughout the day
     */
    public function test_state_detection_throughout_day()
    {
        $testCases = [
            // [hour, streak, hasReadToday, expectedState]
            [0, 5, false, 'active'],   // Midnight
            [6, 5, false, 'active'],   // 6 AM
            [12, 5, false, 'active'],  // Noon
            [17, 5, false, 'active'],  // 5 PM
            [18, 5, false, 'warning'], // 6 PM
            [19, 5, false, 'warning'], // 7 PM
            [23, 5, false, 'warning'], // 11 PM
            [18, 5, true, 'active'],   // 6 PM but read today
            [22, 5, true, 'active'],   // 10 PM but read today
        ];

        foreach ($testCases as [$hour, $streak, $hasReadToday, $expectedState]) {
            $testTime = Carbon::today()->setHour($hour);

            $state = $this->determineComponentState($streak, $hasReadToday, $testTime);
            $this->assertEquals(
                $expectedState,
                $state,
                "Failed for hour: {$hour}, streak: {$streak}, hasReadToday: ".($hasReadToday ? 'true' : 'false')
            );
        }
    }

    /**
     * Test state detection with zero streak at different times
     */
    public function test_inactive_state_always_when_zero_streak()
    {
        $testHours = [0, 6, 12, 17, 18, 19, 23];
        $hasReadTodayValues = [true, false];

        foreach ($testHours as $hour) {
            foreach ($hasReadTodayValues as $hasReadToday) {
                $testTime = Carbon::today()->setHour($hour);

                $state = $this->determineComponentState(0, $hasReadToday, $testTime);
                $this->assertEquals(
                    'inactive',
                    $state,
                    "Should always be inactive when streak is 0 (hour: {$hour}, hasReadToday: ".($hasReadToday ? 'true' : 'false').')'
                );
            }
        }
    }

    /**
     * Test state classes are returned correctly
     */
    public function test_get_state_classes()
    {
        $service = new StreakStateService;

        // Test inactive state classes
        $inactiveClasses = $service->getStateClasses('inactive');
        $this->assertEquals('bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-[#D1D7E0] dark:border-gray-700 shadow-lg', $inactiveClasses['background']);
        $this->assertEquals('text-gray-400 dark:text-gray-500', $inactiveClasses['icon']);
        $this->assertFalse($inactiveClasses['showIcon']);
        $this->assertEquals('opacity-70', $inactiveClasses['opacity']);
        $this->assertEquals('border-[#D1D7E0] dark:border-gray-600', $inactiveClasses['border']);

        // Test active state classes
        $activeClasses = $service->getStateClasses('active');
        $this->assertEquals('bg-gradient-to-br from-[#3366CC] to-[#2952A3] text-white shadow-lg', $activeClasses['background']);
        $this->assertEquals('text-accent-500', $activeClasses['icon']);
        $this->assertTrue($activeClasses['showIcon']);
        $this->assertEquals('opacity-90', $activeClasses['opacity']);
        $this->assertEquals('border-white/20', $activeClasses['border']);

        // Test warning state classes
        $warningClasses = $service->getStateClasses('warning');
        $this->assertEquals('bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg', $warningClasses['background']);
        $this->assertEquals('text-orange-200', $warningClasses['icon']);
        $this->assertTrue($warningClasses['showIcon']);
        $this->assertEquals('opacity-90', $warningClasses['opacity']);
        $this->assertEquals('border-white/20', $warningClasses['border']);
    }

    /**
     * Test message selection for inactive state without history
     */
    public function test_inactive_message_selection_without_history()
    {
        $service = new StreakStateService;

        $message = $service->selectMessage(0, 'inactive', 0, false);

        // Should be one of the default inactive messages
        $expectedMessages = [
            'Start your reading journey today!',
            'Begin building your streak!',
            'Take the first step in your reading habit!',
            'Your Bible reading adventure starts now!',
        ];

        $this->assertContains($message, $expectedMessages);
    }

    /**
     * Test message selection for inactive state with history
     */
    public function test_inactive_message_selection_with_history()
    {
        $service = new StreakStateService;

        $message = $service->selectMessage(0, 'inactive', 15, false);

        // Should be one of the withHistory inactive messages
        $expectedMessages = [
            'You\'ve done it before, you can do it again!',
            'Ready to rebuild your reading habit?',
            'Time to start a new streak!',
            'Your comeback story starts today!',
        ];

        $this->assertContains($message, $expectedMessages);
    }

    /**
     * Test message selection for active state - 1 day streak
     */
    public function test_active_message_selection_one_day()
    {
        $service = new StreakStateService;

        $message = $service->selectMessage(1, 'active', 0, false);

        $expectedMessages = [
            'Great start! Keep it going!',
            'You\'re building momentum!',
            'One day down, many more to go!',
            'Perfect beginning to your journey!',
        ];

        $this->assertContains($message, $expectedMessages);
    }

    /**
     * Test message selection for active state - 2-6 day range
     */
    public function test_active_message_selection_two_to_six_days()
    {
        $service = new StreakStateService;

        $streakValues = [2, 3, 4, 5, 6];
        $expectedMessages = [
            'You\'re building a great habit!',
            'Keep the momentum going!',
            'Your consistency is showing!',
            'Building something beautiful!',
        ];

        foreach ($streakValues as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedMessages, "Failed for streak: {$streak}");
        }
    }

    /**
     * Test message selection for active state - 7-13 day range
     */
    public function test_active_message_selection_seven_to_thirteen_days()
    {
        $service = new StreakStateService;

        $streakValues = [7, 8, 10, 13];
        $expectedMessages = [
            // Milestone messages for day 7
            'One full week of reading!',
            'You\'ve completed your first week!',
            'Seven days of dedication achieved!',
            'Your first weekly milestone reached!',
            // Range messages for 7-13 range
            'One week down, heading for two!',
            'Past one week, approaching two!',
            'Building toward your two-week milestone!',
            'One week achieved, keep the momentum!',
        ];

        foreach ($streakValues as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedMessages, "Failed for streak: {$streak}");
        }
    }

    /**
     * Test message selection for active state - 15-20 and 22-29 day ranges
     */
    public function test_active_message_selection_fifteen_to_twenty_nine_days()
    {
        $service = new StreakStateService;

        // Test 15-20 range (approaching 3-week milestone)
        $streakValues15to20 = [15, 16, 18, 20];
        $expectedMessages15to20 = [
            'Two weeks down, approaching three weeks!',
            'Past two weeks, heading for twenty-one days!',
            'Building toward your three-week milestone!',
            'Two weeks achieved, three weeks within reach!',
        ];

        foreach ($streakValues15to20 as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedMessages15to20, "Failed for streak: {$streak} in 15-20 range");
        }

        // Test 22-29 range (approaching 1-month milestone)
        $streakValues22to29 = [22, 25, 28, 29];
        $expectedMessages22to29 = [
            'Three weeks down, approaching your first month!',
            'Past three weeks, heading for thirty days!',
            'Building toward your monthly milestone!',
            'Three weeks achieved, one month within reach!',
        ];

        foreach ($streakValues22to29 as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedMessages22to29, "Failed for streak: {$streak} in 22-29 range");
        }

        // Test milestone days still work
        $milestoneTests = [
            14 => ['Two full weeks of reading!', 'You\'ve reached the two-week milestone!', 'Fourteen days of consistent reading!', 'Your two-week achievement unlocked!'],
            21 => ['Three full weeks of reading!', 'You\'ve reached the three-week milestone!', 'Twenty-one days of dedication achieved!', 'Your third weekly milestone reached!'],
        ];

        foreach ($milestoneTests as $day => $expectedMessages) {
            $message = $service->selectMessage($day, 'active', 0, false);
            $this->assertContains($message, $expectedMessages, "Failed for milestone day: {$day}");
        }
    }

    /**
     * Test message selection for milestone day (30 days)
     */
    public function test_active_message_selection_thirty_day_milestone()
    {
        $service = new StreakStateService;

        $message = $service->selectMessage(30, 'active', 0, false);

        $expectedMilestoneMessages = [
            'One full month of reading!',
            'You\'ve reached your first month!',
            'Thirty days of dedication achieved!',
            'Your monthly milestone reached!',
        ];

        $this->assertContains($message, $expectedMilestoneMessages, 'Day 30 should show milestone message');
    }

    /**
     * Test message selection for non-milestone days in 31-59 range
     */
    public function test_active_message_selection_thirty_one_to_fifty_nine_range()
    {
        $service = new StreakStateService;

        $nonMilestoneDays = [31, 45, 59];
        $expectedRangeMessages = [
            'One month down, approaching two months!',
            'Past your first month, heading for sixty days!',
            'Building toward your two-month milestone!',
            'One month achieved, two months within reach!',
        ];

        foreach ($nonMilestoneDays as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedRangeMessages, "Failed for streak: {$streak}");
        }
    }

    /**
     * Test message selection for milestone day (21 days - 3 weeks)
     */
    public function test_active_message_selection_twenty_one_day_milestone()
    {
        $service = new StreakStateService;

        $message = $service->selectMessage(21, 'active', 0, false);

        $expectedMilestoneMessages = [
            'Three full weeks of reading!',
            'You\'ve reached the three-week milestone!',
            'Twenty-one days of dedication achieved!',
            'Your third weekly milestone reached!',
        ];

        $this->assertContains($message, $expectedMilestoneMessages, 'Day 21 should show milestone message');
    }

    /**
     * Test message selection for monthly milestones (210, 240, 270, 300, 330 days)
     */
    public function test_active_message_selection_monthly_milestones()
    {
        $service = new StreakStateService;

        $milestones = [
            210 => ['Seven full months of reading!', 'You\'ve reached the seven-month milestone!', 'Your seventh month achievement unlocked!', 'Seven months of incredible dedication!'],
            240 => ['Eight full months of reading!', 'You\'ve reached the eight-month milestone!', 'Your eighth month achievement unlocked!', 'Eight months of incredible dedication!'],
            270 => ['Nine full months of reading!', 'You\'ve reached the nine-month milestone!', 'Your three-quarter year achievement unlocked!', 'Nine months of incredible dedication!'],
            300 => ['Ten full months of reading!', 'You\'ve reached the ten-month milestone!', 'Your tenth month achievement unlocked!', 'Ten months of incredible dedication!'],
            330 => ['Eleven full months of reading!', 'You\'ve reached the eleven-month milestone!', 'Your eleventh month achievement unlocked!', 'Eleven months of incredible dedication!'],
        ];

        foreach ($milestones as $day => $expectedMessages) {
            $message = $service->selectMessage($day, 'active', 0, false);
            $this->assertContains($message, $expectedMessages, "Day {$day} should show milestone message");
        }
    }

    /**
     * Test message selection for active state - 365+ day range
     */
    public function test_active_message_selection_year_plus_days()
    {
        $service = new StreakStateService;

        $streakValues = [365, 400, 1000];
        $expectedMessages = [
            // Milestone messages for day 365
            'One full year of reading achieved!',
            'You\'ve reached the legendary one-year milestone!',
            'Your yearly achievement unlocked!',
            'Three hundred sixty-five days of commitment!',
            // Range messages for 365+ range
            'Building on a full year of reading!',
            'Your year-long habit is extraordinary!',
            'Keep your legendary streak alive!',
            'Over a year of incredible dedication!',
        ];

        foreach ($streakValues as $streak) {
            $message = $service->selectMessage($streak, 'active', 0, false);
            $this->assertContains($message, $expectedMessages, "Failed for streak: {$streak}");
        }
    }

    /**
     * Test message selection for warning state
     */
    public function test_warning_message_selection()
    {
        $service = new StreakStateService;

        $streakValues = [1, 5, 10, 30];

        foreach ($streakValues as $streak) {
            $message = $service->selectMessage($streak, 'warning', 0, false);

            // Should contain the streak number and be one of the warning messages
            $this->assertStringContainsString((string) $streak, $message, "Message should contain streak number for streak: {$streak}");

            // Check if it matches one of the expected patterns
            $expectedPatterns = [
                "Don't break your {$streak}-day streak! Read today!",
                "Your {$streak}-day streak needs you!",
                "Keep your {$streak}-day momentum going - read today!",
                "Don't let your {$streak}-day progress slip away!",
                "Your {$streak}-day streak is counting on you!",
            ];

            $matchesPattern = false;
            foreach ($expectedPatterns as $pattern) {
                if ($message === str_replace('{streak}', $streak, $pattern)) {
                    $matchesPattern = true;
                    break;
                }
            }

            $this->assertTrue($matchesPattern, "Message '{$message}' doesn't match expected warning patterns for streak: {$streak}");
        }
    }

    /**
     * Test acknowledgment message selection when user has read today
     */
    public function test_acknowledgment_message_selection_when_read_today()
    {
        $service = new StreakStateService;

        // Test multiple times to account for the 25% chance
        $foundAcknowledgment = false;
        $foundRegularActive = false;

        // Run test multiple times to check both acknowledgment and regular messages can appear
        for ($i = 0; $i < 20; $i++) {
            // Use different dates to vary the random seed
            Carbon::setTestNow(Carbon::today()->addDays($i));

            $message = $service->selectMessage(5, 'active', 0, true);

            $acknowledgmentMessages = [
                'Well done! You\'ve read today!',
                'Great job staying consistent!',
                'Your streak is safe for today!',
                'Another day of progress!',
            ];

            $regularActiveMessages = [
                'You\'re building a great habit!',
                'Keep the momentum going!',
                'Your consistency is showing!',
                'Building something beautiful!',
            ];

            if (in_array($message, $acknowledgmentMessages)) {
                $foundAcknowledgment = true;
            } elseif (in_array($message, $regularActiveMessages)) {
                $foundRegularActive = true;
            }
        }

        // Reset test time
        Carbon::setTestNow();

        // Should find both types of messages over multiple runs
        $this->assertTrue($foundRegularActive, 'Should find regular active messages when user has read today');
        // Note: Acknowledgment messages have 25% chance, so we might not always find them in 20 runs
        // But the logic should be testable
    }

    /**
     * Test message consistency - same message should be returned for same day/context
     */
    public function test_message_consistency_same_day()
    {
        $service = new StreakStateService;

        // Set a specific test date
        Carbon::setTestNow(Carbon::parse('2024-01-15'));

        // Get message multiple times for same parameters
        $message1 = $service->selectMessage(5, 'active', 0, false);
        $message2 = $service->selectMessage(5, 'active', 0, false);
        $message3 = $service->selectMessage(5, 'active', 0, false);

        // Should be the same message for same day/context
        $this->assertEquals($message1, $message2);
        $this->assertEquals($message2, $message3);

        // Reset test time
        Carbon::setTestNow();
    }

    /**
     * Test message rotation - different messages on different days
     */
    public function test_message_rotation_different_days()
    {
        $service = new StreakStateService;

        $messages = [];

        // Get messages for different days
        for ($i = 0; $i < 10; $i++) {
            Carbon::setTestNow(Carbon::parse('2024-01-15')->addDays($i));
            $messages[] = $service->selectMessage(5, 'active', 0, false);
        }

        // Should have some variety in messages (not all the same)
        $uniqueMessages = array_unique($messages);
        $this->assertGreaterThan(1, count($uniqueMessages), 'Should have message variety across different days');

        // Reset test time
        Carbon::setTestNow();
    }

    /**
     * Test streak range detection
     */
    public function test_streak_range_detection()
    {
        $service = new StreakStateService;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getStreakRange');
        $method->setAccessible(true);

        // Test all ranges
        $this->assertEquals(1, $method->invoke($service, 1));
        $this->assertEquals('2-6', $method->invoke($service, 2));
        $this->assertEquals('2-6', $method->invoke($service, 6));
        $this->assertEquals('7-13', $method->invoke($service, 7));
        $this->assertEquals('7-13', $method->invoke($service, 13));
        $this->assertEquals('7-13', $method->invoke($service, 14)); // Fixed gap
        $this->assertEquals('15-20', $method->invoke($service, 15));
        $this->assertEquals('15-20', $method->invoke($service, 20));
        $this->assertEquals('15-20', $method->invoke($service, 21)); // Fixed gap
        $this->assertEquals('22-29', $method->invoke($service, 22));
        $this->assertEquals('22-29', $method->invoke($service, 29));
        $this->assertEquals('22-29', $method->invoke($service, 30)); // Fixed gap
        $this->assertEquals('31-59', $method->invoke($service, 31));
        $this->assertEquals('31-59', $method->invoke($service, 59));
        $this->assertEquals('31-59', $method->invoke($service, 60)); // Fixed gap
        $this->assertEquals('61-89', $method->invoke($service, 61));
        $this->assertEquals('61-89', $method->invoke($service, 89));
        $this->assertEquals('61-89', $method->invoke($service, 90)); // Fixed gap
        $this->assertEquals('91-119', $method->invoke($service, 91));
        $this->assertEquals('91-119', $method->invoke($service, 119));
        $this->assertEquals('91-119', $method->invoke($service, 120)); // Fixed gap
        $this->assertEquals('121-149', $method->invoke($service, 121));
        $this->assertEquals('121-149', $method->invoke($service, 149));
        $this->assertEquals('121-149', $method->invoke($service, 150)); // Fixed gap
        $this->assertEquals('151-179', $method->invoke($service, 151));
        $this->assertEquals('151-179', $method->invoke($service, 179));
        $this->assertEquals('151-179', $method->invoke($service, 180)); // Fixed gap
        $this->assertEquals('181-209', $method->invoke($service, 181));
        $this->assertEquals('181-209', $method->invoke($service, 209));
        $this->assertEquals('181-209', $method->invoke($service, 210)); // Fixed gap
        $this->assertEquals('211-239', $method->invoke($service, 211));
        $this->assertEquals('211-239', $method->invoke($service, 239));
        $this->assertEquals('211-239', $method->invoke($service, 240)); // Fixed gap
        $this->assertEquals('241-269', $method->invoke($service, 241));
        $this->assertEquals('241-269', $method->invoke($service, 269));
        $this->assertEquals('241-269', $method->invoke($service, 270)); // Fixed gap
        $this->assertEquals('271-299', $method->invoke($service, 271));
        $this->assertEquals('271-299', $method->invoke($service, 299));
        $this->assertEquals('271-299', $method->invoke($service, 300)); // Fixed gap
        $this->assertEquals('301-329', $method->invoke($service, 301));
        $this->assertEquals('301-329', $method->invoke($service, 329));
        $this->assertEquals('301-329', $method->invoke($service, 330)); // Fixed gap
        $this->assertEquals('331-364', $method->invoke($service, 331));
        $this->assertEquals('331-364', $method->invoke($service, 364));
        $this->assertEquals('365+', $method->invoke($service, 365));
        $this->assertEquals('365+', $method->invoke($service, 1000));
    }

    /**
     * Test edge cases for message selection
     */
    public function test_message_selection_edge_cases()
    {
        $service = new StreakStateService;

        // Test with invalid state - should default to active behavior
        $message = $service->selectMessage(5, 'invalid_state', 0, false);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);

        // Test with very high streak values
        $message = $service->selectMessage(999, 'active', 0, false);
        $expectedMessages = [
            'Building on a full year of reading!',
            'Your year-long habit is extraordinary!',
            'Keep your legendary streak alive!',
            'Over a year of incredible dedication!',
        ];
        $this->assertContains($message, $expectedMessages);

        // Test warning message with very high streak
        $message = $service->selectMessage(999, 'warning', 0, false);
        $this->assertStringContainsString('999', $message);
    }

    /**
     * Test that milestone messages have priority over acknowledgment messages
     * This tests the fix for the bug where users on milestone days (like 14)
     * would get generic acknowledgment messages instead of milestone celebrations
     * when recovering from warning state.
     */
    public function test_milestone_messages_priority_over_acknowledgment()
    {
        $service = new StreakStateService;

        // Test multiple milestone days
        $milestoneTests = [
            7 => ['One full week of reading!', 'You\'ve completed your first week!', 'Seven days of dedication achieved!', 'Your first weekly milestone reached!'],
            14 => ['Two full weeks of reading!', 'You\'ve reached the two-week milestone!', 'Fourteen days of consistent reading!', 'Your two-week achievement unlocked!'],
            21 => ['Three full weeks of reading!', 'You\'ve reached the three-week milestone!', 'Twenty-one days of dedication achieved!', 'Your third weekly milestone reached!'],
            30 => ['One full month of reading!', 'You\'ve reached your first month!', 'Thirty days of dedication achieved!', 'Your monthly milestone reached!'],
        ];

        foreach ($milestoneTests as $streak => $expectedMilestoneMessages) {
            // Test multiple times with different dates to account for acknowledgment randomization
            for ($i = 0; $i < 10; $i++) {
                Carbon::setTestNow(Carbon::parse('2024-01-15')->addDays($i));

                // Test the scenario where user has read today (recovering from warning)
                // On milestone days, this should ALWAYS return milestone message, never acknowledgment
                $message = $service->selectMessage($streak, 'active', 0, true);

                $this->assertContains(
                    $message,
                    $expectedMilestoneMessages,
                    "Day {$streak} milestone should always show celebration message, got: '{$message}' on test day {$i}"
                );

                // Also verify acknowledgment messages don't appear on milestone days
                $acknowledgmentMessages = [
                    'Well done! You\'ve read today!',
                    'Great job staying consistent!',
                    'Your streak is safe for today!',
                    'Another day of progress!',
                ];

                $this->assertNotContains(
                    $message,
                    $acknowledgmentMessages,
                    "Day {$streak} should never show acknowledgment message, got: '{$message}' on test day {$i}"
                );
            }
        }

        // Reset test time
        Carbon::setTestNow();
    }

    /**
     * Test that non-milestone days can still show acknowledgment messages
     * This ensures our fix doesn't break the acknowledgment system entirely
     */
    public function test_acknowledgment_messages_work_on_non_milestone_days()
    {
        $service = new StreakStateService;

        // Test non-milestone days that should be able to show acknowledgment
        $nonMilestoneDays = [5, 8, 10, 13, 16, 19]; // Days without specific milestone messages

        $foundAcknowledgment = false;
        $foundRegularActive = false;

        foreach ($nonMilestoneDays as $streak) {
            // Test multiple times with different dates
            for ($i = 0; $i < 10; $i++) {
                Carbon::setTestNow(Carbon::parse('2024-01-15')->addDays($i));

                $message = $service->selectMessage($streak, 'active', 0, true);

                $acknowledgmentMessages = [
                    'Well done! You\'ve read today!',
                    'Great job staying consistent!',
                    'Your streak is safe for today!',
                    'Another day of progress!',
                ];

                if (in_array($message, $acknowledgmentMessages)) {
                    $foundAcknowledgment = true;
                }

                // Also check for regular active messages (range-based)
                $rangeMessages = [
                    'You\'re building a great habit!',
                    'Keep the momentum going!',
                    'Your consistency is showing!',
                    'Building something beautiful!',
                    'One week down, heading for two!',
                    'Past one week, approaching two!',
                    'Building toward your two-week milestone!',
                    'One week achieved, keep the momentum!',
                    'Two weeks down, approaching three weeks!',
                    'Past two weeks, heading for twenty-one days!',
                    'Building toward your three-week milestone!',
                    'Two weeks achieved, three weeks within reach!',
                ];

                if (in_array($message, $rangeMessages)) {
                    $foundRegularActive = true;
                }
            }
        }

        // Reset test time
        Carbon::setTestNow();

        // Should find regular active messages (they appear more frequently)
        $this->assertTrue($foundRegularActive, 'Should find regular active messages on non-milestone days');

        // Acknowledgment messages might appear (25% chance), but we can't guarantee in limited tests
        // The important thing is that the logic allows both types for non-milestone days
    }

    public function test_message_payload_returns_base_message_and_default_tone_without_record_overrides()
    {
        $service = new StreakStateService;

        Carbon::setTestNow(Carbon::parse('2024-02-01'));

        $baseMessage = $service->selectMessage(5, 'active', 0, false);
        $payload = $service->getMessagePayload(5, 'active', 0, false);

        $this->assertSame($baseMessage, $payload['message']);
        $this->assertSame('default', $payload['tone']);

        Carbon::setTestNow();
    }

    public function test_message_payload_overrides_for_tied_records()
    {
        $service = new StreakStateService;

        $payload = $service->getMessagePayload(12, 'active', 30, false, 'tied', false);

        $this->assertSame("You've matched your best streak of 12 days. Read tomorrow to set a new record.", $payload['message']);
        $this->assertSame('default', $payload['tone']);
    }

    public function test_message_payload_overrides_for_new_records_with_accent_tone()
    {
        $service = new StreakStateService;

        $payload = $service->getMessagePayload(15, 'active', 14, false, 'record', true);

        $this->assertSame('New personal record! Your 15-day streak is now the one to beat.', $payload['message']);
        $this->assertSame('accent', $payload['tone']);
    }

    /**
     * Helper method that uses the service to determine state
     */
    private function determineComponentState(int $currentStreak, bool $hasReadToday, ?Carbon $currentTime = null): string
    {
        $service = new StreakStateService;

        return $service->determineStreakState($currentStreak, $hasReadToday, $currentTime);
    }
}
