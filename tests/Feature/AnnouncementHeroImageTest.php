<?php

use App\Models\Announcement;

const HERO_IMAGE_PATH = 'images/deuterocanonical-books-hero.jpg';
const SOCIAL_IMAGE_PATH = 'images/deuterocanonical-books-social.jpg';
const IMAGE_SOURCE_ATTRIBUTE = 'src="';

it('renders a hero image at the start of an announcement article', function () {
    $announcement = Announcement::create([
        'title' => 'New Feature',
        'slug' => 'new-feature',
        'content' => "### Body heading\n\nArticle body.",
        'type' => 'info',
        'hero_image_path' => HERO_IMAGE_PATH,
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee(IMAGE_SOURCE_ATTRIBUTE.asset(HERO_IMAGE_PATH).'"', false);
    $response->assertSee('alt="New Feature"', false);
    $response->assertSee('<h3>Body heading</h3>', false);

    expect(strpos($response->getContent(), IMAGE_SOURCE_ATTRIBUTE.asset(HERO_IMAGE_PATH).'"'))
        ->toBeLessThan(strpos($response->getContent(), '<h3>Body heading</h3>'));
});

it('uses the announcement hero image for social metadata', function () {
    $announcement = Announcement::create([
        'title' => 'Social Feature',
        'slug' => 'social-feature',
        'content' => 'Article body.',
        'type' => 'info',
        'hero_image_path' => HERO_IMAGE_PATH,
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee('property="og:image" content="'.asset(HERO_IMAGE_PATH).'"', false);
    $response->assertSee('property="twitter:image" content="'.asset(HERO_IMAGE_PATH).'"', false);
    $response->assertSee('"image": "'.asset(HERO_IMAGE_PATH).'"', false);
});

it('prefers a dedicated social image for social metadata', function () {
    $announcement = Announcement::create([
        'title' => 'Social Crop Feature',
        'slug' => 'social-crop-feature',
        'content' => 'Article body.',
        'type' => 'info',
        'hero_image_path' => HERO_IMAGE_PATH,
        'social_image_path' => SOCIAL_IMAGE_PATH,
        'starts_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('announcements.show', $announcement->slug));

    $response->assertOk();
    $response->assertSee(IMAGE_SOURCE_ATTRIBUTE.asset(HERO_IMAGE_PATH).'"', false);
    $response->assertSee('property="og:image" content="'.asset(SOCIAL_IMAGE_PATH).'"', false);
    $response->assertSee('property="twitter:image" content="'.asset(SOCIAL_IMAGE_PATH).'"', false);
    $response->assertSee('"image": "'.asset(SOCIAL_IMAGE_PATH).'"', false);
});
