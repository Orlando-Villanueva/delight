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
        ->and($announcement->starts_at->toDateTimeString())->toBe('2026-04-30 12:00:00')
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

it('seeds the permanent achievements release announcement', function () {
    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', 'permanent-achievements-release')->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe('New: Permanent Achievements')
        ->and($announcement->type)->toBe('info')
        ->and($announcement->hero_image_path)->toBe('images/permanent-achievements-release.png')
        ->and($announcement->social_image_path)->toBe('images/permanent-achievements-release.png')
        ->and($announcement->starts_at->toDateTimeString())->toBe('2026-05-15 08:00:00')
        ->and($announcement->ends_at)->toBeNull()
        ->and($announcement->content)->toContain('## What changed')
        ->and($announcement->content)->toContain('Delight now gives your Bible reading milestones a permanent place to live.')
        ->and($announcement->content)->toContain('## What you can earn')
        ->and($announcement->content)->toContain('Fixed streak achievements for consistent reading')
        ->and($announcement->content)->toContain('![Delight achievement shelf showing next goals and earned milestones](/images/updates/permanent-achievements-shelf.png)')
        ->and($announcement->content)->toContain('## Next Milestone on your dashboard')
        ->and($announcement->content)->toContain('what is the next good thing to aim for?')
        ->and($announcement->content)->toContain('<img src="/images/updates/permanent-achievements-next-milestone.png" alt="Delight dashboard next milestone card showing progress toward finishing Amos" class="mx-auto max-w-sm rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">')
        ->and($announcement->content)->toContain('## Already been reading?')
        ->and($announcement->content)->toContain('so longtime readers do not have to start from zero')
        ->and($announcement->content)->toContain('[View your achievement shelf](/achievements)');

    expect(file_exists(public_path($announcement->hero_image_path)))->toBeTrue();
    expect(file_exists(public_path($announcement->social_image_path)))->toBeTrue();
    expect(file_exists(public_path('images/updates/permanent-achievements-shelf.png')))->toBeTrue();
    expect(file_exists(public_path('images/updates/permanent-achievements-next-milestone.png')))->toBeTrue();
    expect(getimagesize(public_path('images/updates/permanent-achievements-next-milestone.png'))[0])->toBeGreaterThan(700);
});

it('keeps release announcement seeds idempotent', function () {
    $this->seed(ReleaseAnnouncementsSeeder::class);
    $this->seed(ReleaseAnnouncementsSeeder::class);

    expect(Announcement::where('slug', 'deuterocanonical-books-release')->count())->toBe(1);
    expect(Announcement::where('slug', 'permanent-achievements-release')->count())->toBe(1);
});
