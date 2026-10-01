<?php

namespace App\Services;

use App\Models\Announcement;
use Carbon\CarbonInterface;
use LogicException;

class AnnouncementService
{
    public function __construct(private AnnouncementEmailLinkValidator $linkValidator) {}

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
}
