<?php

namespace App\Console\Commands;

use App\Mail\ChurnRecoveryEmail as ChurnRecoveryEmailMailable;
use App\Models\ChurnRecoveryEmail;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Console\Command;
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
        $sentCount = 0;
        $eligibleCount = 0;

        User::with('latestReadingLog')
            ->whereNull('marketing_emails_opted_out_at')
            ->where(function ($query) {
                $sevenDaysAgo = now()->subDays(7)->toDateString();
                $query->whereDoesntHave('readingLogs', function ($q) use ($sevenDaysAgo) {
                    $q->where('date_read', '>=', $sevenDaysAgo);
                });
            })
            ->where(function ($query) {
                $sevenDaysAgo = now()->subDays(7)->toDateString();
                $query->whereHas('readingLogs')
                    ->orWhere(function ($q) use ($sevenDaysAgo) {
                        $q->whereDoesntHave('readingLogs')
                            ->where('created_at', '<', $sevenDaysAgo);
                    });
            })
            ->chunkById(100, function ($users) use ($emailService, $dryRun, &$sentCount, &$eligibleCount) {
                // Eager load the data we need
                $userIds = $users->pluck('id');
                $emailHistories = ChurnRecoveryEmail::whereIn('user_id', $userIds)
                    ->whereNull('deleted_at')
                    ->orderBy('sent_at')
                    ->get()
                    ->groupBy('user_id');

                foreach ($users as $user) {
                    $history = $emailHistories->get($user->id, collect());
                    $emailNumber = $this->determineEmailNumber($user, $history);

                    if ($emailNumber === null) {
                        continue;
                    }

                    $eligibleCount++;

                    if ($dryRun) {
                        $this->info("Would send email {$emailNumber} to {$user->email}");

                        continue;
                    }

                    $success = $emailService->sendWithErrorHandling(function () use ($user, $emailNumber) {
                        Mail::to($user->email)->send(new ChurnRecoveryEmailMailable($user, $emailNumber, $user->latestReadingLog?->passage_text));
                    }, "churn-recovery-{$emailNumber}");

                    if ($success) {
                        $this->recordEmailSent($user->id, $emailNumber);
                        $sentCount++;
                    }
                }
            });

        if ($dryRun) {
            $this->info("{$eligibleCount} users eligible for churn recovery emails.");
        } else {
            $this->info("Sent {$sentCount} churn recovery emails.");
        }

        return self::SUCCESS;
    }

    protected function determineEmailNumber(User $user, $history): ?int
    {
        $lastEmail = $history->last(); // Use pre-loaded history

        // If no previous emails, send #1
        if (! $lastEmail) {
            return 1;
        }

        // Check for re-activation since last email
        // If user logged a reading with date_read >= sent_at, they are active again
        $sentAt = $lastEmail->sent_at; // It's already cast to datetime in Model

        // Use eager-loaded latestReadingLog to avoid N+1 query
        $latestLog = $user->latestReadingLog;
        $hasReactivated = $latestLog && $latestLog->date_read >= $sentAt->format('Y-m-d');

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
        ChurnRecoveryEmail::create([
            'user_id' => $userId,
            'email_number' => $emailNumber,
            'sent_at' => now(),
        ]);
    }
}
