<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\EmailDeliveriesRelationManager;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
    Livewire::withoutLazyLoading();
});

it('shows stored publication and processing milestones with recipient totals', function () {
    $this->freezeTime();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now()->subHour(),
        'email_audience_finalized_at' => now()->subMinutes(50),
        'email_broadcast_completed_at' => now()->subMinutes(40),
    ]);
    AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()->subMinutes(45)]);
    $this->actingAs($admin);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertSee('Needs attention')
        ->assertSee('Processing completed at')
        ->assertSee($announcement->email_broadcast_completed_at->format('M j, Y H:i:s'))
        ->assertSee('Recorded recipient outcomes')
        ->assertSee('Transport-submitted')
        ->assertSeeLivewire(EmailDeliveriesRelationManager::class)
        ->assertActionHidden('editDraft');
});

it('links editable drafts to the draft editor from their details page', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->draft()->create();
    $this->actingAs($admin);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertActionVisible('editDraft')
        ->assertActionHasUrl('editDraft', AnnouncementResource::getUrl('edit', ['record' => $announcement], panel: 'admin'));
});

it('shows historical records without inferring authorization or missing milestones', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create(['sent_via_email_at' => now()->subYear()]);
    $this->actingAs($admin);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertSee('Historical email records')->assertSee('Not authorized')->assertSee('Not finalized')
        ->assertSee('Legacy email timestamp')->assertSee('Not recorded');
});

it('protects detail pages and recipient data from guests and readers', function () {
    $announcement = Announcement::factory()->create();
    $url = AnnouncementResource::getUrl('view', ['record' => $announcement], panel: 'admin');
    $this->get($url)->assertRedirect(route('login'));
    $reader = User::factory()->create();
    $this->actingAs($reader)->get($url)->assertForbidden();

    Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->assertForbidden();
});

it('rechecks admin access when recipient components update', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create();
    $response = $this->actingAs($admin)->get(AnnouncementResource::getUrl('view', ['record' => $announcement], panel: 'admin'));
    preg_match_all('/wire:snapshot="([^"\n]+)"/', $response->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn (string $value): string => html_entity_decode($value, ENT_QUOTES))
        ->first(fn (string $value): bool => str_contains(json_decode($value, true)['memo']['name'], 'EmailDeliveriesRelationManager'));
    expect($snapshot)->not->toBeNull();
    config(['mail.admin_address' => 'revoked@example.com']);

    $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => ['tableSearch' => 'recipient'], 'calls' => []]],
    ], ['X-Livewire' => 'true'])->assertForbidden();
});

it('scopes recipient search and modal resolution to the current announcement', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create();
    $own = AnnouncementEmailDelivery::factory()->for($announcement)->create(['recipient_email' => 'find-me@example.com']);
    $other = AnnouncementEmailDelivery::factory()->create(['recipient_email' => 'other@example.com', 'failure_reason' => 'Private other-announcement reason']);
    $this->actingAs($admin);

    Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->searchTable('find-me')->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other])
        ->mountAction(TestAction::make('view')->table($other))
        ->assertActionNotMounted()->assertDontSee('Private other-announcement reason');
});

it('filters stored recipient outcomes and keeps earlier reasons separate from pending retries', function (string $outcome, int $index, string $label) {
    $this->freezeTime();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create();
    $records = AnnouncementEmailDelivery::factory()->count(5)->for($announcement)->sequence(
        ['next_attempt_at' => now()->addMinutes(5), 'failure_reason' => 'Previous transient rejection'],
        ['sent_at' => now()], ['skipped_at' => now()], ['failed_at' => now()], ['uncertain_at' => now()],
    )->create();
    $this->actingAs($admin);

    Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->filterTable('outcome', $outcome)->assertCanSeeTableRecords([$records[$index]])
        ->assertCanNotSeeTableRecords($records->except($records[$index]->id))
        ->assertTableColumnStateSet('outcome', $label, record: $records[$index]);
})->with([
    'pending retry' => ['pending', 0, 'Pending'], 'submitted' => ['submitted', 1, 'Transport-submitted'],
    'skipped' => ['skipped', 2, 'Skipped'], 'failed' => ['failed', 3, 'Failed'], 'uncertain' => ['uncertain', 4, 'Uncertain'],
]);

it('opens escaped diagnostics without changing delivery or announcement history', function () {
    $this->freezeTime();
    Mail::fake();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create();
    $reason = '<script>alert("provider")</script> Rejected <recipient@example.com>';
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create([
        'failed_at' => now(), 'attempt_count' => 2, 'failure_reason' => $reason,
        'message_id' => 'local-message-id', 'provider_message_id' => 'provider-id',
    ]);
    $before = $delivery->fresh()->getRawOriginal();
    $announcementBefore = $announcement->fresh()->getRawOriginal();
    $this->actingAs($admin);

    $component = Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->assertDontSee($reason)->mountAction(TestAction::make('view')->table($delivery))
        ->assertActionMounted(TestAction::make('view')->table($delivery))->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete');

    $modalHtml = $component->instance()->getSchema($component->instance()->getMountedActionSchemaName())->toHtml();
    expect($modalHtml)->toContain(e($reason), 'local-message-id', 'provider-id')->not->toContain($reason);

    expect($delivery->fresh()->getRawOriginal())->toBe($before);
    expect($announcement->fresh()->getRawOriginal())->toBe($announcementBefore);
    Mail::assertNothingSent();
});

it('paginates recipient records without loading another announcement', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $announcement = Announcement::factory()->create();
    $records = AnnouncementEmailDelivery::factory()->count(21)->for($announcement)->create();
    $this->actingAs($admin);

    Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->assertCanSeeTableRecords($records->skip(1))->assertCanNotSeeTableRecords([$records->first()])
        ->set('paginators.emailDeliveriesRelationManagerPage', 2)->assertCanSeeTableRecords([$records->first()])
        ->assertCanNotSeeTableRecords($records->skip(1));
});
