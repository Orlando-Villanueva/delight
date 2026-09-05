<?php

namespace App\Services;

use App\Models\Announcement;
use Carbon\CarbonInterface;
use LogicException;

class AnnouncementService
{
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
            'email_broadcast_authorized_at' => $isDraft ? null : now(),
        ]);
    }

    public function publishDraft(Announcement $announcement, CarbonInterface $startsAt): Announcement
    {
        if (! $announcement->is_draft) {
            throw new LogicException('Only draft announcements can be published.');
        }

        $announcement->update([
            'is_draft' => false,
            'starts_at' => $startsAt,
            'email_broadcast_authorized_at' => now(),
        ]);

        return $announcement;
    }
}
