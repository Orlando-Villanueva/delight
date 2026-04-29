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
        $title = 'New: Deuterocanonical books support';

        $content = <<<'MD'
### What this adds to Delight

You can now opt in to the Catholic 73-book canon from Settings and include the Deuterocanonical books in your reading.

These writings come from the world of ancient Judaism, especially the Second Temple and Hellenistic periods, when many Jewish communities lived across the Greek-speaking Mediterranean. Some of these books were preserved and read in Greek collections of Scripture, especially the Septuagint, which became an important Bible for many early Christians.

Different Christian traditions received these writings differently. Catholic and Orthodox Bibles include them as part of the Old Testament, while most Protestant Bibles place them outside the 66-book canon and often call them the Apocrypha. If you are new to the Bible altogether, think of this as optional support for another historic Christian Bible tradition, not a change to what you already see by default.

They include stories of faithful endurance, wisdom teaching, prayer, and the history of Jewish resistance before the time of Jesus. They also include Greek additions to Esther and Daniel that have long been read in Catholic biblical tradition.

When enabled, Delight adds:
- Tobit, Judith, Wisdom, Sirach, Baruch, 1 Maccabees, and 2 Maccabees
- The Catholic additions to Esther and Daniel
- Progress tracking and book selection for the expanded canon

This setting is off by default, so nothing changes unless you choose to enable it. If you turn it on, Delight will include these books when you log readings and review progress. If you turn it off later, any Deuterocanonical readings you already logged will stay in your history and continue to count toward streaks and weekly goals.

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
