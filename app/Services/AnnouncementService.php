<?php

namespace App\Services;

use App\Models\Announcement;

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
    private function create(array $validated, bool $isDraft): Announcement
    {
        return Announcement::query()->create([
            ...$validated,
            'is_draft' => $isDraft,
            'email_broadcast_authorized_at' => $isDraft ? null : now(),
        ]);
    }
}
