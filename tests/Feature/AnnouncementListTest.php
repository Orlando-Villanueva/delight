<?php

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['mail.admin_address' => 'admin@example.com']);
});

it('redirects the protected legacy list to the Filament resource', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    $this->actingAs($admin)->get(route('admin.announcements.index'))
        ->assertRedirect(AnnouncementResource::getUrl('index', panel: 'admin'));
});

it('protects the resource from readers and guests', function () {
    $url = AnnouncementResource::getUrl('index', panel: 'admin');
    $this->get($url)->assertRedirect(route('login'));
    $reader = User::factory()->create();

    $this->actingAs($reader)->get($url)->assertForbidden();
});

it('rechecks current admin access during announcement table updates', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $response = $this->actingAs($admin)->get(AnnouncementResource::getUrl('index', panel: 'admin'));
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
    config(['mail.admin_address' => 'revoked@example.com']);

    $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => ['tableSearch' => 'release'], 'calls' => []]],
    ], ['X-Livewire' => 'true'])->assertForbidden();
});

it('searches titles and slugs', function (string $search) {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $match = Announcement::factory()->create(['title' => 'Reading plan release', 'slug' => 'find-this-slug']);
    $other = Announcement::factory()->create(['title' => 'Other update', 'slug' => 'other-update']);
    $this->actingAs($admin);

    Livewire::test(ListAnnouncements::class)->searchTable($search)
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
})->with(['title' => 'Reading plan', 'slug' => 'find-this-slug']);

it('filters publication states with consistent date boundaries', function (string $filter, int $index) {
    $this->freezeTime();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $records = collect([
        Announcement::factory()->draft()->create(['ends_at' => now()->subDay()]),
        Announcement::factory()->create(['starts_at' => now()->addDay()]),
        Announcement::factory()->create(['starts_at' => now(), 'ends_at' => now()]),
        Announcement::factory()->create(['starts_at' => null, 'ends_at' => null]),
        Announcement::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->subSecond()]),
    ]);
    $this->actingAs($admin);
    $expected = $filter === 'published' ? $records->only([2, 3]) : $records->only([$index]);

    Livewire::test(ListAnnouncements::class)->filterTable('publication_status', $filter)
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords($records->diff($expected))
        ->assertTableColumnStateSet('publication_status', ucfirst($filter), record: $records[$index]);
})->with(['draft' => ['draft', 0], 'scheduled' => ['scheduled', 1], 'published' => ['published', 2], 'expired' => ['expired', 4]]);

it('paginates twenty records without changing stored announcements or recipients', function () {
    $this->freezeTime();
    Mail::fake();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $records = Announcement::factory()->count(21)->sequence(fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index)])->create();
    $delivery = AnnouncementEmailDelivery::factory()->for($records->last())->create(['failed_at' => now(), 'attempt_count' => 2]);
    $before = $delivery->fresh()->getRawOriginal();
    $this->actingAs($admin);

    Livewire::test(ListAnnouncements::class)
        ->assertCanSeeTableRecords($records->take(20))
        ->assertCanNotSeeTableRecords([$records->last()])
        ->set('paginators.page', 2)
        ->assertCanSeeTableRecords([$records->last()])
        ->assertCanNotSeeTableRecords($records->take(20));

    expect($delivery->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('announcements', 21);
    $this->assertDatabaseCount('announcement_email_deliveries', 1);
    Mail::assertNothingSent();
});

it('offers navigation links without exposing generated mutation actions', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $draft = Announcement::factory()->draft()->create();
    $published = Announcement::factory()->create();
    $this->actingAs($admin);

    Livewire::test(ListAnnouncements::class)
        ->assertSee(AnnouncementResource::getUrl('view', ['record' => $draft], panel: 'admin'))
        ->assertSee(AnnouncementResource::getUrl('create', panel: 'admin'))
        ->assertSee('New draft')
        ->assertSee(AnnouncementResource::getUrl('edit', ['record' => $draft], panel: 'admin'))
        ->assertSee(route('admin.announcements.preview', $draft->slug))
        ->assertSee(route('announcements.show', $published->slug))
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionHidden('editDraft', $published);
});

it('preserves authoring feedback through the legacy list redirect', function () {
    Mail::fake();
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    $response = $this->actingAs($admin)->followingRedirects()->post(route('admin.announcements.store'), [
        'title' => 'Local feedback check',
        'slug' => 'local-feedback-check',
        'content' => 'A local announcement without external links.',
        'hero_image_path' => 'images/updates/local-feedback-check.png',
        'starts_at' => now()->toDateTimeString(),
    ]);

    $response->assertSee('Announcement published.');
    Mail::assertNothingSent();
    $this->assertDatabaseHas('announcements', ['slug' => 'local-feedback-check', 'email_broadcast_authorized_at' => null]);
});

it('shows authorization separately only when it adds to the status badge', function (array $attributes, string $status, ?string $authorization) {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    Announcement::factory()->create($attributes);
    $this->actingAs($admin);

    $component = Livewire::test(ListAnnouncements::class)->assertSee($status);

    if ($authorization === null) {
        $component->assertDontSee('Email not authorized');
    } else {
        $component->assertSee($authorization);
    }
})->with([
    'not authorized' => [[], 'Not authorized', null],
    'historical' => [fn () => ['sent_via_email_at' => now()->subDay()], 'Historical email records', 'Email not authorized'],
    'authorized' => [fn () => ['email_broadcast_authorized_at' => now()], 'Audience not finalized', 'Email authorized'],
]);
