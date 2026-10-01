<?php

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Services\AnnouncementService;
use Illuminate\Validation\ValidationException;

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

it('authorizes once even when another caller holds a stale announcement', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create();
    $stale = $announcement->fresh();
    $service = app(AnnouncementService::class);

    expect($service->authorizeEmail($announcement))->toBeTrue();
    $authorizedAt = $announcement->email_broadcast_authorized_at->toIso8601String();
    $this->travel(1)->hour();

    expect($service->authorizeEmail($stale))->toBeFalse()
        ->and($stale->email_broadcast_authorized_at->toIso8601String())->toBe($authorizedAt);
    $this->assertDatabaseCount('announcement_email_deliveries', 0);
});

it('does not authorize email with historical state but no authorization timestamp', function (string $state) {
    $announcement = Announcement::factory()->create([$state => now()]);

    try {
        app(AnnouncementService::class)->authorizeEmail($announcement);
        $this->fail('Historical email state must require reconciliation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('announcement');
    }

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
})->with(['sent_via_email_at', 'email_audience_finalized_at', 'email_broadcast_completed_at']);

it('does not authorize existing recipient deliveries without an authorization timestamp', function () {
    $announcement = Announcement::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->create([
        'announcement_id' => $announcement->id,
        'failed_at' => now(),
    ]);
    $original = $delivery->fresh()->getAttributes();

    try {
        app(AnnouncementService::class)->authorizeEmail($announcement);
        $this->fail('Existing deliveries must require reconciliation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('announcement');
    }

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull()
        ->and($delivery->fresh()->getAttributes())->toBe($original);
});

it('rechecks draft state from storage before authorizing a stale announcement', function () {
    $announcement = Announcement::factory()->create();
    Announcement::query()->whereKey($announcement->id)->update(['is_draft' => true]);

    try {
        app(AnnouncementService::class)->authorizeEmail($announcement);
        $this->fail('A draft cannot be authorized using stale publication state.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('announcement');
    }

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
});

it('rechecks expiry at authorization after a successful preview', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create(['ends_at' => now()->addMinute()]);
    $service = app(AnnouncementService::class);
    $service->previewEmailAuthorization($announcement);
    $this->travel(2)->minutes();

    try {
        $service->authorizeEmail($announcement);
        $this->fail('An expired announcement cannot be authorized after preview.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('ends_at');
    }

    expect($announcement->fresh()->email_broadcast_authorized_at)->toBeNull();
});

it('preserves existing authorization when an announcement has expired', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
        'email_broadcast_authorized_at' => now()->subDay(),
    ]);
    $original = $announcement->fresh()->getAttributes();
    $service = app(AnnouncementService::class);

    expect($service->previewEmailAuthorization($announcement)['email_broadcast_authorized_at'])
        ->toBe($announcement->email_broadcast_authorized_at->toIso8601String());
    expect($service->authorizeEmail($announcement))->toBeFalse()
        ->and($announcement->fresh()->getAttributes())->toBe($original);
});

it('allows initial authorization at the inclusive visibility expiry boundary', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create(['ends_at' => now()]);

    expect(app(AnnouncementService::class)->authorizeEmail($announcement))->toBeTrue();
});
