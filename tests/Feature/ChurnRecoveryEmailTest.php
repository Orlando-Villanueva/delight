<?php

use App\Mail\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
