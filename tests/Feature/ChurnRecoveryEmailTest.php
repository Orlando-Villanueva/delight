<?php

use App\Console\Commands\SendChurnRecoveryEmails;
use App\Mail\ChurnRecoveryEmail;
use App\Models\ChurnRecoveryCampaign;
use App\Models\ChurnRecoveryEmail as ChurnRecoveryEmailRecord;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\EmailService;
use App\Services\ReadingLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    Carbon::setTestNow();
});

function reengagementUser(int $inactiveDays, ?string $passage = 'John 3'): User
{
    $user = User::factory()->create([
        'created_at' => now()->subDays($inactiveDays + 30),
    ]);

    ReadingLog::factory()->for($user)->create([
        'date_read' => now()->subDays($inactiveDays)->toDateString(),
        'created_at' => now()->subDays($inactiveDays),
        'passage_text' => $passage,
    ]);

    return $user;
}

function currentReengagementCampaign(User $user): ?ChurnRecoveryCampaign
{
    return ChurnRecoveryCampaign::query()
        ->where('user_id', $user->id)
        ->where('campaign_key', SendChurnRecoveryEmails::CAMPAIGN_KEY)
        ->latest('started_at')
        ->first();
}

function assertReengagementEmailSent(User $user, int $emailNumber): void
{
    Mail::assertSent(ChurnRecoveryEmail::class, function (ChurnRecoveryEmail $mail) use ($user, $emailNumber): bool {
        return $mail->hasTo($user->email) && $mail->emailNumber === $emailNumber;
    });
}

it('accepts only the three supported email numbers', function () {
    $user = User::factory()->create();

    foreach ([1, 2, 3] as $emailNumber) {
        expect(new ChurnRecoveryEmail($user, $emailNumber))->toBeInstanceOf(ChurnRecoveryEmail::class);
    }

    expect(fn () => new ChurnRecoveryEmail($user, 0))
        ->toThrow(InvalidArgumentException::class, 'emailNumber must be between 1 and 3');
    expect(fn () => new ChurnRecoveryEmail($user, 4))
        ->toThrow(InvalidArgumentException::class, 'emailNumber must be between 1 and 3');
});

it('renders the approved subjects, direct CTAs, and warm direct language', function (int $emailNumber, string $subject, string $cta) {
    $user = User::factory()->create(['name' => 'Reader']);
    $mail = new ChurnRecoveryEmail($user, $emailNumber, 'John 3');
    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe($subject)
        ->and($html)->toContain($cta)
        ->and($html)->toContain(route('logs.create'))
        ->and($html)->toContain('— Delight')
        ->and(strtolower($html))->not->toContain('grace')
        ->not->toContain('guilt')
        ->not->toContain('journey')
        ->not->toContain('habit')
        ->not->toContain('streak')
        ->not->toContain('60 seconds');
})->with([
    'first check-in' => [1, 'Keep your reading history up to date', 'Log a Reading'],
    'second check-in' => [2, 'A quick check-in from Delight', 'Update My Reading Log'],
    'final check-in' => [3, 'Add your latest reading to Delight', 'Log a Reading'],
]);

it('starts the sequence after seven inactive days and includes the last passage', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(7, 'John 3');

    $this->artisan('churn:send-recovery')->assertSuccessful();

    $campaign = currentReengagementCampaign($user);
    expect($campaign)->not->toBeNull()
        ->and($campaign?->emails()->count())->toBe(1);
    Mail::assertSentCount(1);
    Mail::assertSent(ChurnRecoveryEmail::class, function (ChurnRecoveryEmail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->emailNumber === 1
            && $mail->lastReadingPassage === 'John 3';
    });

    expect($campaign?->variant)->toBe('days_7_14_30');
});

it('does not start before seven inactive days', function () {
    Mail::fake();
    reengagementUser(6);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect(ChurnRecoveryCampaign::query()->where('campaign_key', SendChurnRecoveryEmails::CAMPAIGN_KEY)->count())->toBe(0);
});

it('excludes users who never logged a reading', function () {
    Mail::fake();
    User::factory()->create(['created_at' => now()->subMonth()]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
});

it('excludes users who opted out of marketing emails', function () {
    Mail::fake();
    $user = reengagementUser(10);
    $user->update(['marketing_emails_opted_out_at' => now()]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends email two at fourteen inactive days and seven days after email one', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');
    $user = reengagementUser(7);
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::fake();
    Carbon::setTestNow('2026-07-08 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    assertReengagementEmailSent($user, 2);
});

it('does not send email two before seven days have passed since email one', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');
    $user = reengagementUser(8);
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::fake();
    Carbon::setTestNow('2026-07-07 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect(currentReengagementCampaign($user)?->emails()->count())->toBe(1);
});

it('sends email three at thirty inactive days and sixteen days after email two', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');
    $user = reengagementUser(7);
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-07-08 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::fake();
    Carbon::setTestNow('2026-07-24 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();

    assertReengagementEmailSent($user, 3);
    expect(currentReengagementCampaign($user)?->observed_until?->equalTo(now()->addDays(7)))->toBeTrue();
});

it('sends at most one catch-up email per command run', function () {
    Mail::fake();
    $user = reengagementUser(45);

    $this->artisan('churn:send-recovery')->assertSuccessful();
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSentCount(1);
    assertReengagementEmailSent($user, 1);
});

it('completes the campaign when a user logs yesterday after receiving an email', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(8);
    $this->artisan('churn:send-recovery')->assertSuccessful();

    Carbon::setTestNow('2026-07-19 12:00:00');
    app(ReadingLogService::class)->logReading($user, [
        'book_id' => 1,
        'chapter' => 4,
        'date_read' => now()->subDay()->toDateString(),
    ]);

    $campaign = currentReengagementCampaign($user)?->fresh();
    expect($campaign?->reactivated_at)->not->toBeNull()
        ->and($campaign?->completed_at)->not->toBeNull();

    Mail::fake();
    Carbon::setTestNow('2026-08-05 12:00:00');
    $this->artisan('churn:send-recovery')->assertSuccessful();
    Mail::assertNothingSent();
});

it('completes an unanswered campaign seven days after email three', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(40);
    $campaign = ChurnRecoveryCampaign::factory()->for($user)->create([
        'campaign_key' => SendChurnRecoveryEmails::CAMPAIGN_KEY,
        'cohort' => 'previous_reading_loggers',
        'variant' => 'days_7_14_30',
        'started_at' => now()->subDays(30),
        'observed_until' => now()->subSecond(),
        'last_touch_sent_at' => now()->subDays(7),
    ]);
    ChurnRecoveryEmailRecord::query()->create([
        'user_id' => $user->id,
        'churn_recovery_campaign_id' => $campaign->id,
        'email_number' => 3,
        'sent_at' => now()->subDays(7),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect($campaign->fresh()?->completed_at)->not->toBeNull();
});

it('allows another campaign after meaningful activity and a ninety day cooldown', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(20);
    $campaign = ChurnRecoveryCampaign::factory()->for($user)->create([
        'campaign_key' => SendChurnRecoveryEmails::CAMPAIGN_KEY,
        'cohort' => 'previous_reading_loggers',
        'variant' => 'days_7_14_30',
        'started_at' => now()->subDays(120),
        'observed_until' => now()->subDays(90),
        'completed_at' => now()->subDays(110),
        'last_touch_sent_at' => now()->subDays(100),
    ]);
    ChurnRecoveryEmailRecord::query()->create([
        'user_id' => $user->id,
        'churn_recovery_campaign_id' => $campaign->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(100),
    ]);

    foreach ([80, 79, 78] as $daysAgo) {
        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->subDays($daysAgo)->toDateString(),
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    $this->artisan('churn:send-recovery')->assertSuccessful();

    assertReengagementEmailSent($user, 1);
    expect(ChurnRecoveryCampaign::query()
        ->where('user_id', $user->id)
        ->where('campaign_key', SendChurnRecoveryEmails::CAMPAIGN_KEY)
        ->count())->toBe(2);
});

it('requires both meaningful activity and the ninety day cooldown for another campaign', function (int $emailDaysAgo, int $activityDays) {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(20);
    $campaign = ChurnRecoveryCampaign::factory()->for($user)->create([
        'campaign_key' => SendChurnRecoveryEmails::CAMPAIGN_KEY,
        'cohort' => 'previous_reading_loggers',
        'variant' => 'days_7_14_30',
        'started_at' => now()->subDays(120),
        'observed_until' => now()->subDays(90),
        'completed_at' => now()->subDays(90),
        'last_touch_sent_at' => now()->subDays($emailDaysAgo),
    ]);
    ChurnRecoveryEmailRecord::query()->create([
        'user_id' => $user->id,
        'churn_recovery_campaign_id' => $campaign->id,
        'email_number' => 1,
        'sent_at' => now()->subDays($emailDaysAgo),
    ]);

    for ($index = 0; $index < $activityDays; $index++) {
        ReadingLog::factory()->for($user)->create([
            'date_read' => now()->subDays(80 - $index)->toDateString(),
            'created_at' => now()->subDays(80 - $index),
        ]);
    }

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
})->with([
    'cooldown has not elapsed' => [80, 3],
    'insufficient meaningful activity' => [100, 1],
]);

it('archives incomplete thirty-to-sixty campaigns without sending another email', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-18 12:00:00');
    $user = reengagementUser(40);
    $campaign = ChurnRecoveryCampaign::factory()->for($user)->create([
        'campaign_key' => 'inactive_30_60_followup',
        'variant' => 'two_touch_followup',
        'started_at' => now()->subDays(3),
        'observed_until' => now()->addDays(4),
        'last_touch_sent_at' => now()->subDays(3),
    ]);
    $expiredAt = now()->subDay();
    $expiredUser = reengagementUser(40);
    $expiredUser->update(['marketing_emails_opted_out_at' => now()]);
    $expiredCampaign = ChurnRecoveryCampaign::factory()->for($expiredUser)->create([
        'campaign_key' => 'inactive_30_60_followup',
        'variant' => 'current_flow_control',
        'started_at' => now()->subDays(10),
        'observed_until' => $expiredAt,
    ]);
    ChurnRecoveryEmailRecord::query()->create([
        'user_id' => $user->id,
        'churn_recovery_campaign_id' => $campaign->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(3),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    $campaign->refresh();
    $expiredCampaign->refresh();
    expect($campaign->completed_at?->equalTo(now()))->toBeTrue()
        ->and($campaign->observed_until?->equalTo(now()))->toBeTrue()
        ->and($expiredCampaign->completed_at?->equalTo(now()))->toBeTrue()
        ->and($expiredCampaign->observed_until?->equalTo($expiredAt))->toBeTrue();
});

it('does not mutate or send during a dry run', function () {
    Mail::fake();
    $user = reengagementUser(10);

    $this->artisan('churn:send-recovery', ['--dry-run' => true])
        ->expectsOutput('1 users eligible to start reading-log re-engagement campaigns.')
        ->assertSuccessful();

    Mail::assertNothingSent();
    expect(currentReengagementCampaign($user))->toBeNull();
});

it('deletes a newly created campaign when its first email fails', function () {
    Mail::fake();
    $user = reengagementUser(10);
    $emailService = $this->mock(EmailService::class);
    $emailService->shouldReceive('sendWithErrorHandling')->once()->andReturn(false);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertNothingSent();
    expect(currentReengagementCampaign($user))->toBeNull();
});
