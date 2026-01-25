<?php

namespace Database\Seeders;

use App\Models\ReadingPlan;
use Illuminate\Database\Seeder;
use Throwable;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Bible configuration loaded from config/bible.php
     */
    private array $bibleConfig;

    /**
     * Abbreviation to book ID lookup (built from config)
     */
    private array $abbreviationToId;

    /**
     * Book names from lang/en/bible.php
     */
    private array $bookNames;

    public function __construct()
    {
        $this->bibleConfig = $this->loadBibleConfig();
        $this->bookNames = $this->loadBookNames();
        $this->abbreviationToId = $this->buildAbbreviationLookup();
    }

    /**
     * Load bible config, preferring Laravel's config helper (respects config:cache).
     * Falls back to direct file include for isolated unit tests.
     */
    private function loadBibleConfig(): array
    {
        // Prefer Laravel's config helper (respects config:cache)
        try {
            if (function_exists('config') && ($config = config('bible.books'))) {
                return $config;
            }
        } catch (Throwable) {
            // Container not bootstrapped (unit tests), fall through to file loading
        }

        // Fallback for unit tests running outside Laravel
        $configPath = __DIR__ . '/../../config/bible.php';
        if (file_exists($configPath)) {
            $config = include $configPath;

            return $config['books'] ?? [];
        }

        return [];
    }

    /**
     * Load book names, preferring Laravel's translation helper.
     * Falls back to direct file include for isolated unit tests.
     */
    private function loadBookNames(): array
    {
        // Prefer Laravel's translation helper (may throw if container not bootstrapped)
        try {
            $names = __('bible.books');
            if (is_array($names)) {
                return $names;
            }
        } catch (Throwable) {
            // Container not bootstrapped (unit tests), fall through to file loading
        }

        // Fallback for unit tests running outside Laravel
        $langPath = __DIR__ . '/../../lang/en/bible.php';
        if (file_exists($langPath)) {
            $lang = include $langPath;

            return $lang['books'] ?? [];
        }

        return [];
    }

    /**
     * Build abbreviation to book ID lookup from config.
     */
    private function buildAbbreviationLookup(): array
    {
        $lookup = [];
        foreach ($this->bibleConfig as $bookId => $book) {
            if (isset($book['abbreviation'])) {
                $lookup[$book['abbreviation']] = $bookId;
            }
        }

        return $lookup;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Canonical Plan
        $this->seedPlan(
            'standard-canonical',
            'Canonical Bible Reading Plan',
            'Read through the Bible in the traditional order, from Genesis to Revelation. Perfect for those who want to experience Scripture as the books appear in your Bible.',
            'standard-canonical.csv'
        );

        // Seed Chronological Plan
        $this->seedPlan(
            'chronological',
            'Chronological Bible Reading Plan',
            'Experience the Bible\'s story as events unfolded in history, from Creation to the Early Church. Provides unique context by following the historical timeline.',
            'chronological.csv'
        );
    }

    /**
     * Seed a single reading plan from a CSV file.
     */
    private function seedPlan(string $slug, string $name, string $description, string $csvFile): void
    {
        $csvPath = database_path("data/reading-plans/{$csvFile}");

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");

            return;
        }

        $days = $this->parseCsv($csvPath);

        ReadingPlan::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'days' => $days,
                'is_active' => true,
            ]
        );

        $this->command->info("Seeded {$name} with " . count($days) . ' days.');
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
     * Parse a single segment like "Gen 1-3" or "Gen 50" or "Jude" (single-chapter book).
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

            $bookName = $this->bookNames[$bookId] ?? "Book {$bookId}";

            // Parse chapter specification (e.g., "1-3" or "50" or "1, 3-5")
            $chapterNums = $this->parseChapterSpec($chapterSpec);

            foreach ($chapterNums as $chapter) {
                $chapters[] = [
                    'book_id' => $bookId,
                    'book_name' => $bookName,
                    'chapter' => $chapter,
                ];
            }

            return $chapters;
        }

        // Fallback: Check if this is a single-chapter book without a chapter number
        $bookId = $this->getBookId(trim($segment));
        if ($bookId !== null && $this->isSingleChapterBook($bookId)) {
            return [
                [
                    'book_id' => $bookId,
                    'book_name' => $this->bookNames[$bookId] ?? "Book {$bookId}",
                    'chapter' => 1,
                ],
            ];
        }

        return $chapters;
    }

    /**
     * Check if a book has only one chapter.
     */
    private function isSingleChapterBook(int $bookId): bool
    {
        return ($this->bibleConfig[$bookId]['chapters'] ?? 0) === 1;
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
        return $this->abbreviationToId[$abbr] ?? null;
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
        $bookName = $this->bookNames[$bookId] ?? "Book {$bookId}";
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

        return $bookName . ' ' . implode(', ', $ranges);
    }
}
