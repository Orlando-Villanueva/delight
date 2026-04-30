<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class ReleaseAnnouncementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDeuterocanonicalBooksAnnouncement();
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
                'starts_at' => now(),
                'ends_at' => null,
            ]
        );
    }
}
