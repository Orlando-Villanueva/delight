<?php

namespace Database\Seeders;

use App\Models\ReadingPlan;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Book abbreviation to book_id mapping.
     */
    private array $bookAbbreviations = [
        'Gen' => 1,
        'Ex' => 2,
        'Lev' => 3,
        'Num' => 4,
        'Deut' => 5,
        'Josh' => 6,
        'Jdg' => 7,
        'Rut' => 8,
        '1 Sa' => 9,
        '2 Sa' => 10,
        '1 Kgs' => 11,
        '2 Kgs' => 12,
        '1 Chr' => 13,
        '2 Chr' => 14,
        'Ezr' => 15,
        'Neh' => 16,
        'Est' => 17,
        'Job' => 18,
        'Ps' => 19,
        'Pro' => 20,
        'Ecc' => 21,
        'Sos' => 22,
        'Isa' => 23,
        'Jer' => 24,
        'Lam' => 25,
        'Eze' => 26,
        'Dan' => 27,
        'Hos' => 28,
        'Joe' => 29,
        'Amo' => 30,
        'Oba' => 31,
        'Jon' => 32,
        'Mic' => 33,
        'Nah' => 34,
        'Hab' => 35,
        'Zep' => 36,
        'Hag' => 37,
        'Zec' => 38,
        'Mal' => 39,
        'Mat' => 40,
        'Mk' => 41,
        'Luk' => 42,
        'John' => 43,
        'Acts' => 44,
        'Rom' => 45,
        '1 Co' => 46,
        '2 Co' => 47,
        'Gal' => 48,
        'Eph' => 49,
        'Phil' => 50,
        'Col' => 51,
        '1 Th' => 52,
        '2 Th' => 53,
        '1 Ti' => 54,
        '2 Ti' => 55,
        'Tit' => 56,
        'Phlm' => 57,
        'Heb' => 58,
        'Jam' => 59,
        '1 Pe' => 60,
        '2 Pe' => 61,
        '1 Jn' => 62,
        '2 Jn' => 63,
        '3 Jn' => 64,
        'Jude' => 65,
        'Rev' => 66,
    ];

    /**
     * Full book names from lang/en/bible.php
     */
    private array $bookNames = [
        1 => 'Genesis',
        2 => 'Exodus',
        3 => 'Leviticus',
        4 => 'Numbers',
        5 => 'Deuteronomy',
        6 => 'Joshua',
        7 => 'Judges',
        8 => 'Ruth',
        9 => '1 Samuel',
        10 => '2 Samuel',
        11 => '1 Kings',
        12 => '2 Kings',
        13 => '1 Chronicles',
        14 => '2 Chronicles',
        15 => 'Ezra',
        16 => 'Nehemiah',
        17 => 'Esther',
        18 => 'Job',
        19 => 'Psalms',
        20 => 'Proverbs',
        21 => 'Ecclesiastes',
        22 => 'Song of Solomon',
        23 => 'Isaiah',
        24 => 'Jeremiah',
        25 => 'Lamentations',
        26 => 'Ezekiel',
        27 => 'Daniel',
        28 => 'Hosea',
        29 => 'Joel',
        30 => 'Amos',
        31 => 'Obadiah',
        32 => 'Jonah',
        33 => 'Micah',
        34 => 'Nahum',
        35 => 'Habakkuk',
        36 => 'Zephaniah',
        37 => 'Haggai',
        38 => 'Zechariah',
        39 => 'Malachi',
        40 => 'Matthew',
        41 => 'Mark',
        42 => 'Luke',
        43 => 'John',
        44 => 'Acts',
        45 => 'Romans',
        46 => '1 Corinthians',
        47 => '2 Corinthians',
        48 => 'Galatians',
        49 => 'Ephesians',
        50 => 'Philippians',
        51 => 'Colossians',
        52 => '1 Thessalonians',
        53 => '2 Thessalonians',
        54 => '1 Timothy',
        55 => '2 Timothy',
        56 => 'Titus',
        57 => 'Philemon',
        58 => 'Hebrews',
        59 => 'James',
        60 => '1 Peter',
        61 => '2 Peter',
        62 => '1 John',
        63 => '2 John',
        64 => '3 John',
        65 => 'Jude',
        66 => 'Revelation',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('data/reading-plans/standard-canonical.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");

            return;
        }

        $days = $this->parseCsv($csvPath);

        ReadingPlan::updateOrCreate(
            ['slug' => 'standard-canonical'],
            [
                'name' => 'Read the Bible in a Year',
                'description' => 'A 365-day journey through the entire Bible in canonical order. Perfect for establishing a consistent daily reading habit.',
                'days' => $days,
                'is_active' => true,
            ]
        );

        $this->command->info('Seeded Standard Canonical reading plan with '.count($days).' days.');
    }

    /**
     * Parse the CSV file into structured day data.
     */
    private function parseCsv(string $path): array
    {
        $days = [];
        $handle = fopen($path, 'r');

        // Skip header row
        fgetcsv($handle);

        $dayNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $passage = trim($row[1]);
            $chapters = $this->parsePassage($passage);
            $label = $this->formatLabel($chapters);

            $days[] = [
                'day' => $dayNumber,
                'label' => $label,
                'chapters' => $chapters,
            ];

            $dayNumber++;
        }

        fclose($handle);

        return $days;
    }

    /**
     * Parse a passage string like "Gen 1-3" or "Gen 50; Ex 1-3" into chapter arrays.
     */
    private function parsePassage(string $passage): array
    {
        $chapters = [];

        // Split by semicolon for multiple book segments
        $segments = array_map('trim', explode(';', $passage));

        foreach ($segments as $segment) {
            $parsed = $this->parseSegment($segment);
            $chapters = array_merge($chapters, $parsed);
        }

        return $chapters;
    }

    /**
     * Parse a single segment like "Gen 1-3" or "Gen 50".
     */
    private function parseSegment(string $segment): array
    {
        $chapters = [];

        // Match pattern: "Book ChapterRange" (e.g., "Gen 1-3" or "Gen 50")
        if (preg_match('/^(.+?)\s+(\d+(?:-\d+)?(?:,\s*\d+(?:-\d+)?)*)$/', $segment, $matches)) {
            $bookAbbr = trim($matches[1]);
            $chapterSpec = $matches[2];

            $bookId = $this->getBookId($bookAbbr);

            if ($bookId === null) {
                return [];
            }

            $bookName = $this->bookNames[$bookId];

            // Parse chapter specification (e.g., "1-3" or "50" or "1, 3-5")
            $chapterNums = $this->parseChapterSpec($chapterSpec);

            foreach ($chapterNums as $chapter) {
                $chapters[] = [
                    'book_id' => $bookId,
                    'book_name' => $bookName,
                    'chapter' => $chapter,
                ];
            }
        }

        return $chapters;
    }

    /**
     * Parse chapter specification like "1-3" or "50".
     */
    private function parseChapterSpec(string $spec): array
    {
        $chapters = [];

        // Split by comma for multiple ranges
        $parts = array_map('trim', explode(',', $spec));

        foreach ($parts as $part) {
            if (str_contains($part, '-')) {
                // Range: "1-3"
                [$start, $end] = explode('-', $part);
                for ($i = (int) $start; $i <= (int) $end; $i++) {
                    $chapters[] = $i;
                }
            } else {
                // Single: "50"
                $chapters[] = (int) $part;
            }
        }

        return $chapters;
    }

    /**
     * Get book ID from abbreviation.
     */
    private function getBookId(string $abbr): ?int
    {
        return $this->bookAbbreviations[$abbr] ?? null;
    }

    /**
     * Format a human-readable label with full book names.
     */
    private function formatLabel(array $chapters): string
    {
        if (empty($chapters)) {
            return '';
        }

        $segments = [];
        $currentBook = null;
        $currentChapters = [];

        foreach ($chapters as $chapter) {
            if ($currentBook !== $chapter['book_id']) {
                // Save previous segment
                if ($currentBook !== null) {
                    $segments[] = $this->formatBookChapters($currentBook, $currentChapters);
                }
                $currentBook = $chapter['book_id'];
                $currentChapters = [$chapter['chapter']];
            } else {
                $currentChapters[] = $chapter['chapter'];
            }
        }

        // Save last segment
        if ($currentBook !== null) {
            $segments[] = $this->formatBookChapters($currentBook, $currentChapters);
        }

        return implode('; ', $segments);
    }

    /**
     * Format book name with chapter range.
     */
    private function formatBookChapters(int $bookId, array $chapters): string
    {
        $bookName = $this->bookNames[$bookId];
        sort($chapters);

        // Condense consecutive chapters into ranges
        $ranges = [];
        $start = null;
        $end = null;

        foreach ($chapters as $ch) {
            if ($start === null) {
                $start = $end = $ch;
            } elseif ($ch === $end + 1) {
                $end = $ch;
            } else {
                $ranges[] = $start === $end ? (string) $start : "{$start}-{$end}";
                $start = $end = $ch;
            }
        }

        if ($start !== null) {
            $ranges[] = $start === $end ? (string) $start : "{$start}-{$end}";
        }

        return $bookName.' '.implode(', ', $ranges);
    }
}
