<?php

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\ReleaseAnnouncementsSeeder;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('renders announcement index seo directives for guests', function () {
    Announcement::create([
        'title' => 'Visible Update',
        'slug' => 'visible-update',
        'content' => "## What changed\n\nA visible public update.",
        'type' => 'info',
        'starts_at' => now()->subDay(),
    ]);

    $response = $this->get(route('announcements.index'));

    $response->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('announcements.index').'">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false);
});

it('renders announcement article seo directives and a body-first description for guests', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 14, 9, 0, 0));

    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', 'permanent-achievements-release')->firstOrFail();

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('announcements.show', $announcement->slug).'">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<meta name="description" content="Delight now gives your Bible reading milestones a permanent place to live.', false)
        ->assertSee('property="og:description" content="Delight now gives your Bible reading milestones a permanent place to live.', false)
        ->assertSee('property="og:image" content="'.asset('images/permanent-achievements-release.png').'"', false)
        ->assertDontSee('<meta name="description" content="What changed', false);

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $response->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();
    expect(json_decode($matches[1], associative: true, flags: JSON_THROW_ON_ERROR))
        ->description->toStartWith('Delight now gives your Bible reading milestones a permanent place to live.');
});

it('keeps scheduled announcements hidden from public lists and notifications until their publish time', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 13, 18, 0, 0));

    $this->seed(ReleaseAnnouncementsSeeder::class);

    $user = User::factory()->create();
    $announcement = Announcement::where('slug', 'permanent-achievements-release')->firstOrFail();

    $this->get(route('announcements.index'))
        ->assertOk()
        ->assertDontSee($announcement->title);

    $this->get(route('announcements.show', $announcement->slug))
        ->assertNotFound();

    expect($user->unreadAnnouncements()->whereKey($announcement->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee($announcement->title);

    Carbon::setTestNow(Carbon::create(2026, 5, 14, 8, 0, 0));

    $this->get(route('announcements.index'))
        ->assertOk()
        ->assertSee($announcement->title);

    expect($user->unreadAnnouncements()->whereKey($announcement->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($announcement->title);
});
