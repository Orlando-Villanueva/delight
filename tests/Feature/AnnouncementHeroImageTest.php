<?php

use App\Models\Announcement;

it('renders a hero image at the start of an announcement article', function () {
    $announcement = Announcement::create([
        'title' => 'New Feature',
        'slug' => 'new-feature',
        'content' => "### Body heading\n\nArticle body.",
        'type' => 'info',
        'hero_image_path' => 'images/deuterocanonical-books-hero.jpg',
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee('src="'.asset('images/deuterocanonical-books-hero.jpg').'"', false);
    $response->assertSee('alt="New Feature"', false);
    $response->assertSee('<h3>Body heading</h3>', false);

    expect(strpos($response->getContent(), 'src="'.asset('images/deuterocanonical-books-hero.jpg').'"'))
        ->toBeLessThan(strpos($response->getContent(), '<h3>Body heading</h3>'));
});

it('uses the announcement hero image for social metadata', function () {
    $announcement = Announcement::create([
        'title' => 'Social Feature',
        'slug' => 'social-feature',
        'content' => 'Article body.',
        'type' => 'info',
        'hero_image_path' => 'images/deuterocanonical-books-hero.jpg',
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee('property="og:image" content="'.asset('images/deuterocanonical-books-hero.jpg').'"', false);
    $response->assertSee('property="twitter:image" content="'.asset('images/deuterocanonical-books-hero.jpg').'"', false);
    $response->assertSee('"image": "'.asset('images/deuterocanonical-books-hero.jpg').'"', false);
});

it('prefers a dedicated social image for social metadata', function () {
    $announcement = Announcement::create([
        'title' => 'Social Crop Feature',
        'slug' => 'social-crop-feature',
        'content' => 'Article body.',
        'type' => 'info',
        'hero_image_path' => 'images/deuterocanonical-books-hero.jpg',
        'social_image_path' => 'images/deuterocanonical-books-social.jpg',
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee('src="'.asset('images/deuterocanonical-books-hero.jpg').'"', false);
    $response->assertSee('property="og:image" content="'.asset('images/deuterocanonical-books-social.jpg').'"', false);
    $response->assertSee('property="twitter:image" content="'.asset('images/deuterocanonical-books-social.jpg').'"', false);
    $response->assertSee('"image": "'.asset('images/deuterocanonical-books-social.jpg').'"', false);
});
