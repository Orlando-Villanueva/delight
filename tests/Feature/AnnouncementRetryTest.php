<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\EmailDeliveriesRelationManager;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AnnouncementEmailDeliveryService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();
    Mail::fake();
    config(['mail.admin_address' => 'admin@example.com']);
    Livewire::withoutLazyLoading();
});

it('confirms retries before resetting only eligible failures and refreshing the summary', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create([
        'title' => 'Retry confirmation example',
        'email_broadcast_authorized_at' => now()->subHour(),
        'email_audience_finalized_at' => now()->subHour(),
        'email_broadcast_completed_at' => now()->subMinutes(30),
    ]);
    $failed = AnnouncementEmailDelivery::factory()->for($announcement)->create([
        'failed_at' => now()->subMinutes(30), 'attempt_count' => 2, 'failure_reason' => 'Earlier rejection',
        'message_id' => 'existing-message', 'provider_message_id' => 'existing-provider',
    ]);
    $excluded = AnnouncementEmailDelivery::factory()->count(4)->for($announcement)->sequence(
        ['failed_at' => now(), 'sent_at' => now()], ['failed_at' => now(), 'skipped_at' => now()],
        ['failed_at' => now(), 'uncertain_at' => now()], ['next_attempt_at' => now()->addMinutes(5)],
    )->create();
    $other = AnnouncementEmailDelivery::factory()->create(['failed_at' => now()]);
    $excludedBefore = $excluded->map(fn ($record) => $record->fresh()->getRawOriginal())->all();
    $before = $failed->fresh()->getRawOriginal();
    $this->actingAs($admin);

    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertActionVisible('retryFailedRecipients')->mountAction('retryFailedRecipients')
        ->assertActionMounted('retryFailedRecipients');
    expect($page->instance()->getMountedAction()->getModalDescription())
        ->toContain('Eligible recipients: 1', $announcement->title, 'background worker');
    expect($failed->fresh()->getRawOriginal())->toBe($before);

    $page->callMountedAction()->assertNotified('1 failed recipient marked for retry.')
        ->assertDispatched('announcement-deliveries-retried', announcementId: $announcement->id)
        ->assertSchemaComponentStateSet('delivery_pending', 2, schema: 'infolist')
        ->assertSchemaComponentStateSet('email_broadcast_completed_at', null, schema: 'infolist')
        ->assertActionHidden('retryFailedRecipients');
    expect($failed->fresh()->failed_at)->toBeNull()
        ->and($failed->fresh()->next_attempt_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($failed->fresh()->attempt_count)->toBe(2)
        ->and($failed->fresh()->failure_reason)->toBe('Earlier rejection')
        ->and($failed->fresh()->message_id)->toBe('existing-message')
        ->and($failed->fresh()->provider_message_id)->toBe('existing-provider')
        ->and($announcement->fresh()->email_broadcast_completed_at)->toBeNull()
        ->and($excluded->map(fn ($record) => $record->fresh()->getRawOriginal())->all())->toBe($excludedBefore)
        ->and($other->fresh()->failed_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('blocks ineligible broadcasts in the service, legacy route and Filament action', function (array $attributes) {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create(array_merge([
        'email_broadcast_authorized_at' => now(), 'email_broadcast_completed_at' => now(),
    ], $attributes));
    $failed = AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);
    $before = $failed->fresh()->getRawOriginal();
    $announcementBefore = $announcement->fresh()->getRawOriginal();
    $service = app(AnnouncementEmailDeliveryService::class);
    expect($service->countRetryableFailures($announcement))->toBe(0)
        ->and($service->retryFailedForAnnouncement($announcement))->toBe(0);
    $this->actingAs($admin)->post(route('admin.announcements.email-deliveries.retry', $announcement))
        ->assertRedirect(route('admin.announcements.index'));
    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertActionHidden('retryFailedRecipients')->call('mountAction', 'retryFailedRecipients')->assertActionNotMounted();
    expect($failed->fresh()->getRawOriginal())->toBe($before)
        ->and($announcement->fresh()->getRawOriginal())->toBe($announcementBefore);
    Mail::assertNothingSent();
})->with([
    'unauthorized historical' => [fn () => ['email_broadcast_authorized_at' => null, 'sent_via_email_at' => now()->subYear()]],
    'draft' => [['is_draft' => true]],
    'future' => [fn () => ['starts_at' => now()->addDay()]],
]);

it('rechecks current broadcast eligibility instead of trusting a stale model', function () {
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now(), 'email_broadcast_completed_at' => now()]);
    $failed = AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);
    $service = app(AnnouncementEmailDeliveryService::class);
    expect($service->countRetryableFailures($announcement))->toBe(1);
    Announcement::whereKey($announcement->id)->update(['email_broadcast_authorized_at' => null]);
    expect($service->retryFailedForAnnouncement($announcement))->toBe(0)
        ->and($failed->fresh()->failed_at)->not->toBeNull()
        ->and($announcement->fresh()->email_broadcast_completed_at)->not->toBeNull();
});

it('reports the actual reset count when recipients change after confirmation', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now(), 'email_broadcast_completed_at' => now()]);
    $failed = AnnouncementEmailDelivery::factory()->count(2)->for($announcement)->create(['failed_at' => now()]);
    $this->actingAs($admin);
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('retryFailedRecipients');
    $failed->first()->update(['failed_at' => null, 'uncertain_at' => now()]);
    $page->callMountedAction()->assertNotified('1 failed recipient marked for retry.');
    expect($failed->first()->fresh()->uncertain_at)->not->toBeNull()
        ->and($failed->last()->fresh()->failed_at)->toBeNull();
    Mail::assertNothingSent();
});

it('refreshes the recipient table after retries while keeping its outcome filter', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $failed = AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);
    $this->actingAs($admin);
    $table = Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->filterTable('outcome', 'failed')->assertCanSeeTableRecords([$failed]);
    app(AnnouncementEmailDeliveryService::class)->retryFailedForAnnouncement($announcement);
    $table->dispatch('announcement-deliveries-retried', announcementId: $announcement->id)
        ->assertCanNotSeeTableRecords([$failed])->assertSet('tableFilters.outcome.value', 'failed');
    $table->filterTable('outcome', 'pending')->assertCanSeeTableRecords([$failed]);
});

it('blocks a retry request after admin access is revoked', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $failed = AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);
    $response = $this->actingAs($admin)->get(AnnouncementResource::getUrl('view', ['record' => $announcement], panel: 'admin'));
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
    config(['mail.admin_address' => 'revoked@example.com']);

    $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [
            ['method' => 'mountAction', 'params' => ['retryFailedRecipients']],
        ]]],
    ], ['X-Livewire' => 'true'])->assertForbidden();
    expect($failed->fresh()->failed_at)->not->toBeNull();
    Mail::assertNothingSent();
});
