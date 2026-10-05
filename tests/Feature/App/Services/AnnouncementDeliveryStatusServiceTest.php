<?php

use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Services\AnnouncementDeliveryStatusService;
use Illuminate\Support\Facades\Mail;

it('counts unresolved attempts as pending and reports recorded outcomes without changing history', function () {
    $this->freezeTime();
    Mail::fake();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);
    $deliveries = AnnouncementEmailDelivery::factory()->count(7)->for($announcement)->sequence(
        [],
        ['sending_at' => now()],
        ['next_attempt_at' => now()->addMinutes(5)],
        ['sent_at' => now(), 'provider_message_id' => 'recorded-id'],
        ['skipped_at' => now()],
        ['failed_at' => now(), 'attempt_count' => 2, 'failure_reason' => 'Permanent rejection'],
        ['uncertain_at' => now(), 'failure_reason' => 'Interrupted attempt'],
    )->create();
    $before = $deliveries->map(fn ($delivery) => $delivery->fresh()->getRawOriginal())->all();
    $announcementBefore = $announcement->fresh()->getRawOriginal();
    $service = app(AnnouncementDeliveryStatusService::class);
    $loaded = $service->withDeliveryCounts(Announcement::query())->findOrFail($announcement->id);

    $summary = $service->summarize($loaded);

    expect($summary)->toMatchArray([
        'authorized' => true, 'audience_finalized' => true, 'completed' => false,
        'status' => 'Needs attention', 'total' => 7, 'pending' => 3,
        'submitted' => 1, 'skipped' => 1, 'failed' => 1, 'uncertain' => 1, 'handled' => 4,
    ]);
    expect($announcement->fresh()->getRawOriginal())->toBe($announcementBefore);
    expect($deliveries->map(fn ($delivery) => $delivery->fresh()->getRawOriginal())->all())->toBe($before);
    Mail::assertNothingSent();
});

it('distinguishes authorization and processing milestones without inventing successful delivery', function (array $attributes, string $status, bool $active) {
    $this->freezeTime();
    $announcement = Announcement::factory()->create([
        'starts_at' => now()->subHour(),
        ...$attributes,
    ]);

    $summary = app(AnnouncementDeliveryStatusService::class)->summarize($announcement);

    expect($summary)->toMatchArray(['status' => $status, 'active' => $active, 'total' => 0, 'submitted' => 0]);
})->with([
    'not authorized' => [[], 'Not authorized', false],
    'authorized without an audience' => [fn () => ['email_broadcast_authorized_at' => now()], 'Audience not finalized', true],
    'scheduled' => [fn () => ['email_broadcast_authorized_at' => now(), 'starts_at' => now()->addDay()], 'Scheduled', false],
    'empty finalized audience awaiting completion' => [fn () => ['email_broadcast_authorized_at' => now(), 'email_audience_finalized_at' => now()], 'Awaiting completion', true],
    'empty completed audience' => [fn () => ['email_broadcast_authorized_at' => now(), 'email_audience_finalized_at' => now(), 'email_broadcast_completed_at' => now()], 'Processing completed', false],
    'legacy timestamp only' => [fn () => ['sent_via_email_at' => now()], 'Historical email records', false],
]);

it('keeps historical outcomes visible without treating them as authorization', function () {
    $this->freezeTime();
    $announcement = Announcement::factory()->create();
    AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);

    $summary = app(AnnouncementDeliveryStatusService::class)->summarize($announcement);

    expect($summary)->toMatchArray(['status' => 'Historical email records', 'authorized' => false, 'failed' => 1, 'pending' => 0, 'active' => false]);
});

it('does not claim processing completion when all recipients were submitted but no completion was recorded', function () {
    $this->freezeTime();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
    ]);
    AnnouncementEmailDelivery::factory()->for($announcement)->create(['sent_at' => now()]);

    $summary = app(AnnouncementDeliveryStatusService::class)->summarize($announcement);

    expect($summary)->toMatchArray(['status' => 'Awaiting completion', 'completed' => false, 'submitted' => 1, 'pending' => 0]);
});

it('reports uncertain attempts independently of submission and completion', function () {
    $this->freezeTime();
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(),
        'email_audience_finalized_at' => now(),
        'email_broadcast_completed_at' => now(),
    ]);
    AnnouncementEmailDelivery::factory()->for($announcement)->create(['uncertain_at' => now()]);

    $summary = app(AnnouncementDeliveryStatusService::class)->summarize($announcement);

    expect($summary)->toMatchArray(['status' => 'Uncertain', 'completed' => true, 'uncertain' => 1, 'submitted' => 0, 'active' => false]);
});
