<?php

use App\Mail\ChurnRecoveryEmail;
use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

test('command finds users inactive for 30+ days', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(31)->format('Y-m-d'),
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

test('command sends email 1 to eligible inactive users', function () {
    Mail::fake();

    $user = User::factory()->create();
    ReadingLog::factory()->create([
        'user_id' => $user->id,
        'date_read' => now()->subDays(31)->format('Y-m-d'),
    ]);

    $this->artisan('churn:send-recovery')->assertSuccessful();

    Mail::assertSent(ChurnRecoveryEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && $mail->emailNumber === 1;
    });

    $this->assertDatabaseHas('churn_recovery_emails', [
        'user_id' => $user->id,
        'email_number' => 1,
    ]);
});
