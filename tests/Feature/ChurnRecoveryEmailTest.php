<?php

use App\Mail\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
    $user = User::factory()->create();
    // Seed existing churn emails (sent 100 days ago)
    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(100),
    ]);

    $service = app(\App\Services\ReadingLogService::class);

    // 1st day reading
    $service->logReading($user, ['book_id' => 1, 'chapter' => 1, 'date_read' => now()->subDays(1)->toDateString()]);

    // Verify NOT soft deleted
    expect(DB::table('churn_recovery_emails')->where('user_id', $user->id)->whereNull('deleted_at')->exists())->toBeTrue();

    // 2nd day reading
    $service->logReading($user, ['book_id' => 1, 'chapter' => 2, 'date_read' => now()->toDateString()]);

    // Verify NOT soft deleted (only 2 days)
    expect(DB::table('churn_recovery_emails')->where('user_id', $user->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

test('churn recovery reset occurs after 3 days of activity', function () {
    $user = User::factory()->create();
    // Seed existing churn emails
    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(100),
    ]);

    $service = app(\App\Services\ReadingLogService::class);

    // 1st day
    $service->logReading($user, ['book_id' => 1, 'chapter' => 1, 'date_read' => now()->subDays(2)->toDateString()]);
    // 2nd day
    $service->logReading($user, ['book_id' => 1, 'chapter' => 2, 'date_read' => now()->subDays(1)->toDateString()]);
    // 3rd day - should trigger reset
    $service->logReading($user, ['book_id' => 1, 'chapter' => 3, 'date_read' => now()->toDateString()]);

    // Verify soft deleted
    expect(DB::table('churn_recovery_emails')->where('user_id', $user->id)->whereNull('deleted_at')->exists())->toBeFalse();
    expect(DB::table('churn_recovery_emails')->where('user_id', $user->id)->whereNotNull('deleted_at')->exists())->toBeTrue();
});

test('churn recovery reset respects 90 day cooldown', function () {
    $user = User::factory()->create();
    // Seed existing churn emails (sent 80 days ago - too soon)
    DB::table('churn_recovery_emails')->insert([
        'user_id' => $user->id,
        'email_number' => 1,
        'sent_at' => now()->subDays(80),
    ]);

    $service = app(\App\Services\ReadingLogService::class);

    // 3 days of reading
    $service->logReading($user, ['book_id' => 1, 'chapter' => 1, 'date_read' => now()->subDays(2)->toDateString()]);
    $service->logReading($user, ['book_id' => 1, 'chapter' => 2, 'date_read' => now()->subDays(1)->toDateString()]);
    $service->logReading($user, ['book_id' => 1, 'chapter' => 3, 'date_read' => now()->toDateString()]);

    // Verify NOT soft deleted because of cooldown
    expect(DB::table('churn_recovery_emails')->where('user_id', $user->id)->whereNull('deleted_at')->exists())->toBeTrue();
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
