<?php

namespace App\Console\Commands;

use App\Mail\ChurnRecoveryEmail as ChurnRecoveryEmailMailable;
use App\Models\ChurnRecoveryCampaign;
use App\Models\ChurnRecoveryEmail;
use App\Models\User;
use App\Services\EmailService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendChurnRecoveryEmails extends Command
{
    private const THIRTY_TO_SIXTY_CAMPAIGN_KEY = 'inactive_30_60_followup';

    private const THIRTY_TO_SIXTY_COHORT = 'inactive_30_60_days';

    private const THIRTY_TO_SIXTY_VARIANT_CONTROL = 'current_flow_control';

    private const THIRTY_TO_SIXTY_VARIANT_FOLLOWUP = 'two_touch_followup';

    protected $signature = 'churn:send-recovery
        {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send churn recovery emails to inactive users';

    public function handle(EmailService $emailService): int
    {
        $dryRun = $this->option('dry-run');
        $legacyEligibleCount = 0;
        $legacySentCount = 0;
        $followUpEligibleCount = 0;
        $followUpSentCount = 0;
        $controlAssignments = 0;

        if (! $dryRun) {
            $this->completeObservedThirtyToSixtyCampaigns();
        }
        $this->sendLegacyRecoveryEmails($emailService, $dryRun, $legacyEligibleCount, $legacySentCount);
        $this->startThirtyToSixtyCampaigns($emailService, $dryRun, $followUpEligibleCount, $followUpSentCount, $controlAssignments);
        $this->sendThirtyToSixtySecondTouches($emailService, $dryRun, $followUpSentCount);

        if ($dryRun) {
            $this->info("{$legacyEligibleCount} users eligible for legacy churn recovery emails.");
            $this->info("{$followUpEligibleCount} users eligible for 30-60 day follow-up campaigns.");
        } else {
            $this->info("Sent {$legacySentCount} legacy churn recovery emails.");
            $this->info("Sent {$followUpSentCount} 30-60 day follow-up emails.");
            $this->info("Assigned {$controlAssignments} users to the 30-60 day control path.");
        }

        return self::SUCCESS;
    }

    protected function determineEmailNumber(User $user, EloquentCollection $history): ?int
    {
        $lastEmail = $history->last();
        $emailNumber = 1;

        if ($lastEmail) {
            $sentAt = $lastEmail->sent_at;
            $latestLog = $user->latestReadingLog;
            $hasReactivated = $latestLog && $latestLog->date_read >= $sentAt->format('Y-m-d');
            $daysSinceLast = $sentAt->diffInDays(now());

            if ($hasReactivated || $daysSinceLast < 7) {
                $emailNumber = null;
            } else {
                $emailNumber = match ($lastEmail->email_number) {
                    1 => 2,
                    2 => 3,
                    default => null,
                };
            }
        }

        return $emailNumber;
    }

    protected function recordEmailSent(int $userId, int $emailNumber, ?int $campaignId = null): void
    {
        ChurnRecoveryEmail::create([
            'user_id' => $userId,
            'churn_recovery_campaign_id' => $campaignId,
            'email_number' => $emailNumber,
            'sent_at' => now(),
        ]);
    }

    protected function sendLegacyRecoveryEmails(
        EmailService $emailService,
        bool $dryRun,
        int &$eligibleCount,
        int &$sentCount
    ): void {
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
            ->whereDoesntHave('churnRecoveryCampaigns', function ($query) {
                $query->where('campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY)
                    ->whereNull('completed_at');
            })
            ->whereDoesntHave('churnRecoveryEmails', function ($q) {
                $q->where('sent_at', '>=', now()->subDays(6));
            })
            ->chunkById(100, function ($users) use ($emailService, $dryRun, &$sentCount, &$eligibleCount) {
                $userIds = $users->pluck('id');
                $emailHistories = ChurnRecoveryEmail::whereIn('user_id', $userIds)
                    ->whereNull('churn_recovery_campaign_id')
                    ->orderBy('sent_at')
                    ->get()
                    ->groupBy('user_id');

                foreach ($users as $user) {
                    $this->processLegacyRecoveryUser($user, $emailHistories, $emailService, $dryRun, $eligibleCount, $sentCount);
                }
            });
    }

    protected function processLegacyRecoveryUser(
        User $user,
        Collection $emailHistories,
        EmailService $emailService,
        bool $dryRun,
        int &$eligibleCount,
        int &$sentCount
    ): void {
        $history = $emailHistories->get($user->id, new EloquentCollection);
        $emailNumber = $this->determineEmailNumber($user, $history);

        if ($emailNumber === null) {
            return;
        }

        $eligibleCount++;

        if ($dryRun) {
            $this->info("Would send legacy email {$emailNumber} to {$user->email}");

            return;
        }

        $this->sendLegacyRecoveryEmail($user, $emailNumber, $emailService, $sentCount);
    }

    protected function sendLegacyRecoveryEmail(
        User $user,
        int $emailNumber,
        EmailService $emailService,
        int &$sentCount
    ): void {
        $lock = Cache::lock('churn-email-'.$user->id, 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $alreadySent = ChurnRecoveryEmail::where('user_id', $user->id)
                ->whereNull('churn_recovery_campaign_id')
                ->where('email_number', $emailNumber)
                ->exists();

            if ($alreadySent) {
                return;
            }

            $success = $emailService->sendWithErrorHandling(function () use ($user, $emailNumber) {
                Mail::to($user->email)->send(
                    new ChurnRecoveryEmailMailable($user, $emailNumber, $user->latestReadingLog?->passage_text)
                );
            }, "churn-recovery-{$emailNumber}");

            if ($success) {
                $this->recordEmailSent($user->id, $emailNumber);
                $sentCount++;
            }
        } finally {
            $lock->release();
        }
    }

    protected function startThirtyToSixtyCampaigns(
        EmailService $emailService,
        bool $dryRun,
        int &$eligibleCount,
        int &$sentCount,
        int &$controlAssignments
    ): void {
        $startDate = now()->subDays(60)->toDateString();
        $endDate = now()->subDays(30)->toDateString();

        User::with('latestReadingLog')
            ->whereNull('marketing_emails_opted_out_at')
            ->whereHas('latestReadingLog', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date_read', [$startDate, $endDate]);
            })
            ->whereDoesntHave('churnRecoveryEmails', function ($query) {
                $query->where('sent_at', '>=', now()->subDays(6));
            })
            ->whereDoesntHave('churnRecoveryCampaigns', function ($query) {
                $query->where('campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY);
            })
            ->chunkById(100, function ($users) use ($emailService, $dryRun, &$eligibleCount, &$sentCount, &$controlAssignments) {
                foreach ($users as $user) {
                    $this->processThirtyToSixtyCampaignUser(
                        $user,
                        $emailService,
                        $dryRun,
                        $eligibleCount,
                        $sentCount,
                        $controlAssignments
                    );
                }
            });
    }

    protected function processThirtyToSixtyCampaignUser(
        User $user,
        EmailService $emailService,
        bool $dryRun,
        int &$eligibleCount,
        int &$sentCount,
        int &$controlAssignments
    ): void {
        if (! $this->isThirtyToSixtyInactive($user)) {
            return;
        }

        $eligibleCount++;
        $variant = $this->determineThirtyToSixtyVariant($user);

        if ($dryRun) {
            $this->info("Would start {$variant} 30-60 day campaign for {$user->email}");

            return;
        }

        $this->startThirtyToSixtyCampaignForUser($user, $variant, $emailService, $sentCount, $controlAssignments);
    }

    protected function startThirtyToSixtyCampaignForUser(
        User $user,
        string $variant,
        EmailService $emailService,
        int &$sentCount,
        int &$controlAssignments
    ): void {
        $lock = Cache::lock('churn-30-60-campaign-'.$user->id, 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $alreadyAssigned = ChurnRecoveryCampaign::query()
                ->where('user_id', $user->id)
                ->where('campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY)
                ->exists();

            if (! $alreadyAssigned) {
                $campaign = ChurnRecoveryCampaign::create([
                    'user_id' => $user->id,
                    'campaign_key' => self::THIRTY_TO_SIXTY_CAMPAIGN_KEY,
                    'cohort' => self::THIRTY_TO_SIXTY_COHORT,
                    'variant' => $variant,
                    'started_at' => now(),
                    'observed_until' => now()->addDays(7),
                ]);

                $isControlVariant = $variant === self::THIRTY_TO_SIXTY_VARIANT_CONTROL;

                if ($isControlVariant) {
                    $controlAssignments++;
                } else {
                    $success = $emailService->sendWithErrorHandling(function () use ($user) {
                        Mail::to($user->email)->send(
                            new ChurnRecoveryEmailMailable(
                                $user,
                                1,
                                $user->latestReadingLog?->passage_text,
                                ChurnRecoveryEmailMailable::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP
                            )
                        );
                    }, 'churn-recovery-30-60-touch-1');

                    if (! $success) {
                        $campaign->delete();
                    } else {
                        $campaign->forceFill([
                            'last_touch_sent_at' => now(),
                        ])->save();

                        $this->recordEmailSent($user->id, 1, $campaign->id);
                        $sentCount++;
                    }
                }
            }
        } finally {
            $lock->release();
        }
    }

    protected function sendThirtyToSixtySecondTouches(
        EmailService $emailService,
        bool $dryRun,
        int &$sentCount
    ): void {
        ChurnRecoveryCampaign::query()
            ->with(['user.latestReadingLog', 'emails'])
            ->where('campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY)
            ->where('variant', self::THIRTY_TO_SIXTY_VARIANT_FOLLOWUP)
            ->whereNull('completed_at')
            ->whereNull('reactivated_at')
            ->whereNotNull('last_touch_sent_at')
            ->where('last_touch_sent_at', '<=', now()->subDays(3))
            ->chunkById(100, function ($campaigns) use ($emailService, $dryRun, &$sentCount) {
                foreach ($campaigns as $campaign) {
                    $this->processThirtyToSixtySecondTouchCampaign($campaign, $emailService, $dryRun, $sentCount);
                }
            });
    }

    protected function processThirtyToSixtySecondTouchCampaign(
        ChurnRecoveryCampaign $campaign,
        EmailService $emailService,
        bool $dryRun,
        int &$sentCount
    ): void {
        $user = $campaign->user;

        if (! $user instanceof User || $this->campaignHasSecondTouch($campaign)) {
            return;
        }

        if ($dryRun) {
            if (! $this->shouldCompleteThirtyToSixtyCampaignWithoutSecondTouch($campaign, $user)) {
                $this->info("Would send 30-60 follow-up touch 2 to {$user->email}");
            }

            return;
        }

        if ($this->shouldCompleteThirtyToSixtyCampaignWithoutSecondTouch($campaign, $user)) {
            $this->completeThirtyToSixtyCampaignWithoutSecondTouch($campaign, $user);

            return;
        }

        $this->sendThirtyToSixtySecondTouch($campaign, $user, $emailService, $sentCount);
    }

    protected function campaignHasSecondTouch(ChurnRecoveryCampaign $campaign): bool
    {
        return $campaign->emails->contains(fn (ChurnRecoveryEmail $email) => $email->email_number === 2);
    }

    protected function shouldCompleteThirtyToSixtyCampaignWithoutSecondTouch(
        ChurnRecoveryCampaign $campaign,
        User $user
    ): bool {
        return $user->marketing_emails_opted_out_at !== null
            || $this->hasReactivatedSince($user, $campaign->started_at);
    }

    protected function completeThirtyToSixtyCampaignWithoutSecondTouch(
        ChurnRecoveryCampaign $campaign,
        User $user
    ): void {
        $reactivated = $this->hasReactivatedSince($user, $campaign->started_at);

        $campaign->forceFill([
            'reactivated_at' => $reactivated ? now() : $campaign->reactivated_at,
            'completed_at' => now(),
        ])->save();
    }

    protected function sendThirtyToSixtySecondTouch(
        ChurnRecoveryCampaign $campaign,
        User $user,
        EmailService $emailService,
        int &$sentCount
    ): void {
        $lock = Cache::lock('churn-30-60-touch-2-'.$user->id, 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $campaign = ChurnRecoveryCampaign::query()
                ->with(['user.latestReadingLog', 'emails'])
                ->find($campaign->id);

            if (! $campaign instanceof ChurnRecoveryCampaign) {
                return;
            }

            $user = $campaign->user;

            if (! $user instanceof User
                || $campaign->completed_at !== null
                || $campaign->reactivated_at !== null
                || $this->campaignHasSecondTouch($campaign)) {
                return;
            }

            if ($this->shouldCompleteThirtyToSixtyCampaignWithoutSecondTouch($campaign, $user)) {
                $this->completeThirtyToSixtyCampaignWithoutSecondTouch($campaign, $user);

                return;
            }

            $success = $emailService->sendWithErrorHandling(function () use ($user) {
                Mail::to($user->email)->send(
                    new ChurnRecoveryEmailMailable(
                        $user,
                        2,
                        $user->latestReadingLog?->passage_text,
                        ChurnRecoveryEmailMailable::SEQUENCE_THIRTY_TO_SIXTY_FOLLOWUP
                    )
                );
            }, 'churn-recovery-30-60-touch-2');

            if ($success) {
                $campaign->forceFill([
                    'last_touch_sent_at' => now(),
                    'completed_at' => now(),
                ])->save();

                $this->recordEmailSent($user->id, 2, $campaign->id);
                $sentCount++;
            }
        } finally {
            $lock->release();
        }
    }

    protected function completeObservedThirtyToSixtyCampaigns(): void
    {
        ChurnRecoveryCampaign::query()
            ->where('campaign_key', self::THIRTY_TO_SIXTY_CAMPAIGN_KEY)
            ->where('variant', self::THIRTY_TO_SIXTY_VARIANT_CONTROL)
            ->whereNull('completed_at')
            ->where('observed_until', '<=', now())
            ->update([
                'completed_at' => now(),
            ]);
    }

    protected function determineThirtyToSixtyVariant(User $user): string
    {
        return $user->id % 2 === 1
            ? self::THIRTY_TO_SIXTY_VARIANT_FOLLOWUP
            : self::THIRTY_TO_SIXTY_VARIANT_CONTROL;
    }

    protected function isThirtyToSixtyInactive(User $user): bool
    {
        $latestLog = $user->latestReadingLog;

        if ($latestLog === null) {
            return false;
        }

        $lastReadDate = $latestLog->date_read;

        return $lastReadDate >= now()->subDays(60)->toDateString()
            && $lastReadDate <= now()->subDays(30)->toDateString();
    }

    protected function hasReactivatedSince(User $user, CarbonInterface $threshold): bool
    {
        $latestLog = $user->latestReadingLog;

        if ($latestLog === null) {
            return false;
        }

        return $latestLog->date_read >= $threshold->toDateString();
    }
}
