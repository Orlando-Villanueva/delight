<?php

namespace App\Console\Commands;

use App\Mail\ChurnRecoveryEmail;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendChurnRecoveryEmails extends Command
{
    protected $signature = 'churn:send-recovery 
        {--dry-run : Show what would be sent without actually sending}
        {--force : Send even if already sent today}';

    protected $description = 'Send churn recovery emails to inactive users';

    public function handle(EmailService $emailService): int
    {
        $dryRun = $this->option('dry-run');

        // Find potentially eligible users (inactive > 30 days)
        $candidates = $this->getEligibleUsers();

        // Filter users who are actually due for an email
        $eligibleToSend = $candidates->filter(function ($user) {
            $emailNumber = $this->determineEmailNumber($user);
            if ($emailNumber !== null) {
                $user->nextEmailNumber = $emailNumber;

                return true;
            }

            return false;
        });

        if ($eligibleToSend->isEmpty()) {
            $this->info('0 users eligible for churn recovery emails.');

            return self::SUCCESS;
        }

        $this->info("{$eligibleToSend->count()} users eligible for churn recovery emails.");

        // Setup progress bar
        $bar = $this->output->createProgressBar($eligibleToSend->count());
        if (! $dryRun) {
            $bar->start();
        }

        $sentCount = 0;

        foreach ($eligibleToSend as $user) {
            $emailNumber = $user->nextEmailNumber;

            if ($dryRun) {
                $this->info("Would send email {$emailNumber} to {$user->email}");

                continue;
            }

            $success = $emailService->sendWithErrorHandling(function () use ($user, $emailNumber) {
                Mail::to($user->email)->send(new ChurnRecoveryEmail($user, $emailNumber));
            }, "churn-recovery-{$emailNumber}");

            if ($success) {
                $this->recordEmailSent($user->id, $emailNumber);
                $sentCount++;
            }

            $bar->advance();
        }

        if (! $dryRun) {
            $bar->finish();
            $this->newLine();
            $this->info("Sent {$sentCount} churn recovery emails.");
        }

        return self::SUCCESS;
    }

    protected function getEligibleUsers()
    {
        $thirtyDaysAgo = now()->subDays(30)->toDateString();

        return User::whereNull('marketing_emails_opted_out_at')
            ->where(function ($query) use ($thirtyDaysAgo) {
                // User has NO reading logs in the last 30 days
                $query->whereDoesntHave('readingLogs', function ($q) use ($thirtyDaysAgo) {
                    $q->where('date_read', '>=', $thirtyDaysAgo);
                });
            })
            ->where(function ($query) use ($thirtyDaysAgo) {
                // Must either have read before (but not recently)
                $query->whereHas('readingLogs')
                    // OR have never read but account is old enough
                    ->orWhere(function ($q) use ($thirtyDaysAgo) {
                        $q->whereDoesntHave('readingLogs')
                            ->where('created_at', '<', $thirtyDaysAgo);
                    });
            })
            ->get();
    }

    protected function determineEmailNumber(User $user): ?int
    {
        // Get email history
        $lastEmail = DB::table('churn_recovery_emails')
            ->where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->first();

        // If no previous emails, send #1
        if (! $lastEmail) {
            return 1;
        }

        // Check for re-activation since last email
        // If user logged a reading with date_read > sent_at, they are active again
        $sentAt = Carbon::parse($lastEmail->sent_at);
        $hasReactivated = $user->readingLogs()
            ->where('date_read', '>', $sentAt->format('Y-m-d'))
            ->exists();

        if ($hasReactivated) {
            return null;
        }

        // Check timing (7 days gap)
        $daysSinceLast = $sentAt->diffInDays(now());

        if ($daysSinceLast < 7) {
            return null;
        }

        $lastNumber = $lastEmail->email_number;

        if ($lastNumber === 1) {
            return 2;
        }

        if ($lastNumber === 2) {
            return 3;
        }

        // Sequence complete
        return null;
    }

    protected function recordEmailSent(int $userId, int $emailNumber): void
    {
        DB::table('churn_recovery_emails')->insert([
            'user_id' => $userId,
            'email_number' => $emailNumber,
            'sent_at' => now(),
        ]);
    }
}
