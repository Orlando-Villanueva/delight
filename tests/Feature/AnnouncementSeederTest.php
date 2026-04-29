<?php

use App\Models\Announcement;
use Database\Seeders\ReleaseAnnouncementsSeeder;

it('seeds the deuterocanonical books release announcement', function () {
    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', 'deuterocanonical-books-release')->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe('New: Deuterocanonical books support')
        ->and($announcement->type)->toBe('info')
        ->and($announcement->hero_image_path)->toBe('images/deuterocanonical-books-hero.jpg')
        ->and($announcement->social_image_path)->toBe('images/deuterocanonical-books-social.jpg')
        ->and($announcement->ends_at)->toBeNull()
        ->and($announcement->content)->toContain('### What this adds to Delight')
        ->and($announcement->content)->not->toContain('### Deuterocanonical books are now available')
        ->and($announcement->content)->toContain('Catholic 73-book canon')
        ->and($announcement->content)->toContain('Second Temple and Hellenistic periods')
        ->and($announcement->content)->toContain('Septuagint')
        ->and($announcement->content)->toContain('most Protestant Bibles place them outside the 66-book canon')
        ->and($announcement->content)->toContain('optional support for another historic Christian Bible tradition')
        ->and($announcement->content)->toContain('stories of faithful endurance, wisdom teaching, prayer, and the history of Jewish resistance')
        ->and($announcement->content)->toContain('Tobit, Judith, Wisdom, Sirach, Baruch, 1 Maccabees, and 2 Maccabees')
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
