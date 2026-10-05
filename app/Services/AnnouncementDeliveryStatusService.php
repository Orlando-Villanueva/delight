<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementDeliveryStatusService
{
    public const array RECIPIENT_OUTCOMES = [
        'pending' => 'Pending',
        'submitted' => 'Transport-submitted',
        'skipped' => 'Skipped',
        'failed' => 'Failed',
        'uncertain' => 'Uncertain',
    ];

    private const array OUTCOME_COLUMNS = [
        'submitted' => 'sent_at',
        'skipped' => 'skipped_at',
        'failed' => 'failed_at',
        'uncertain' => 'uncertain_at',
    ];

    public function recipientStatus(AnnouncementEmailDelivery $delivery): string
    {
        foreach (self::OUTCOME_COLUMNS as $outcome => $column) {
            if ($delivery->{$column} !== null) {
                return self::RECIPIENT_OUTCOMES[$outcome];
            }
        }

        return self::RECIPIENT_OUTCOMES['pending'];
    }

    /**
     * @param  Builder<AnnouncementEmailDelivery>  $query
     * @return Builder<AnnouncementEmailDelivery>
     */
    public function filterRecipients(Builder $query, ?string $outcome): Builder
    {
        if ($outcome === 'pending') {
            foreach (self::OUTCOME_COLUMNS as $column) {
                $query->whereNull($column);
            }
        } elseif (isset(self::OUTCOME_COLUMNS[$outcome])) {
            $query->whereNotNull(self::OUTCOME_COLUMNS[$outcome]);
        }

        return $query;
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function withDeliveryCounts(Builder $query): Builder
    {
        return $query->withCount($this->deliveryCounts());
    }

    /**
     * @return array{status: string, authorized: bool, audience_finalized: bool, completed: bool, active: bool, total: int, pending: int, submitted: int, skipped: int, failed: int, uncertain: int, handled: int}
     */
    public function summarize(Announcement $announcement): array
    {
        if (! array_key_exists('email_pending_count', $announcement->getAttributes())) {
            $announcement->loadCount($this->deliveryCounts());
        }

        $authorized = $announcement->email_broadcast_authorized_at !== null;
        $finalized = $announcement->email_audience_finalized_at !== null;
        $completed = $announcement->email_broadcast_completed_at !== null;
        $total = (int) $announcement->email_deliveries_count;
        $pending = (int) $announcement->email_pending_count;
        $hasHistory = $total > 0 || $finalized || $completed || $announcement->sent_via_email_at !== null;

        $status = match (true) {
            ! $authorized && $hasHistory => 'Historical email records',
            ! $authorized => 'Not authorized',
            $announcement->starts_at?->isFuture() === true => 'Scheduled',
            ! $finalized => 'Audience not finalized',
            $announcement->email_failed_count > 0 => 'Needs attention',
            $announcement->email_uncertain_count > 0 => 'Uncertain',
            $pending > 0 => 'Pending recipients',
            $completed => 'Processing completed',
            default => 'Awaiting completion',
        };

        return [
            'status' => $status,
            'authorized' => $authorized,
            'audience_finalized' => $finalized,
            'completed' => $completed,
            'active' => $authorized && ! $completed && ! $announcement->is_draft
                && $announcement->starts_at?->lte(now()) === true,
            'total' => $total,
            'pending' => $pending,
            'submitted' => (int) $announcement->email_sent_count,
            'skipped' => (int) $announcement->email_skipped_count,
            'failed' => (int) $announcement->email_failed_count,
            'uncertain' => (int) $announcement->email_uncertain_count,
            'handled' => $total - $pending,
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    private function deliveryCounts(): array
    {
        return [
            'emailDeliveries',
            'emailDeliveries as email_pending_count' => fn (Builder $query) => $this->filterRecipients($query, 'pending'),
            'emailDeliveries as email_sent_count' => fn (Builder $query) => $query->whereNotNull('sent_at'),
            'emailDeliveries as email_skipped_count' => fn (Builder $query) => $query->whereNotNull('skipped_at'),
            'emailDeliveries as email_failed_count' => fn (Builder $query) => $query->whereNotNull('failed_at'),
            'emailDeliveries as email_uncertain_count' => fn (Builder $query) => $query->whereNotNull('uncertain_at'),
        ];
    }
}
