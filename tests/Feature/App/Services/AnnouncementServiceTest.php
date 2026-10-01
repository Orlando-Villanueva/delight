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

it('creates announcements without accepting email authorization from input', function (bool $isDraft) {
    $attributes = Announcement::factory()->make([
        'email_broadcast_authorized_at' => now(),
    ])->getAttributes();
    $service = app(AnnouncementService::class);

    $announcement = $isDraft
        ? $service->createDraft($attributes)
        : $service->createPublishedOrScheduled($attributes);

    expect($announcement->fresh()->is_draft)->toBe($isDraft)
        ->and($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
})->with(['draft' => true, 'published' => false]);

it('publishes a draft while preserving existing email state', function (bool $hasEmailState) {
    $this->freezeSecond();
    $emailState = [
        'email_broadcast_authorized_at' => $hasEmailState ? now()->subHour() : null,
        'email_audience_finalized_at' => $hasEmailState ? now()->subMinutes(50) : null,
        'email_broadcast_completed_at' => $hasEmailState ? now()->subMinutes(40) : null,
        'sent_via_email_at' => $hasEmailState ? now()->subMinutes(40) : null,
    ];
    $draft = Announcement::factory()->draft()->create($emailState);
    $originalEmailState = array_intersect_key($draft->fresh()->getAttributes(), $emailState);
    $startsAt = now()->addDay();

    app(AnnouncementService::class)->publishDraft($draft, $startsAt);

    $published = $draft->fresh();
    expect($published->is_draft)->toBeFalse()
        ->and($published->starts_at->equalTo($startsAt))->toBeTrue()
        ->and(array_intersect_key($published->getAttributes(), $emailState))->toBe($originalEmailState);
})->with(['unauthorized' => false, 'existing email state' => true]);
