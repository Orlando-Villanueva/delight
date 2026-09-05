<?php

use App\Models\Announcement;
use App\Services\AnnouncementService;

it('rejects updates to an announcement that is no longer a draft', function () {
    $announcement = Announcement::factory()->create();

    app(AnnouncementService::class)->updateDraft($announcement, [
        'title' => 'Changed title',
    ]);
})->throws(LogicException::class, 'Only draft announcements can be edited.');

it('rejects publication of a non-draft announcement', function () {
    $announcement = Announcement::factory()->create();

    app(AnnouncementService::class)->publishDraft($announcement, now());
})->throws(LogicException::class, 'Only draft announcements can be published.');
