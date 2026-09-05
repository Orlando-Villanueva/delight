<?php

use App\Models\Announcement;

it('reports publication state consistently with the published scope', function (array $attributes, bool $expected) {
    $this->travelTo('2026-09-03 12:00:00');
    $announcement = Announcement::factory()->create($attributes);

    expect($announcement->isPublished())->toBe($expected)
        ->and(Announcement::published()->whereKey($announcement->getKey())->exists())->toBe($expected);
})->with([
    'draft after proposed start' => [
        ['is_draft' => true, 'starts_at' => '2026-09-03 11:00:00'],
        false,
    ],
    'scheduled for the future' => [
        ['is_draft' => false, 'starts_at' => '2026-09-03 13:00:00'],
        false,
    ],
    'published without a start time' => [
        ['is_draft' => false, 'starts_at' => null],
        true,
    ],
    'published at its start time' => [
        ['is_draft' => false, 'starts_at' => '2026-09-03 12:00:00'],
        true,
    ],
    'published but expired' => [
        [
            'is_draft' => false,
            'starts_at' => '2026-09-03 11:00:00',
            'ends_at' => '2026-09-03 11:30:00',
        ],
        true,
    ],
]);
