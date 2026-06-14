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
        $this->createStartReadingPlansFromWhereYouAreAnnouncement();
        $this->createMcheyneAndCatholicCanonicalReadingPlansAnnouncement();
    }

    /**
     * Create the M'Cheyne and Catholic Canonical reading plans release announcement.
     */
    private function createMcheyneAndCatholicCanonicalReadingPlansAnnouncement(): void
    {
        $title = 'New: M’Cheyne and Catholic Canonical Reading Plans';

        $content = <<<'MD'
## What changed

Delight now includes two more one-year reading plans: the classic M’Cheyne Reading Plan and a Catholic Canonical Reading Plan.

## M’Cheyne Reading Plan

The M’Cheyne plan is a higher-commitment classic with four readings each day.

Over the course of the year, it takes you through the Old Testament once and the New Testament and Psalms twice. It is a strong fit if you want a fuller daily rhythm and do not mind reading from multiple parts of Scripture each day.

## Catholic Canonical Reading Plan

The Catholic Canonical plan reads through the complete 73-book Catholic Bible in traditional canonical order over one year.

Because this plan includes the Deuterocanonical books and the Catholic additions to Esther and Daniel, you’ll see this plan after enabling the Catholic 73-book canon in Settings.

## How to start

Open Reading Plans and choose the plan that fits your season. You can start from Day 1 or choose a later passage if you are already reading alongside a church, group, printed schedule, or another app.

[Open Reading Plans](/plans)
MD;

        Announcement::updateOrCreate(
            ['slug' => 'mcheyne-and-catholic-canonical-reading-plans'],
            [
                'title' => $title,
                'content' => $content,
                'type' => 'info',
                'hero_image_path' => 'images/updates/mcheyne-and-catholic-canonical-reading-plans.png',
                'social_image_path' => 'images/updates/mcheyne-and-catholic-canonical-reading-plans.png',
                'starts_at' => Carbon::create(2026, 6, 14, 12, 0, 0, config('app.timezone')),
                'ends_at' => null,
            ]
        );
    }

    /**
     * Create the start reading plans from where you are release announcement.
     */
    private function createStartReadingPlansFromWhereYouAreAnnouncement(): void
    {
        $title = 'New: Start Reading Plans From Where You Are';

        $content = <<<'MD'
## What changed

Reading Plans no longer have to start from Day 1.

If you are already partway through a Bible reading plan, Delight can now help you continue tracking from the passage where you are. Choose your current passage before starting, and Delight will begin your plan from that point.

## Why it helps

Many people start Bible reading plans with a printed plan, a church schedule, a friend, or another app.

Until now, starting a reading plan in Delight meant beginning from Day 1, even if you were already weeks or months ahead. Now you can pick up where you are without restarting or backfilling earlier days.

## How it works

When you start a reading plan, you can choose:

- Start from Day 1
- Start from a different passage

Delight will begin tracking from the day you choose, without treating earlier days as missed.

[Open Reading Plans](/plans)
MD;

        Announcement::updateOrCreate(
            ['slug' => 'start-reading-plans-from-where-you-are'],
            [
                'title' => $title,
                'content' => $content,
                'type' => 'info',
                'hero_image_path' => 'images/updates/start-reading-plans-from-where-you-are.png',
                'social_image_path' => 'images/updates/start-reading-plans-from-where-you-are.png',
                'starts_at' => Carbon::create(2026, 6, 9, 18, 43, 3, config('app.timezone')),
                'ends_at' => null,
            ]
        );
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
                'starts_at' => Carbon::create(2026, 5, 14, 16, 0, 0, config('app.timezone')),
                'ends_at' => null,
            ]
        );
    }
}
