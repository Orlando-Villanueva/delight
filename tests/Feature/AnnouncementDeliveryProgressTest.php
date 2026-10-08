<?php

use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\EmailDeliveriesRelationManager;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Services\AnnouncementDeliveryStatusService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
    Livewire::withoutLazyLoading();
});

it('polls only authorized incomplete announcements including scheduled broadcasts', function (array $attributes, bool $polls) {
    $announcement = Announcement::factory()->create($attributes);

    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id]);

    if ($polls) {
        $page->assertSee('wire:poll.15s.visible', false)->assertSee('Auto-refresh');
    } else {
        $page->assertDontSee('wire:poll.15s.visible', false)->assertDontSee('Auto-refresh');
    }
})->with([
    'unauthorized' => [[], false],
    'draft' => [['is_draft' => true], false],
    'due' => [fn (): array => ['email_broadcast_authorized_at' => now()], true],
    'scheduled' => [fn (): array => ['starts_at' => now()->addDay(), 'email_broadcast_authorized_at' => now()], true],
    'completed' => [fn (): array => ['email_broadcast_authorized_at' => now(), 'email_broadcast_completed_at' => now()], false],
]);

it('refreshes recorded milestones and totals and stops polling at completion', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id]);
    $delivery->update(['sent_at' => now()]);
    $announcement->update(['email_audience_finalized_at' => now(), 'email_broadcast_completed_at' => now()]);

    $page->call('$refresh');

    $page->assertSee('Processing completed')->assertDontSee('Auto-refresh')->assertDontSee('wire:poll', false);

    $summary = app(AnnouncementDeliveryStatusService::class)->summarize($page->instance()->getRecord());
    expect($summary['submitted'])->toBe(1)->and($summary['pending'])->toBe(0);
});

it('resumes polling after a failed-recipient retry', function () {
    $announcement = Announcement::factory()->create([
        'email_broadcast_authorized_at' => now(), 'email_broadcast_completed_at' => now(),
    ]);
    AnnouncementEmailDelivery::factory()->for($announcement)->create(['failed_at' => now()]);

    Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertDontSee('wire:poll.15s.visible', false)
        ->callAction('retryFailedRecipients')
        ->assertSee('wire:poll.15s.visible', false);
});

it('keeps page confirmation open during page refresh', function () {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])->mountAction('authorizeEmail');

    $page->call('$refresh')
        ->assertActionMounted('authorizeEmail');
});

it('updates the refresh time when page polling renders fresh data', function () {
    $this->freezeSecond();
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id]);
    $this->travel(15)->seconds();

    $page->call('$refresh');

    $page->assertSee('Last refreshed '.now()->format('H:i:s T'));
});

it('polls an empty recipient table so the first recorded recipients appear', function () {
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $page = Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->assertSee('wire:poll.15s.visible', false);
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create();

    $page->call('$refresh')->assertCanSeeTableRecords([$delivery]);
    $announcement->update(['email_broadcast_completed_at' => now()]);

    $page->call('$refresh')->assertDontSee('wire:poll.15s.visible', false);
});

it('refreshes recipient rows without resetting search filters or pagination', function () {
    $announcement = Announcement::factory()->create(['email_broadcast_authorized_at' => now()]);
    $deliveries = AnnouncementEmailDelivery::factory()->count(45)->for($announcement)
        ->sequence(fn ($sequence): array => ['recipient_email' => 'progress-'.$sequence->index.'@example.com'])
        ->create();
    $page = Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->searchTable('progress-')->filterTable('outcome', 'pending');
    $paginationName = $page->instance()->getTablePaginationPageName();
    $page->call('setPage', 2, $paginationName);
    $deliveries[25]->update(['sent_at' => now()]);

    $page->call('$refresh')
        ->assertSet('tableSearch', 'progress-')
        ->assertSet('tableFilters.outcome.value', 'pending')
        ->assertSet('paginators.'.$paginationName, 2)
        ->assertCanNotSeeTableRecords([$deliveries[25]]);
});

it('keeps recipient details open during native table refresh', function () {
    $announcement = Announcement::factory()->create();
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create();

    Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
        ->mountAction(TestAction::make('view')->table($delivery))
        ->call('$refresh')
        ->assertActionMounted(TestAction::make('view')->table($delivery));
});

it('rechecks admin access on page and recipient refresh requests', function () {
    $announcement = Announcement::factory()->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id]);
    $recipients = Livewire::test(EmailDeliveriesRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class]);
    config(['mail.admin_address' => 'other@example.com']);

    $page->call('$refresh')->assertForbidden();
    $recipients->call('$refresh')->assertForbidden();
});

it('reveals the retry header action when polling discovers failures at completion', function () {
    $announcement = Announcement::factory()->create(['starts_at' => now()->subHour(), 'email_broadcast_authorized_at' => now()]);
    $delivery = AnnouncementEmailDelivery::factory()->for($announcement)->create();
    $page = Livewire::test(ViewAnnouncement::class, ['record' => $announcement->id])
        ->assertDontSee('Retry failed recipients');
    $delivery->update(['failed_at' => now()]);
    $announcement->update(['email_broadcast_completed_at' => now()]);

    $page->call('$refresh')->assertSee('Retry failed recipients')->assertDontSee('Auto-refresh');
});
