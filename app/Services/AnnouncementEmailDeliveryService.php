<?php

namespace App\Services;

use App\Enums\AnnouncementEmailFailureDisposition;
use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface as MailerTransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface as HttpClientTransportException;
use Throwable;

class AnnouncementEmailDeliveryService
{
    private const int MAX_DELIVERIES_PER_RUN = 100;

    private const int MAX_AUTOMATIC_ATTEMPTS = 2;

    private const int RETRY_DELAY_MINUTES = 5;

    private const int STALE_SENDING_MINUTES = 15;

    /**
     * @return array{
     *     audiences_finalized: int,
     *     recipients_added: int,
     *     sent: int,
     *     skipped: int,
     *     retryable: int,
     *     failed: int,
     *     uncertain: int
     * }
     */
    public function processDueBroadcasts(): array
    {
        $summary = [
            'audiences_finalized' => 0,
            'recipients_added' => 0,
            'sent' => 0,
            'skipped' => 0,
            'retryable' => 0,
            'failed' => 0,
            'uncertain' => $this->markInterruptedDeliveriesUncertain(),
        ];

        Announcement::query()
            ->whereNotNull('email_broadcast_authorized_at')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->whereNull('email_audience_finalized_at')
            ->orderBy('id')
            ->each(function (Announcement $announcement) use (&$summary): void {
                $summary['recipients_added'] += $this->finalizeAudience($announcement);
                $summary['audiences_finalized']++;
            });

        AnnouncementEmailDelivery::query()
            ->with(['announcement', 'user'])
            ->whereNull('sending_at')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->whereNull('uncertain_at')
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(self::MAX_DELIVERIES_PER_RUN)
            ->get()
            ->each(function (AnnouncementEmailDelivery $delivery) use (&$summary): void {
                $result = $this->processDelivery($delivery);

                if (array_key_exists($result, $summary)) {
                    $summary[$result]++;
                }
            });

        return $summary;
    }

    public function processDelivery(AnnouncementEmailDelivery $delivery): string
    {
        $delivery->loadMissing(['announcement', 'user']);

        if ($delivery->sent_at || $delivery->skipped_at || $delivery->failed_at || $delivery->uncertain_at) {
            return 'ignored';
        }

        if (! $delivery->announcement || ! $delivery->user) {
            return 'ignored';
        }

        if ($delivery->user->marketing_emails_opted_out_at !== null) {
            $delivery->forceFill([
                'skipped_at' => now(),
                'next_attempt_at' => null,
                'failure_reason' => 'Recipient opted out before delivery.',
            ])->save();

            return 'skipped';
        }

        $messageId = $delivery->message_id ?: $this->messageIdFor($delivery);
        $claimed = AnnouncementEmailDelivery::query()
            ->whereKey($delivery->id)
            ->whereNull('sending_at')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->whereNull('uncertain_at')
            ->update([
                'attempt_count' => DB::raw('attempt_count + 1'),
                'message_id' => $messageId,
                'sending_at' => now(),
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return 'ignored';
        }

        $delivery->refresh()->loadMissing(['announcement', 'user']);

        try {
            $sentMessage = Mail::to($delivery->recipient_email)->send(
                new AnnouncementEmail($delivery->announcement, $delivery->user, $delivery)
            );
        } catch (MailerTransportException $exception) {
            return $this->recordFailure($delivery, $exception);
        }

        $delivery->forceFill([
            'provider_message_id' => $sentMessage?->getMessageId(),
            'sending_at' => null,
            'next_attempt_at' => null,
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        return 'sent';
    }

    public function retryFailedDelivery(AnnouncementEmailDelivery $delivery): bool
    {
        return AnnouncementEmailDelivery::query()
            ->whereKey($delivery->id)
            ->whereNotNull('failed_at')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('uncertain_at')
            ->update([
                'failed_at' => null,
                'next_attempt_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    public function retryFailedForAnnouncement(Announcement $announcement): int
    {
        return $announcement->emailDeliveries()
            ->whereNotNull('failed_at')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('uncertain_at')
            ->update([
                'failed_at' => null,
                'next_attempt_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function finalizeAudience(Announcement $announcement): int
    {
        $recipientsAdded = 0;
        $publishedAt = $announcement->starts_at;

        if (! $publishedAt || $announcement->email_audience_finalized_at) {
            return 0;
        }

        User::query()
            ->where('created_at', '<=', $publishedAt)
            ->whereNull('marketing_emails_opted_out_at')
            ->select(['id', 'email'])
            ->chunkById(100, function (EloquentCollection $users) use ($announcement, &$recipientsAdded): void {
                foreach ($users as $user) {
                    if (! is_string($user->email) || filter_var($user->email, FILTER_VALIDATE_EMAIL) === false) {
                        continue;
                    }

                    $delivery = AnnouncementEmailDelivery::query()->firstOrCreate(
                        [
                            'announcement_id' => $announcement->id,
                            'user_id' => $user->id,
                        ],
                        ['recipient_email' => $user->email]
                    );

                    if ($delivery->wasRecentlyCreated) {
                        $recipientsAdded++;
                    }
                }
            });

        Announcement::query()
            ->whereKey($announcement->id)
            ->whereNull('email_audience_finalized_at')
            ->update(['email_audience_finalized_at' => now()]);

        return $recipientsAdded;
    }

    private function markInterruptedDeliveriesUncertain(): int
    {
        return AnnouncementEmailDelivery::query()
            ->whereNotNull('sending_at')
            ->where('sending_at', '<=', now()->subMinutes(self::STALE_SENDING_MINUTES))
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNull('failed_at')
            ->whereNull('uncertain_at')
            ->update([
                'sending_at' => null,
                'uncertain_at' => now(),
                'failure_reason' => 'Delivery outcome is uncertain after an interrupted send attempt.',
                'updated_at' => now(),
            ]);
    }

    private function recordFailure(
        AnnouncementEmailDelivery $delivery,
        MailerTransportException $exception
    ): string {
        $disposition = $this->classifyFailure($exception);
        $reason = Str::limit($exception->getMessage() ?: $exception::class, 255, '');
        $attributes = [
            'sending_at' => null,
            'next_attempt_at' => null,
            'failure_reason' => $reason,
        ];
        $result = 'failed';

        if ($disposition === AnnouncementEmailFailureDisposition::Retryable
            && $delivery->attempt_count < self::MAX_AUTOMATIC_ATTEMPTS) {
            $attributes['next_attempt_at'] = now()->addMinutes(self::RETRY_DELAY_MINUTES);
            $result = 'retryable';
        } elseif ($disposition === AnnouncementEmailFailureDisposition::Uncertain) {
            $attributes['uncertain_at'] = now();
            $result = 'uncertain';
        } else {
            $attributes['failed_at'] = now();
        }

        $delivery->forceFill($attributes)->save();

        Log::warning('Announcement email delivery attempt did not succeed.', [
            'announcement_id' => $delivery->announcement_id,
            'delivery_id' => $delivery->id,
            'user_id' => $delivery->user_id,
            'attempt_count' => $delivery->attempt_count,
            'disposition' => $disposition->value,
            'reason' => $reason,
        ]);

        return $result;
    }

    private function classifyFailure(MailerTransportException $exception): AnnouncementEmailFailureDisposition
    {
        if ($exception instanceof HttpTransportException) {
            if ($exception->getPrevious() instanceof HttpClientTransportException) {
                return AnnouncementEmailFailureDisposition::Uncertain;
            }

            try {
                $statusCode = $exception->getResponse()->getStatusCode();
            } catch (Throwable) {
                return AnnouncementEmailFailureDisposition::Uncertain;
            }

            if ($statusCode === 429 || $statusCode >= 500) {
                return AnnouncementEmailFailureDisposition::Retryable;
            }

            return AnnouncementEmailFailureDisposition::Terminal;
        }

        return AnnouncementEmailFailureDisposition::Uncertain;
    }

    private function messageIdFor(AnnouncementEmailDelivery $delivery): string
    {
        $fromAddress = (string) config('mail.from.address');
        $domain = Str::after($fromAddress, '@');

        if ($domain === $fromAddress || $domain === '') {
            $domain = 'delight.local';
        }

        return "announcement-email-delivery-{$delivery->id}@{$domain}";
    }
}
