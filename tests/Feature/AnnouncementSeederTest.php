<?php

use App\Models\Announcement;
use Database\Seeders\ReleaseAnnouncementsSeeder;

it('seeds the deuterocanonical books release announcement', function () {
    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', 'deuterocanonical-books-release')->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe('New: Deuterocanonical Book Support')
        ->and($announcement->type)->toBe('info')
        ->and($announcement->hero_image_path)->toBe('images/deuterocanonical-books-hero.jpg')
        ->and($announcement->social_image_path)->toBe('images/deuterocanonical-books-social.jpg')
        ->and($announcement->ends_at)->toBeNull()
        ->and($announcement->content)->toContain('## What changed')
        ->and($announcement->content)->not->toContain('### Deuterocanonical books are now available')
        ->and($announcement->content)->toContain('Catholic 73-book canon')
        ->and($announcement->content)->toContain('Your current reading experience does not change unless you turn this setting on.')
        ->and($announcement->content)->toContain('## Why it is optional')
        ->and($announcement->content)->toContain('most Protestant Bibles do not include them in the 66-book canon')
        ->and($announcement->content)->toContain('Delight keeps the 66-book canon as the default')
        ->and($announcement->content)->toContain('## Included books')
        ->and($announcement->content)->toContain('Tobit, Judith, Wisdom, Sirach, Baruch, 1 Maccabees, and 2 Maccabees')
        ->and($announcement->content)->toContain('## Your history stays intact')
        ->and($announcement->content)->toContain('continue to count toward streaks and weekly goals')
        ->and($announcement->content)->toContain('[Open Settings](/settings)');

    expect(file_exists(public_path($announcement->hero_image_path)))->toBeTrue();
    expect(file_exists(public_path($announcement->social_image_path)))->toBeTrue();
});

it('keeps the deuterocanonical announcement seed idempotent', function () {
    $this->seed(ReleaseAnnouncementsSeeder::class);
    $this->seed(ReleaseAnnouncementsSeeder::class);

    expect(Announcement::where('slug', 'deuterocanonical-books-release')->count())->toBe(1);
});
