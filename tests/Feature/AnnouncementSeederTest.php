<?php

use App\Models\Announcement;
use Database\Seeders\ReleaseAnnouncementsSeeder;

$releaseAnnouncements = [
    'deuterocanonical books' => [[
        'slug' => 'deuterocanonical-books-release',
        'title' => 'New: Deuterocanonical Book Support',
        'hero' => 'images/deuterocanonical-books-hero.jpg',
        'social' => 'images/deuterocanonical-books-social.jpg',
        'starts_at' => '2026-04-30 12:00:00',
        'contains' => [
            '## What changed',
            'Catholic 73-book canon',
            'Your current reading experience does not change unless you turn this setting on.',
            '## Why it is optional',
            'most Protestant Bibles do not include them in the 66-book canon',
            'Delight keeps the 66-book canon as the default',
            '## Included books',
            'Tobit, Judith, Wisdom, Sirach, Baruch, 1 Maccabees, and 2 Maccabees',
            '## Your history stays intact',
            'continue to count toward streaks and weekly goals',
            '[Open Settings](/settings)',
        ],
        'excludes' => ['### Deuterocanonical books are now available'],
    ]],
    'permanent achievements' => [[
        'slug' => 'permanent-achievements-release',
        'title' => 'New: Permanent Achievements',
        'hero' => 'images/permanent-achievements-release.png',
        'social' => 'images/permanent-achievements-release.png',
        'starts_at' => '2026-05-14 16:00:00',
        'contains' => [
            '## What changed',
            'Delight now gives your Bible reading milestones a permanent place to live.',
            '## What you can earn',
            'Fixed streak achievements for consistent reading',
            '![Delight achievement shelf showing next goals and earned milestones](/images/updates/permanent-achievements-shelf.png)',
            '## Next Milestone on your dashboard',
            'what is the next good thing to aim for?',
            '<img src="/images/updates/permanent-achievements-next-milestone.png" alt="Delight dashboard next milestone card showing progress toward finishing Amos" class="mx-auto max-w-sm rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">',
            '## Already been reading?',
            'so longtime readers do not have to start from zero',
            '[View your achievement shelf](/achievements)',
        ],
        'extra_images' => [
            'images/updates/permanent-achievements-shelf.png',
            'images/updates/permanent-achievements-next-milestone.png',
        ],
        'minimum_widths' => ['images/updates/permanent-achievements-next-milestone.png' => 700],
    ]],
    'start reading plans from where you are' => [[
        'slug' => 'start-reading-plans-from-where-you-are',
        'title' => 'New: Start Reading Plans From Where You Are',
        'hero' => 'images/updates/start-reading-plans-from-where-you-are.png',
        'social' => 'images/updates/start-reading-plans-from-where-you-are.png',
        'starts_at' => '2026-06-09 18:43:03',
        'contains' => [
            '## What changed',
            'Reading Plans no longer have to start from Day 1.',
            '## Why it helps',
            'without restarting or backfilling earlier days',
            '## How it works',
            '- Start from a different passage',
            'without treating earlier days as missed',
            '[Open Reading Plans](/plans)',
        ],
    ]],
    'Mcheyne and Catholic canonical reading plans' => [[
        'slug' => 'mcheyne-and-catholic-canonical-reading-plans',
        'title' => 'New: M’Cheyne and Catholic Canonical Reading Plans',
        'hero' => 'images/updates/mcheyne-and-catholic-canonical-reading-plans.png',
        'social' => 'images/updates/mcheyne-and-catholic-canonical-reading-plans.png',
        'starts_at' => '2026-06-14 12:00:00',
        'contains' => [
            '## What changed',
            'two more one-year reading plans',
            '## M’Cheyne Reading Plan',
            'Old Testament once and the New Testament and Psalms twice',
            '## Catholic Canonical Reading Plan',
            'complete 73-book Catholic Bible',
            'you’ll see this plan after enabling the Catholic 73-book canon in Settings',
            '## How to start',
            'start from Day 1 or choose a later passage',
            '[Open Reading Plans](/plans)',
        ],
        'hero_size' => [1672, 941, 'image/png'],
    ]],
];

it('seeds the :dataset release announcement', function (array $expected) {
    $this->seed(ReleaseAnnouncementsSeeder::class);

    $announcement = Announcement::where('slug', $expected['slug'])->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe($expected['title'])
        ->and($announcement->type)->toBe('info')
        ->and($announcement->hero_image_path)->toBe($expected['hero'])
        ->and($announcement->social_image_path)->toBe($expected['social'])
        ->and($announcement->starts_at->toDateTimeString())->toBe($expected['starts_at'])
        ->and($announcement->ends_at)->toBeNull();

    foreach ($expected['contains'] as $content) {
        expect($announcement->content)->toContain($content);
    }

    foreach ($expected['excludes'] ?? [] as $content) {
        expect($announcement->content)->not->toContain($content);
    }

    foreach ([$expected['hero'], $expected['social'], ...($expected['extra_images'] ?? [])] as $image) {
        expect(file_exists(public_path($image)))->toBeTrue();
    }

    foreach ($expected['minimum_widths'] ?? [] as $image => $minimumWidth) {
        expect(getimagesize(public_path($image))[0])->toBeGreaterThan($minimumWidth);
    }

    if (isset($expected['hero_size'])) {
        $heroImageSize = getimagesize(public_path($expected['hero']));

        expect([$heroImageSize[0], $heroImageSize[1], $heroImageSize['mime']])->toBe($expected['hero_size']);
    }
})->with($releaseAnnouncements);

it('keeps release announcement seeds idempotent', function () use ($releaseAnnouncements) {
    $this->seed(ReleaseAnnouncementsSeeder::class);
    $this->seed(ReleaseAnnouncementsSeeder::class);

    foreach ($releaseAnnouncements as [$expected]) {
        expect(Announcement::where('slug', $expected['slug'])->count())->toBe(1);
    }
});
