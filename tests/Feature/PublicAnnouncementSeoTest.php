<?php

use App\Models\Announcement;
use Database\Seeders\ReleaseAnnouncementsSeeder;

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
    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', 'deuterocanonical-books-release')->firstOrFail();

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('announcements.show', $announcement->slug).'">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<meta name="description" content="You can now enable the Catholic 73-book canon from Settings.', false)
        ->assertSee('property="og:description" content="You can now enable the Catholic 73-book canon from Settings.', false)
        ->assertDontSee('<meta name="description" content="What changed', false);

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $response->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();
    expect(json_decode($matches[1], associative: true, flags: JSON_THROW_ON_ERROR))
        ->description->toStartWith('You can now enable the Catholic 73-book canon from Settings.');
});
