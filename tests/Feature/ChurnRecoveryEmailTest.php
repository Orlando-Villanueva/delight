<?php

use App\Mail\ChurnRecoveryEmail;
use App\Models\ChurnRecoveryCampaign;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
use App\Services\ReadingLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    Carbon::setTestNow();
});

it('throws exception for invalid email number', function () {
    $user = User::factory()->create();

    expect(fn () => new ChurnRecoveryEmail($user, 4))
        ->toThrow(InvalidArgumentException::class, 'emailNumber must be between 1 and 3');

    expect(fn () => new ChurnRecoveryEmail($user, 0))
        ->toThrow(InvalidArgumentException::class, 'emailNumber must be between 1 and 3');
});

it('accepts valid email numbers 1-3', function () {
    $user = User::factory()->create();

    expect(new ChurnRecoveryEmail($user, 1))->toBeInstanceOf(ChurnRecoveryEmail::class);
    expect(new ChurnRecoveryEmail($user, 2))->toBeInstanceOf(ChurnRecoveryEmail::class);
    expect(new ChurnRecoveryEmail($user, 3))->toBeInstanceOf(ChurnRecoveryEmail::class);
});

it('accepts valid email numbers 1-2 for the 30-60 follow-up sequence', function () {
    $user = User::factory()->create();

    expect(new ChurnRecoveryEmail($user, 1, null, ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP))
        ->toBeInstanceOf(ChurnRecoveryEmail::class);
    expect(new ChurnRecoveryEmail($user, 2, null, ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP))
        ->toBeInstanceOf(ChurnRecoveryEmail::class);
});

it('throws exception for invalid 30-60 follow-up email number', function () {
    $user = User::factory()->create();

    expect(fn () => new ChurnRecoveryEmail($user, 3, null, ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP))
        ->toThrow(InvalidArgumentException::class, 'emailNumber must be between 1 and 2');
});

function churn_test_createUserWithEmail(int $daysAgo): User
{
    $user = User::factory()->create();
    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays($daysAgo),
    ]);

    return $user;
}

function churn_test_logReadings(ReadingLogService $service, User $user, array $daysAgo): void
{
    foreach ($daysAgo as $index => $day) {
        $service->logReading($user, [
            'book_id' => 1,
            'chapter' => $index + 1,
            'date_read' => now()->subDays($day)->toDateString(),
        ]);
    }
}

function churn_test_assertEmailExists(int $userId): void
{
    expect(DB::table('churn_recovery_emails')->where('user_id', $userId)->whereNull('deleted_at')->exists())->toBeTrue();
}

function churn_test_assertEmailSoftDeleted(int $userId): void
{
    expect(DB::table('churn_recovery_emails')->where('user_id', $userId)->whereNull('deleted_at')->exists())->toBeFalse();
    expect(DB::table('churn_recovery_emails')->where('user_id', $userId)->whereNotNull('deleted_at')->exists())->toBeTrue();
}

function churn_test_createThirtyToSixtyCandidate(int $desiredParity): User
{
    while (true) {
        $user = User::factory()->create([
            'created_at' => now()->subDays(120),
        ]);

        if ($user->id % 2 === $desiredParity) {
            break;
        }

        $user->delete();
    }

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(40)->format('Y-m-d'),
        'created_at' => now()->subDays(40),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 3,
        'sent_at' => now()->subDays(8),
    ]);

    return $user;
}

function churn_test_getThirtyToSixtyCampaign(User $user): ?ChurnRecoveryCampaign
{
    return ChurnRecoveryCampaign::query()
        ->where('user_id', $user->id)
        ->where('campaign_key', 'inactive_30_60_followup')
        ->first();
}

test('command finds users inactive for 7+ days', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(8)->format('Y-m-d'),
    ]);

    $this->artisan('churn:send-recovery', ['--dry-run' => true])
        ->expectsOutputToContain('1 users eligible')
        ->assertSuccessful();
});

test('command excludes users who opted out', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update([
        'marketing_emails_opted_out_at' => now(),
    ]);

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(31)->format('Y-m-d'),
    ]);

    $this->artisan('churn:send-recovery', ['--dry-run' => true])
        ->expectsOutputToContain('0 users eligible')
        ->assertSuccessful();
});

test('command excludes users who already received email 1', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(35)->format('Y-m-d'),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(2),
    ]);

    $this->artisan('churn:send-recovery', ['--dry-run' => true])
        ->expectsOutputToContain('0 users eligible')
        ->assertSuccessful();
});

test('command sends email 2 after 7 days', function () {
    Mail::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(40)->format('Y-m-d'),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(7),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && $mail->emailNumber === 2;
    });

    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 2,
    ]);
});

test('command excludes users who re-activated after email', function () {
    Mail::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(40)->format('Y-m-d'),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(10),
    ]);

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(2)->format('Y-m-d'),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
});

test('dry run does not send emails', function () {
    Mail::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(31)->format('Y-m-d'),
    ]);

    $this->artisan('churn:send-recovery', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingSent();
    $this->assertDatabaseCount('churn_recovery_emails', 0);
});

test('dry run does not complete overdue 30-60 control campaigns', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(0);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign?->completed_at)->toBeNull();

    Mail::fake();
    Carbon::setTestNow('2026-03-16 12:00:00');

    $this->artisan('churn:send-recovery', ['--dry-run' => true])->assertSuccessful();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    Mail::assertNothingSent();
    expect($campaign?->completed_at)->toBeNull();
});

test('dry run does not complete reactivated 30-60 follow-up campaigns', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-03-09 12:00:00');

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->toDateString(),
        'created_at' => now(),
    ]);

    Mail::fake();
    Carbon::setTestNow('2026-03-11 12:00:00');

    $this->artisan('churn:send-recovery', ['--dry-run' => true])->assertSuccessful();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    Mail::assertNothingSent();
    expect($campaign?->reactivated_at)->toBeNull();
    expect($campaign?->completed_at)->toBeNull();
});

test('command sends email 1 with last reading passage', function () {
    Mail::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(10)->format('Y-m-d'),
        'passage_text' => 'John 3',
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->lastReadingPassage === 'John 3';
    });
});

test('command sends email 1 without passage if none exists', function () {
    Mail::fake();

    $user = User::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    // No reading logs

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->lastReadingPassage === null;
    });
});

test('churn recovery reset requires 3 days of activity', function () {
    $user = churn_test_createUserWithEmail(100);
    $service = app(ReadingLogService::class);

    churn_test_logReadings($service, $user, [1]);
    churn_test_assertEmailExists($user->id);

    churn_test_logReadings($service, $user, [0]);
    churn_test_assertEmailExists($user->id);
});

test('churn recovery reset occurs after 3 days of activity', function () {
    $user = churn_test_createUserWithEmail(100);
    $service = app(ReadingLogService::class);

    churn_test_logReadings($service, $user, [2, 1, 0]);
    churn_test_assertEmailSoftDeleted($user->id);
});

test('churn recovery reset respects 90 day cooldown', function () {
    $user = churn_test_createUserWithEmail(80);
    $service = app(ReadingLogService::class);

    churn_test_logReadings($service, $user, [2, 1, 0]);
    churn_test_assertEmailExists($user->id);
});

test('command ignores soft deleted records and starts fresh', function () {
    Mail::fake();
    $user = User::factory()->create([
        'created_at' => now()->subDays(20),
    ]);

    // Soft deleted record
    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(100),
        'deleted_at' => now(),
    ]);

    // No recent readings, so user is eligible (inactive > 7 days)

    $this->artisan('churn:send-recovery')->assertSuccessful();

    // Should send email #1 because previous history is deleted
    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && $mail->emailNumber === 1;
    });
});

test('command starts a 30-60 follow-up campaign for odd-id users and sends touch 1', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP;
    });

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign)->not->toBeNull();
    expect($campaign?->variant)->toBe('two_touch_followup');
    expect($campaign?->last_touch_sent_at)->not->toBeNull();

    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 1,
        'churn_recovery_campaign_id' => $campaign?->id,
    ]);
});

test('command assigns even-id users to the 30-60 control path without sending mail', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(0);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign)->not->toBeNull();
    expect($campaign?->variant)->toBe('current_flow_control');

    $this->assertDatabaseMissing('churn_recovery_emails', [
        'user_id' => $user->id,
        'churn_recovery_campaign_id' => $campaign?->id,
    ]);
});

test('command completes expired control campaigns before selecting legacy emails', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-16 12:00:00');

    $user = User::factory()->create([
        'created_at' => now()->subDays(120),
    ]);

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(40)->format('Y-m-d'),
        'created_at' => now()->subDays(40),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(8),
    ]);

    $campaign = ChurnRecoveryCampaign::create([
        'user_id' => $user->id,
        'campaign_key' => 'inactive_30_60_followup',
        'cohort' => 'inactive_30_60_days',
        'variant' => 'current_flow_control',
        'started_at' => now()->subDays(7),
        'observed_until' => now(),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 2
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_LEGACY;
    });

    expect($campaign->fresh()?->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 2,
        'churn_recovery_campaign_id' => null,
    ]);
});

test('command sends the second 30-60 follow-up touch after three days when the user is still inactive', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::fake();
    Carbon::setTestNow('2026-03-11 12:00:00');

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 2
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP;
    });

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign?->completed_at)->not->toBeNull();

    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 2,
        'churn_recovery_campaign_id' => $campaign?->id,
    ]);
});

test('command does not send a duplicate second 30-60 follow-up touch after lock acquisition', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign)->not->toBeNull();

    Cache::shouldReceive('lock')
        ->once()
        ->with('churn-30-60-touch-2-'.$user->id, 30)
        ->andReturn(new class($campaign)
        {
            private bool $inserted = false;

            public function __construct(private ChurnRecoveryCampaign $campaign) {}

            public function get(): bool
            {
                if (! $this->inserted) {
                    DB::table('churn_recovery_emails')->insert([
                        'user_id' => $this->campaign->user_id,
                        'churn_recovery_campaign_id' => $this->campaign->id,
                        'email_number' => 2,
                        'sent_at' => now(),
                    ]);

                    DB::table('churn_recovery_campaigns')
                        ->where('id', $this->campaign->id)
                        ->update([
                            'last_touch_sent_at' => now(),
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $this->inserted = true;
                }

                return true;
            }

            public function release(): void {}
        });

    Mail::fake();
    Carbon::setTestNow('2026-03-11 12:00:00');

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('churn_recovery_emails')
        ->where('user_id', $user->id)
        ->where('churn_recovery_campaign_id', $campaign?->id)
        ->where('email_number', 2)
        ->count())->toBe(1);
});

test('command suppresses the second 30-60 follow-up touch after reactivation', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-03-09 12:00:00');

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->format('Y-m-d'),
        'created_at' => now(),
    ]);

    Mail::fake();
    Carbon::setTestNow('2026-03-11 12:00:00');

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign?->reactivated_at)->not->toBeNull();
    expect($campaign?->completed_at)->not->toBeNull();
});

test('command does not create duplicate 30-60 follow-up campaigns on repeated runs', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(0);

    $this->artisan('churn:send-recovery')->assertSuccessful();
    $this->artisan('churn:send-recovery')->assertSuccessful();

    expect(ChurnRecoveryCampaign::query()
        ->where('user_id', $user->id)
        ->where('campaign_key', 'inactive_30_60_followup')
        ->count())->toBe(1);
});

test('command retries 30-60 follow-up enrollment after touch 1 send failure', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $failingEmailService = $this->mock(EmailService::class);
    $failingEmailService->shouldReceive('sendWithErrorHandling')
        ->once()
        ->andReturn(false);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect(churn_test_getThirtyToSixtyCampaign($user))->toBeNull();
    expect(DB::table('churn_recovery_emails')
        ->where('user_id', $user->id)
        ->where('email_number', 1)
        ->doesntExist())->toBeTrue();

    $this->app->instance(EmailService::class, new EmailService);
    Mail::fake();

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP;
    });

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign)->not->toBeNull();
    expect($campaign?->last_touch_sent_at)->not->toBeNull();
    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 1,
        'churn_recovery_campaign_id' => $campaign?->id,
    ]);
});

test('campaign emails do not replay the legacy churn sequence after follow-up participation', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-03-11 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-03-18 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP;
    });

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 2
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP;
    });

    Mail::assertNotSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->sequence === ChurnRecoveryEmail::SEQUENCE_LEGACY;
    });

    expect(DB::table('churn_recovery_emails')
        ->where('user_id', $user->id)
        ->count())->toBe(3);
    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 3,
        'churn_recovery_campaign_id' => null,
    ]);
});

test('logging multiple chapters marks the active follow-up campaign reactivated and suppresses touch 2', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = churn_test_createThirtyToSixtyCandidate(1);
    $service = app(ReadingLogService::class);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-03-09 12:00:00');
    $service->logReading($user, [
        'book_id' => 1,
        'chapters' => [1, 2],
        'date_read' => now()->toDateString(),
    ]);

    $campaign = churn_test_getThirtyToSixtyCampaign($user);

    expect($campaign?->reactivated_at)->not->toBeNull();
    expect($campaign?->completed_at)->not->toBeNull();

    Mail::fake();
    Carbon::setTestNow('2026-03-11 12:00:00');

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
});

test('users outside the 30-60 inactivity cohort do not get a 30-60 follow-up campaign', function () {
    Mail::fake();
    Carbon::setTestNow('2026-03-08 12:00:00');

    $user = User::factory()->create([
        'created_at' => now()->subDays(120),
    ]);

    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(20)->format('Y-m-d'),
        'created_at' => now()->subDays(20),
    ]);

    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 3,
        'sent_at' => now()->subDays(8),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    expect(ChurnRecoveryCampaign::query()
        ->where('user_id', $user->id)
        ->where('campaign_key', 'inactive_30_60_followup')
        ->exists())->toBeFalse();
});
