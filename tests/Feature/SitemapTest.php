<?php

use App\Models\Announcement;

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
