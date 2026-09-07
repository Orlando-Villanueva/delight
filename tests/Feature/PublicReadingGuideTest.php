<?php

use App\Models\User;

it('serves the approved guide in initial HTML to guests with an account CTA and metadata', function () {
    $url = route('guides.paper-bible');
    $response = $this->get($url);

    $response->assertOk()
        ->assertSeeText('How to keep track of Bible reading with a paper Bible')
        ->assertSeeText('Orlando Villanueva')
        ->assertSee('<time datetime="2026-09-07">September 7, 2026</time>', false)
        ->assertSee('<meta property="article:published_time" content="2026-09-07">', false)
        ->assertSeeText('three reading days')
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/guide-paper-bible-social.jpg').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('src="'.asset('images/guide-paper-bible-hero.jpg').'"', false)
        ->assertSee('guide-reading-form.png')
        ->assertSee('guide-reading-calendar.png');

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect($schema['@type'])->toBe('Article');
    expect($schema['author']['name'])->toBe('Orlando Villanueva');
    expect($schema['image'])->toBe(asset('images/guide-paper-bible-hero.jpg'));
    expect($schema['datePublished'])->toBe('2026-09-07');
    expect($schema)->not->toHaveKey('dateModified');
});

it('links the guide from the landing page and sitemap without inventing modification dates', function () {
    $url = route('guides.paper-bible');

    $this->get('/')->assertSee('href="'.$url.'"', false);
    $this->get(route('sitemap'))->assertSee('<url><loc>'.$url.'</loc></url>', false);
});

it('sends signed-in readers to the reading form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('guides.paper-bible'))
        ->assertOk()
        ->assertSee('href="'.route('logs.create').'"', false)
        ->assertSeeText('Log a Reading');
});
