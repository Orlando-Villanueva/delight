<?php

namespace App\Console\Commands;

use App\Models\AnnouncementEmailDelivery;
use App\Services\AnnouncementEmailDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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

                $result = $deliveryService->processDelivery($delivery->fresh());
                $this->info("Announcement email delivery {$delivery->id}: {$result}.");

                return self::SUCCESS;
            }

            $summary = $deliveryService->processDueBroadcasts();

            $this->info(sprintf(
                'Announcement emails processed: %d audiences finalized, %d recipients added, %d sent, %d skipped, %d retryable, %d failed, %d uncertain.',
                $summary['audiences_finalized'],
                $summary['recipients_added'],
                $summary['sent'],
                $summary['skipped'],
                $summary['retryable'],
                $summary['failed'],
                $summary['uncertain'],
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
