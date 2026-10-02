<?php

namespace App\Services;

use App\Models\Announcement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class AnnouncementService
{
    public function __construct(
        private AnnouncementEmailLinkValidator $linkValidator,
        private AnnouncementEmailDeliveryService $deliveryService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createDraft(array $validated): Announcement
    {
        return $this->create($validated, isDraft: true);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createPublishedOrScheduled(array $validated): Announcement
    {
        $this->linkValidator->validate(new Announcement($validated));

        return $this->create($validated, isDraft: false);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDraft(Announcement $announcement, array $validated): Announcement
    {
        if (! $announcement->is_draft) {
            throw new LogicException('Only draft announcements can be edited.');
        }

        $announcement->update($validated);

        return $announcement;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function create(array $validated, bool $isDraft): Announcement
    {
        return Announcement::query()->create([
            ...$validated,
            'is_draft' => $isDraft,
            'email_broadcast_authorized_at' => null,
        ]);
    }

    public function publishDraft(Announcement $announcement, CarbonInterface $startsAt): Announcement
    {
        if (! $announcement->is_draft) {
            throw new LogicException('Only draft announcements can be published.');
        }

        $this->linkValidator->validate($announcement);

        $announcement->update([
            'is_draft' => false,
            'starts_at' => $startsAt,
        ]);

        return $announcement;
    }

    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     title: string,
     *     state: string,
     *     starts_at: ?string,
     *     ends_at: ?string,
     *     email_broadcast_authorized_at: ?string,
     *     delivery_due_at: ?string,
     *     eligible_recipients?: int,
     *     excluded_recipients?: int,
     *     audience_note: string,
     *     delivery_note: string
     * }
     */
    public function previewEmailAuthorization(Announcement $announcement): array
    {
        $announcement->refresh();
        $this->validateEmailAuthorization($announcement);

        return [
            'id' => $announcement->id,
            'slug' => $announcement->slug,
            'title' => $announcement->title,
            'state' => $announcement->starts_at?->isFuture() ? 'scheduled' : 'published',
            'starts_at' => $announcement->starts_at?->toIso8601String(),
            'ends_at' => $announcement->ends_at?->toIso8601String(),
            'email_broadcast_authorized_at' => $announcement->email_broadcast_authorized_at?->toIso8601String(),
            'delivery_due_at' => $announcement->starts_at?->toIso8601String(),
            ...($announcement->starts_at ? $this->deliveryService->estimateAudience($announcement->starts_at) : []),
            'audience_note' => 'Current estimates using the publication-time cutoff; authorization does not change an existing audience.',
            'delivery_note' => 'Authorization permits the worker to process email once publication is due; it does not confirm sending.',
        ];
    }

    public function authorizeEmail(Announcement $announcement): bool
    {
        $authorized = DB::transaction(function () use ($announcement): bool {
            $current = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $this->validateEmailAuthorization($current);

            if ($current->email_broadcast_authorized_at !== null) {
                return false;
            }

            $current->update(['email_broadcast_authorized_at' => now()]);

            return true;
        });

        $announcement->refresh();

        return $authorized;
    }

    private function validateEmailAuthorization(Announcement $announcement): void
    {
        if ($announcement->is_draft) {
            throw ValidationException::withMessages([
                'announcement' => ['Publish or schedule the announcement before authorizing email.'],
            ]);
        }

        if ($announcement->email_broadcast_authorized_at !== null) {
            return;
        }

        if ($announcement->ends_at?->lt(now())) {
            throw ValidationException::withMessages([
                'ends_at' => ['Expired announcements cannot be authorized for email.'],
            ]);
        }

        if ($announcement->sent_via_email_at !== null
            || $announcement->email_audience_finalized_at !== null
            || $announcement->email_broadcast_completed_at !== null
            || $announcement->emailDeliveries()->exists()) {
            throw ValidationException::withMessages([
                'announcement' => ['This announcement has existing email history and requires separate reconciliation.'],
            ]);
        }

        if ($announcement->starts_at === null) {
            throw ValidationException::withMessages([
                'starts_at' => ['Email authorization requires a publication time.'],
            ]);
        }

        $this->linkValidator->validate($announcement);
    }
}
