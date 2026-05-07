<?php

use App\Models\Announcement;
use Illuminate\Support\Carbon;

it('lists the updates index and visible announcement articles', function () {
    $visibleAnnouncement = Announcement::create([
        'title' => 'Visible Update',
        'slug' => 'visible-update',
        'content' => 'Visible article body.',
        'type' => 'info',
        'starts_at' => now()->subDay(),
    ]);

    $futureAnnouncement = Announcement::create([
        'title' => 'Future Update',
        'slug' => 'future-update',
        'content' => 'Future article body.',
        'type' => 'info',
        'starts_at' => now()->addDay(),
    ]);

    $expiredAnnouncement = Announcement::create([
        'title' => 'Expired Update',
        'slug' => 'expired-update',
        'content' => 'Expired article body.',
        'type' => 'info',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertSee('<loc>'.route('announcements.index').'</loc>', false)
        ->assertSee('<loc>'.route('announcements.show', $visibleAnnouncement->slug).'</loc>', false)
        ->assertDontSee(route('announcements.show', $futureAnnouncement->slug), false)
        ->assertDontSee(route('announcements.show', $expiredAnnouncement->slug), false);
});

it('uses the most recently updated visible announcement for the updates index lastmod', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 6, 12, 0, 0));

    $olderAnnouncement = Announcement::create([
        'title' => 'Older Update',
        'slug' => 'older-update',
        'content' => 'Older article body.',
        'type' => 'info',
        'starts_at' => now()->subDays(10),
    ]);

    $newerAnnouncement = Announcement::create([
        'title' => 'Newer Update',
        'slug' => 'newer-update',
        'content' => 'Newer article body.',
        'type' => 'info',
        'starts_at' => now()->subDays(5),
    ]);

    Announcement::withoutTimestamps(function () use ($olderAnnouncement, $newerAnnouncement) {
        $olderAnnouncement->forceFill(['updated_at' => Carbon::create(2026, 4, 20, 8, 0, 0)])->save();
        $newerAnnouncement->forceFill(['updated_at' => Carbon::create(2026, 4, 30, 9, 30, 0)])->save();
    });

    $response = $this->get(route('sitemap'));

    $expectedUpdatesEntry = '<url><loc>'.route('announcements.index').'</loc><lastmod>'.$newerAnnouncement->fresh()->updated_at->toIso8601String().'</lastmod>';

    $response->assertOk()
        ->assertSee($expectedUpdatesEntry, false);
});
