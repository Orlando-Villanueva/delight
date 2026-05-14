<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReleaseAnnouncementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDeuterocanonicalBooksAnnouncement();
        $this->createPermanentAchievementsAnnouncement();
    }

    /**
     * Create the Deuterocanonical books release announcement.
     */
    private function createDeuterocanonicalBooksAnnouncement(): void
    {
        $title = 'New: Deuterocanonical Book Support';

        $content = <<<'MD'
## What changed

You can now enable the Catholic 73-book canon from Settings. When enabled, Delight includes the Deuterocanonical books in reading logs, book selection, and progress tracking.

Your current reading experience does not change unless you turn this setting on.

## Why it is optional

Different Christian traditions receive these writings differently. Catholic and Orthodox Bibles include them as part of the Old Testament, while most Protestant Bibles do not include them in the 66-book canon and often refer to them as the Apocrypha.

Delight keeps the 66-book canon as the default while adding opt-in support for readers who use the Catholic canon.

## Included books

When enabled, Delight adds:
- Tobit, Judith, Wisdom, Sirach, Baruch, 1 Maccabees, and 2 Maccabees
- The Catholic additions to Esther and Daniel
- Progress tracking and book selection for the expanded canon

## Your history stays intact

If you turn this setting off later, any Deuterocanonical readings you already logged will stay in your history and continue to count toward streaks and weekly goals.

[Open Settings](/settings)
MD;

        Announcement::updateOrCreate(
            ['slug' => 'deuterocanonical-books-release'],
            [
                'title' => $title,
                'content' => $content,
                'type' => 'info',
                'hero_image_path' => 'images/deuterocanonical-books-hero.jpg',
                'social_image_path' => 'images/deuterocanonical-books-social.jpg',
                'starts_at' => Carbon::create(2026, 4, 30, 12, 0, 0),
                'ends_at' => null,
            ]
        );
    }

    /**
     * Create the Permanent Achievements release announcement.
     */
    private function createPermanentAchievementsAnnouncement(): void
    {
        $title = 'New: Permanent Achievements';

        $content = <<<'MD'
## What changed

Delight now gives your Bible reading milestones a permanent place to live. As you log readings, the app can award achievements for meaningful moments like your first reading, steady reading-day milestones, completed books, Bible progress, and longer streaks.

These achievements stay on your shelf even as your next goal changes.

## What you can earn

The new achievement shelf includes:
- First reading and reading-day milestones
- Fixed streak achievements for consistent reading
- Completed book achievements
- Bible progress achievements
- Testament completion achievements

The goal is simple: celebrate the progress you have actually made without turning your reading life into a scoreboard.

![Delight achievement shelf showing next goals and earned milestones](/images/updates/permanent-achievements-shelf.png)

## Next Milestone on your dashboard

Your dashboard now highlights a useful next milestone, such as a nearly finished book, a Bible progress threshold, or a meaningful streak marker.

It is meant to answer one quiet question: what is the next good thing to aim for?

<img src="/images/updates/permanent-achievements-next-milestone.png" alt="Delight dashboard next milestone card showing progress toward finishing Amos" class="mx-auto max-w-sm rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">

## Already been reading?

If you already have reading history in Delight, your past progress can count. Existing logs can be backfilled into the new achievement system, so longtime readers do not have to start from zero.

[View your achievement shelf](/achievements)
MD;

        Announcement::updateOrCreate(
            ['slug' => 'permanent-achievements-release'],
            [
                'title' => $title,
                'content' => $content,
                'type' => 'info',
                'hero_image_path' => 'images/permanent-achievements-release.png',
                'social_image_path' => 'images/permanent-achievements-release.png',
                'starts_at' => Carbon::create(2026, 5, 14, 8, 0, 0, config('app.timezone')),
                'ends_at' => null,
            ]
        );
    }
}
