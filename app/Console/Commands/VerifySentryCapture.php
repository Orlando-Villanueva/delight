<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Sentry\State\HubInterface;

class VerifySentryCapture extends Command
{
    protected $signature = 'sentry:verify-capture';

    protected $description = 'Report one synthetic exception through the configured application Sentry integration';

    public function handle(HubInterface $hub): int
    {
        $client = $hub->getClient();

        if ($client === null || $client->getOptions()->getDsn() === null) {
            $this->error('Sentry capture is disabled. Configure SENTRY_ENABLED=true and SENTRY_DSN before running this command.');

            return self::FAILURE;
        }

        report(new RuntimeException('Synthetic Sentry capture verification'));

        $eventId = $hub->getLastEventId();
        $client->flush();

        if ($eventId === null) {
            $this->error('The SDK did not return an event ID. Verify the configured reporting integration.');

            return self::FAILURE;
        }

        $this->info("SDK event ID: {$eventId}");
        $this->line('Confirm this event in Sentry to verify delivery, sanitized payload, environment, and release.');
        $this->line('Run this command again to verify both events group into the same issue.');

        return self::SUCCESS;
    }
}
