<?php

namespace App\Console\Commands;

use App\Models\AnnouncementEmailDelivery;
use App\Services\AnnouncementEmailDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendPublishedAnnouncementEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:send-published-emails
        {--retry-delivery= : Retry one terminally failed delivery}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due announcement emails and retry transient delivery failures';

    /**
     * Execute the console command.
     */
    public function handle(AnnouncementEmailDeliveryService $deliveryService): int
    {
        $lock = Cache::lock('send-published-announcement-emails', 1200);

        if (! $lock->get()) {
            $this->info('Announcement email processing is already running.');

            return self::SUCCESS;
        }

        try {
            $startedAt = hrtime(true);
            $retryDeliveryId = $this->option('retry-delivery');

            if ($retryDeliveryId !== null) {
                if (! ctype_digit((string) $retryDeliveryId)) {
                    $this->error('The retry delivery ID must be a positive integer.');

                    return self::FAILURE;
                }

                $delivery = AnnouncementEmailDelivery::query()->find((int) $retryDeliveryId);

                if (! $delivery || ! $deliveryService->retryFailedDelivery($delivery)) {
                    $this->error('Only an existing terminally failed delivery can be retried.');

                    return self::FAILURE;
                }

                $refreshedDelivery = $delivery->fresh();

                if (! $refreshedDelivery) {
                    $this->error('The announcement email delivery no longer exists.');

                    return self::FAILURE;
                }

                $result = $deliveryService->processDelivery($refreshedDelivery);
                $deliveryService->completeFinishedBroadcasts();
                $durationMilliseconds = $this->durationMilliseconds($startedAt);
                $this->info("Announcement email delivery {$delivery->id}: {$result}.");

                Log::info('Announcement email emergency retry completed.', [
                    'delivery_id' => $delivery->id,
                    'announcement_id' => $delivery->announcement_id,
                    'result' => $result,
                    'duration_ms' => $durationMilliseconds,
                ]);

                return self::SUCCESS;
            }

            $summary = $deliveryService->processDueBroadcasts();
            $durationMilliseconds = $this->durationMilliseconds($startedAt);
            $pendingCount = $this->pendingDeliveryCount();

            $this->info(sprintf(
                'Announcement emails processed in %d ms: %d audiences finalized, %d recipients added, %d sent, %d skipped, %d retryable, %d failed, %d uncertain, %d broadcasts completed, %d pending.',
                $durationMilliseconds,
                $summary['audiences_finalized'],
                $summary['recipients_added'],
                $summary['sent'],
                $summary['skipped'],
                $summary['retryable'],
                $summary['failed'],
                $summary['uncertain'],
                $summary['broadcasts_completed'],
                $pendingCount,
            ));

            if ($this->summaryHasActivity($summary)) {
                Log::info('Announcement email processing run completed.', [
                    'duration_ms' => $durationMilliseconds,
                    ...$summary,
                    'pending_count' => $pendingCount,
                ]);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function durationMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function pendingDeliveryCount(): int
    {
        return AnnouncementEmailDelivery::query()
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->whereNull('uncertain_at')
            ->count();
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function summaryHasActivity(array $summary): bool
    {
        return array_sum($summary) > 0;
    }
}
