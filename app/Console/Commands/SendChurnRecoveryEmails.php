<?php

namespace App\Console\Commands;

use App\Mail\ChurnRecoveryEmail as ChurnRecoveryEmailMailable;
use App\Models\ChurnRecoveryCampaign;
use App\Models\ChurnRecoveryEmail;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendChurnRecoveryEmails extends Command
{
    public const CAMPAIGN_KEY = 'reading_log_reengagement_v1';

    private const ARCHIVED_CAMPAIGN_KEY = 'inactive_30_60_followup';

    private const COHORT = 'previous_reading_loggers';

    private const VARIANT = 'days_7_14_30';

    private const REPEAT_COOLDOWN_DAYS = 90;

    protected $signature = 'churn:send-recovery
        {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send reading-log re-engagement emails to inactive users';

    public function handle(EmailService $emailService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sentCount = 0;
        $eligibleCount = 0;

        if (! $dryRun) {
            $this->archiveIncompleteLegacyExperiments();
            $this->completeExpiredCampaigns();
        }

        $this->processActiveCampaigns($emailService, $dryRun, $sentCount);
        $this->startEligibleCampaigns($emailService, $dryRun, $eligibleCount, $sentCount);

        if ($dryRun) {
            $this->info("{$eligibleCount} users eligible to start reading-log re-engagement campaigns.");
        } else {
            $this->info("Sent {$sentCount} reading-log re-engagement emails.");
        }

        return self::SUCCESS;
    }

    private function archiveIncompleteLegacyExperiments(): void
    {
        ChurnRecoveryCampaign::query()
            ->where('campaign_key', self::ARCHIVED_CAMPAIGN_KEY)
            ->whereNull('completed_at')
            ->update([
                'completed_at' => now(),
            ]);
    }

    private function completeExpiredCampaigns(): void
    {
        ChurnRecoveryCampaign::query()
            ->where('campaign_key', self::CAMPAIGN_KEY)
            ->whereNull('completed_at')
            ->where('observed_until', '<', now())
            ->update([
                'completed_at' => now(),
            ]);
    }

    private function processActiveCampaigns(
        EmailService $emailService,
        bool $dryRun,
        int &$sentCount
    ): void {
        ChurnRecoveryCampaign::query()
            ->with(['user.latestReadingLog', 'emails'])
            ->where('campaign_key', self::CAMPAIGN_KEY)
            ->whereNull('completed_at')
            ->chunkById(100, function (EloquentCollection $campaigns) use ($emailService, $dryRun, &$sentCount): void {
                foreach ($campaigns as $campaign) {
                    $this->processActiveCampaign($campaign, $emailService, $dryRun, $sentCount);
                }
            });
    }

    private function processActiveCampaign(
        ChurnRecoveryCampaign $campaign,
        EmailService $emailService,
        bool $dryRun,
        int &$sentCount
    ): void {
        $user = $campaign->user;

        if (! $user instanceof User) {
            return;
        }

        if ($this->hasReactivatedSince($user, $campaign)) {
            if (! $dryRun) {
                $this->completeReactivatedCampaign($campaign);
            }

            return;
        }

        if ($user->marketing_emails_opted_out_at !== null) {
            if (! $dryRun) {
                $campaign->forceFill(['completed_at' => now()])->save();
            }

            return;
        }

        $emailNumber = $this->nextDueEmailNumber($campaign, $user);

        if ($emailNumber === null) {
            return;
        }

        if ($dryRun) {
            $this->info("Would send reading-log re-engagement email {$emailNumber} to {$user->email}");

            return;
        }

        $this->sendCampaignEmail($campaign, $user, $emailNumber, $emailService, $sentCount);
    }

    private function nextDueEmailNumber(ChurnRecoveryCampaign $campaign, User $user): ?int
    {
        $lastEmail = $campaign->emails->sortBy('email_number')->last();

        if (! $lastEmail instanceof ChurnRecoveryEmail) {
            return 1;
        }

        $inactiveDays = $this->inactiveDays($user);

        return match ($lastEmail->email_number) {
            1 => $inactiveDays >= 14 && $lastEmail->sent_at->lte(now()->subDays(7)) ? 2 : null,
            2 => $inactiveDays >= 30 && $lastEmail->sent_at->lte(now()->subDays(16)) ? 3 : null,
            default => null,
        };
    }

    private function startEligibleCampaigns(
        EmailService $emailService,
        bool $dryRun,
        int &$eligibleCount,
        int &$sentCount
    ): void {
        User::query()
            ->with('latestReadingLog')
            ->whereNull('marketing_emails_opted_out_at')
            ->whereHas('readingLogs')
            ->whereDoesntHave('readingLogs', function ($query): void {
                $query->whereDate('date_read', '>=', now()->subDays(6)->toDateString());
            })
            ->whereDoesntHave('churnRecoveryCampaigns', function ($query): void {
                $query->where('campaign_key', self::CAMPAIGN_KEY)
                    ->whereNull('completed_at');
            })
            ->chunkById(100, function (EloquentCollection $users) use ($emailService, $dryRun, &$eligibleCount, &$sentCount): void {
                foreach ($users as $user) {
                    if (! $this->canStartCampaign($user)) {
                        continue;
                    }

                    $eligibleCount++;

                    if ($dryRun) {
                        $this->info("Would start reading-log re-engagement campaign for {$user->email}");

                        continue;
                    }

                    $this->startCampaign($user, $emailService, $sentCount);
                }
            });
    }

    private function canStartCampaign(User $user): bool
    {
        $lastCampaign = ChurnRecoveryCampaign::query()
            ->where('user_id', $user->id)
            ->where('campaign_key', self::CAMPAIGN_KEY)
            ->latest('started_at')
            ->first();

        $lastRecoveryEmail = ChurnRecoveryEmail::query()
            ->where('user_id', $user->id)
            ->latest('sent_at')
            ->first();

        if (! $lastRecoveryEmail instanceof ChurnRecoveryEmail) {
            return true;
        }

        if ($lastRecoveryEmail->sent_at->gt(now()->subDays(self::REPEAT_COOLDOWN_DAYS))) {
            return false;
        }

        $activityThreshold = $lastCampaign?->started_at ?? $lastRecoveryEmail->sent_at;

        return $user->readingLogs()
            ->where('created_at', '>', $activityThreshold)
            ->distinct('date_read')
            ->count('date_read') >= 3;
    }

    private function startCampaign(User $user, EmailService $emailService, int &$sentCount): void
    {
        $lock = Cache::lock('reading-log-reengagement-start-'.$user->id, 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $hasActiveCampaign = ChurnRecoveryCampaign::query()
                ->where('user_id', $user->id)
                ->where('campaign_key', self::CAMPAIGN_KEY)
                ->whereNull('completed_at')
                ->exists();

            if ($hasActiveCampaign || ! $this->canStartCampaign($user)) {
                return;
            }

            $campaign = ChurnRecoveryCampaign::query()->create([
                'user_id' => $user->id,
                'campaign_key' => self::CAMPAIGN_KEY,
                'cohort' => self::COHORT,
                'variant' => self::VARIANT,
                'started_at' => now(),
                'observed_until' => now()->addDays(30),
            ]);

            if (! $this->sendCampaignEmail($campaign, $user, 1, $emailService, $sentCount)) {
                $campaign->delete();
            }
        } finally {
            $lock->release();
        }
    }

    private function sendCampaignEmail(
        ChurnRecoveryCampaign $campaign,
        User $user,
        int $emailNumber,
        EmailService $emailService,
        int &$sentCount
    ): bool {
        $lock = Cache::lock("reading-log-reengagement-{$campaign->id}-{$emailNumber}", 30);

        if (! $lock->get()) {
            return false;
        }

        try {
            $alreadySent = ChurnRecoveryEmail::query()
                ->where('churn_recovery_campaign_id', $campaign->id)
                ->where('email_number', $emailNumber)
                ->exists();

            if ($alreadySent) {
                return true;
            }

            $success = $emailService->sendWithErrorHandling(function () use ($user, $emailNumber): void {
                Mail::to($user->email)->send(
                    new ChurnRecoveryEmailMailable($user, $emailNumber, $user->latestReadingLog?->passage_text)
                );
            }, "reading-log-reengagement-{$emailNumber}");

            if (! $success) {
                return false;
            }

            ChurnRecoveryEmail::query()->create([
                'user_id' => $user->id,
                'churn_recovery_campaign_id' => $campaign->id,
                'email_number' => $emailNumber,
                'sent_at' => now(),
            ]);

            $campaign->forceFill([
                'last_touch_sent_at' => now(),
                'observed_until' => $emailNumber === 3 ? now()->addDays(7) : $campaign->observed_until,
            ])->save();

            $sentCount++;

            return true;
        } finally {
            $lock->release();
        }
    }

    private function inactiveDays(User $user): int
    {
        return $user->latestReadingLog?->date_read
            ? now()->startOfDay()->diffInDays($user->latestReadingLog->date_read, true)
            : 0;
    }

    private function hasReactivatedSince(User $user, ChurnRecoveryCampaign $campaign): bool
    {
        return $user->readingLogs()
            ->where('created_at', '>=', $campaign->started_at)
            ->exists();
    }

    private function completeReactivatedCampaign(ChurnRecoveryCampaign $campaign): void
    {
        $campaign->forceFill([
            'reactivated_at' => now(),
            'completed_at' => now(),
        ])->save();
    }
}
