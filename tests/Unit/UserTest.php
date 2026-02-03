<?php

namespace Tests\Unit;

use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_onboarding_when_user_has_no_reading_logs()
    {
        $user = User::factory()->create();
        expect($user->needsOnboarding())->toBeTrue();
    }

    public function test_does_not_need_onboarding_after_logging_a_reading()
    {
        $user = User::factory()->create();
        ReadingLog::factory()->for($user)->create([
            'passage_text' => 'John 1',
            'date_read' => now(),
        ]);
        expect($user->needsOnboarding())->toBeFalse();
    }

    public function test_does_not_need_onboarding_after_dismissing()
    {
        $user = User::factory()->create(['onboarding_dismissed_at' => now()]);
        expect($user->needsOnboarding())->toBeFalse();
    }
}
