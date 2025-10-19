<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;

class BibleReferenceService
{
    private array $bibleConfig;

    private string $defaultLocale;

    private array $supportedLocales;

    public function __construct(?array $bibleConfig = null)
    {
        // Allow injection of config for testing, otherwise load from Laravel config
        $this->bibleConfig = $bibleConfig ?? $this->loadBibleConfig();
        $this->defaultLocale = $this->bibleConfig['default_locale'] ?? 'en';
        $this->supportedLocales = $this->bibleConfig['supported_locales'] ?? ['en'];
    }

    private function loadBibleConfig(): array
    {
        // Include the config file directly to avoid dependency on Laravel's config system during tests
        $configPath = __DIR__.'/../../config/bible.php';
        if (file_exists($configPath)) {
            return include $configPath;
        }

        // Fallback: try to load from Laravel's config system
        if (function_exists('config')) {
            return config('bible', []);
        }

        return [];
    }

    /**
     * Get Bible book by ID or name
     */
    public function getBibleBook(int|string $identifier, ?string $locale = null): ?array
    {
        $locale = $locale ?? $this->defaultLocale;

        // If identifier is numeric, search by ID
        if (is_numeric($identifier)) {
            $bookId = (int) $identifier;
            if (isset($this->bibleConfig['books'][$bookId])) {
                $book = $this->bibleConfig['books'][$bookId];
                // Return localized name for the requested locale
                $book['name'] = $this->getLocalizedBookName($bookId, $locale);

                return $book;
            }

            return null;
        }

        // If identifier is string, search by name in the specified locale
        foreach ($this->bibleConfig['books'] as $bookId => $book) {
            $translatedName = $this->getLocalizedBookName($bookId, $locale);
            if (strtolower($translatedName) === strtolower($identifier)) {
                // Return localized name for the requested locale
                $book['name'] = $this->getLocalizedBookName($bookId, $locale);

                return $book;
            }
        }

        return null;
    }

    /**
     * Validate Bible reference (book_id and chapter combination)
     */
    public function validateBibleReference(int $bookId, int $chapter): bool
    {
        if (! $this->validateBookId($bookId)) {
            return false;
        }

        return $this->validateChapterNumber($bookId, $chapter);
    }

    /**
     * Format Bible reference for display
     */
    public function formatBibleReference(int $bookId, int $chapter, ?string $locale = null): string
    {
        $locale = $locale ?? $this->defaultLocale;

        if (! $this->validateBookId($bookId)) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        $bookName = $this->getLocalizedBookName($bookId, $locale);

        return "{$bookName} {$chapter}";
    }

    /**
     * Format Bible reference range for display
     */
    public function formatBibleReferenceRange(int $bookId, int $startChapter, int $endChapter, ?string $locale = null): string
    {
        $locale = $locale ?? $this->defaultLocale;

        if (! $this->validateBookId($bookId)) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        $bookName = $this->getLocalizedBookName($bookId, $locale);

        if ($startChapter === $endChapter) {
            return "{$bookName} {$startChapter}";
        }

        return "{$bookName} {$startChapter}-{$endChapter}";
    }

    /**
     * Format an arbitrary list of chapters (may include multiple contiguous ranges).
     */
    public function formatBibleChapterList(int $bookId, array $chapters, ?string $locale = null): string
    {
        $locale = $locale ?? $this->defaultLocale;

        if (! $this->validateBookId($bookId)) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        $normalized = $this->normalizeChapters($chapters);

        if (empty($normalized)) {
            throw new InvalidArgumentException('Chapter list cannot be empty.');
        }

        $bookName = $this->getLocalizedBookName($bookId, $locale);
        $segments = $this->condenseChapterSegments($normalized);

        $formattedSegments = array_map(function (array $segment) {
            if ($segment['start'] === $segment['end']) {
                return (string) $segment['start'];
            }

            return $segment['start'].'-'.$segment['end'];
        }, $segments);

        return $bookName.' '.implode(', ', $formattedSegments);
    }

    /**
     * Validate chapter range for a specific book
     */
    public function validateChapterRange(int $bookId, int $startChapter, int $endChapter): bool
    {
        if (! $this->validateBookId($bookId)) {
            return false;
        }

        if ($startChapter > $endChapter) {
            return false;
        }

        $maxChapters = $this->getBookChapterCount($bookId);

        return $startChapter >= 1 && $endChapter <= $maxChapters;
    }

    /**
     * Parse chapter input (single chapter or range)
     */
    public function parseChapterInput(string $chapterInput): array
    {
        $chapterInput = trim($chapterInput);

        // Check for range (e.g., "1-3")
        if (preg_match('/^(\d+)-(\d+)$/', $chapterInput, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            // Ensure start <= end
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            return [
                'type' => 'range',
                'start' => $start,
                'end' => $end,
                'chapters' => range($start, $end),
            ];
        }

        // Single chapter
        if (preg_match('/^\d+$/', $chapterInput)) {
            $chapter = (int) $chapterInput;

            return [
                'type' => 'single',
                'start' => $chapter,
                'end' => $chapter,
                'chapters' => [$chapter],
            ];
        }

        throw new InvalidArgumentException("Invalid chapter input format: {$chapterInput}");
    }

    /**
     * Get chapter count for a specific book
     */
    public function getBookChapterCount(int $bookId): int
    {
        if (! isset($this->bibleConfig['books'][$bookId])) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        return $this->bibleConfig['books'][$bookId]['chapters'];
    }

    /**
     * List Bible books, optionally filtered by testament
     */
    public function listBibleBooks(?string $testament = null, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $books = [];

        foreach ($this->bibleConfig['books'] as $bookId => $book) {
            // Filter by testament if specified
            if ($testament && $book['testament'] !== $testament) {
                continue;
            }

            $books[] = [
                'id' => $book['id'],
                'name' => $this->getLocalizedBookName($bookId, $locale),
                'chapters' => $book['chapters'],
                'testament' => $book['testament'],
            ];
        }

        return $books;
    }

    /**
     * Validate book ID is within valid range (1-66)
     */
    public function validateBookId(int $bookId): bool
    {
        return $bookId >= 1 && $bookId <= 66 && isset($this->bibleConfig['books'][$bookId]);
    }

    /**
     * Validate chapter number against book-specific limits
     */
    public function validateChapterNumber(int $bookId, int $chapter): bool
    {
        if (! $this->validateBookId($bookId)) {
            return false;
        }

        $maxChapters = $this->getBookChapterCount($bookId);

        return $chapter >= 1 && $chapter <= $maxChapters;
    }

    /**
     * Get localized book name using Laravel's translation system
     */
    public function getLocalizedBookName(int $bookId, ?string $locale = null): string
    {
        $locale = $locale ?? $this->defaultLocale;

        if (! $this->validateBookId($bookId)) {
            throw new InvalidArgumentException("Invalid book ID: {$bookId}");
        }

        // Use Laravel's translation system if available
        $translationKey = "bible.books.{$bookId}";

        // Try to get translation, fallback to English if not found
        if (function_exists('__') && app()->bound('translator')) {
            try {
                $translation = __($translationKey, [], $locale);

                // If translation key is returned unchanged, try fallback locale
                if ($translation === $translationKey && $locale !== $this->defaultLocale) {
                    $translation = __($translationKey, [], $this->defaultLocale);
                }

                // If still no translation, use fallback
                if ($translation === $translationKey) {
                    return $this->getTranslationFallback($bookId, $locale);
                }

                return $translation;
            } catch (Exception $e) {
                // If translation fails, use fallback
                return $this->getTranslationFallback($bookId, $locale);
            }
        }

        // Fallback for testing environment or when translator not available
        return $this->getTranslationFallback($bookId, $locale);
    }

    /**
     * Fallback translation method for testing
     */
    private function getTranslationFallback(int $bookId, string $locale): string
    {
        $translations = [
            'en' => [
                1 => 'Genesis', 2 => 'Exodus', 3 => 'Leviticus', 4 => 'Numbers', 5 => 'Deuteronomy',
                6 => 'Joshua', 7 => 'Judges', 8 => 'Ruth', 9 => '1 Samuel', 10 => '2 Samuel',
                11 => '1 Kings', 12 => '2 Kings', 13 => '1 Chronicles', 14 => '2 Chronicles', 15 => 'Ezra',
                16 => 'Nehemiah', 17 => 'Esther', 18 => 'Job', 19 => 'Psalms', 20 => 'Proverbs',
                21 => 'Ecclesiastes', 22 => 'Song of Solomon', 23 => 'Isaiah', 24 => 'Jeremiah', 25 => 'Lamentations',
                26 => 'Ezekiel', 27 => 'Daniel', 28 => 'Hosea', 29 => 'Joel', 30 => 'Amos',
                31 => 'Obadiah', 32 => 'Jonah', 33 => 'Micah', 34 => 'Nahum', 35 => 'Habakkuk',
                36 => 'Zephaniah', 37 => 'Haggai', 38 => 'Zechariah', 39 => 'Malachi',
                40 => 'Matthew', 41 => 'Mark', 42 => 'Luke', 43 => 'John', 44 => 'Acts',
                45 => 'Romans', 46 => '1 Corinthians', 47 => '2 Corinthians', 48 => 'Galatians', 49 => 'Ephesians',
                50 => 'Philippians', 51 => 'Colossians', 52 => '1 Thessalonians', 53 => '2 Thessalonians', 54 => '1 Timothy',
                55 => '2 Timothy', 56 => 'Titus', 57 => 'Philemon', 58 => 'Hebrews', 59 => 'James',
                60 => '1 Peter', 61 => '2 Peter', 62 => '1 John', 63 => '2 John', 64 => '3 John',
                65 => 'Jude', 66 => 'Revelation',
            ],
            'fr' => [
                1 => 'Genèse', 2 => 'Exode', 3 => 'Lévitique', 4 => 'Nombres', 5 => 'Deutéronome',
                6 => 'Josué', 7 => 'Juges', 8 => 'Ruth', 9 => '1 Samuel', 10 => '2 Samuel',
                11 => '1 Rois', 12 => '2 Rois', 13 => '1 Chroniques', 14 => '2 Chroniques', 15 => 'Esdras',
                16 => 'Néhémie', 17 => 'Esther', 18 => 'Job', 19 => 'Psaumes', 20 => 'Proverbes',
                21 => 'Ecclésiaste', 22 => 'Cantique des Cantiques', 23 => 'Ésaïe', 24 => 'Jérémie', 25 => 'Lamentations',
                26 => 'Ézéchiel', 27 => 'Daniel', 28 => 'Osée', 29 => 'Joël', 30 => 'Amos',
                31 => 'Abdias', 32 => 'Jonas', 33 => 'Michée', 34 => 'Nahum', 35 => 'Habacuc',
                36 => 'Sophonie', 37 => 'Aggée', 38 => 'Zacharie', 39 => 'Malachie',
                40 => 'Matthieu', 41 => 'Marc', 42 => 'Luc', 43 => 'Jean', 44 => 'Actes',
                45 => 'Romains', 46 => '1 Corinthiens', 47 => '2 Corinthiens', 48 => 'Galates', 49 => 'Éphésiens',
                50 => 'Philippiens', 51 => 'Colossiens', 52 => '1 Thessaloniciens', 53 => '2 Thessaloniciens', 54 => '1 Timothée',
                55 => '2 Timothée', 56 => 'Tite', 57 => 'Philémon', 58 => 'Hébreux', 59 => 'Jacques',
                60 => '1 Pierre', 61 => '2 Pierre', 62 => '1 Jean', 63 => '2 Jean', 64 => '3 Jean',
                65 => 'Jude', 66 => 'Apocalypse',
            ],
        ];

        return $translations[$locale][$bookId] ?? $translations[$this->defaultLocale][$bookId] ?? "Book {$bookId}";
    }

    /**
     * Sort and deduplicate a chapter list.
     */
    private function normalizeChapters(array $chapters): array
    {
        $chapters = array_map('intval', $chapters);
        $chapters = array_values(array_unique($chapters));
        sort($chapters);

        return $chapters;
    }

    /**
     * Convert a chapter list into contiguous segment metadata.
     *
     * @return array<int, array{start:int,end:int}>
     */
    private function condenseChapterSegments(array $chapters): array
    {
        if (empty($chapters)) {
            return [];
        }

        $segments = [];
        $segmentStart = $chapters[0];
        $segmentEnd = $chapters[0];

        $total = count($chapters);

        for ($i = 1; $i < $total; $i++) {
            $current = $chapters[$i];

            if ($current === $segmentEnd + 1) {
                $segmentEnd = $current;
                continue;
            }

            $segments[] = ['start' => $segmentStart, 'end' => $segmentEnd];
            $segmentStart = $current;
            $segmentEnd = $current;
        }

        $segments[] = ['start' => $segmentStart, 'end' => $segmentEnd];

        return $segments;
    }

    /**
     * Parse user input into structured Bible reference format
     */
    public function parseBibleReference(string $reference, ?string $locale = null): ?array
    {
        $locale = $locale ?? $this->defaultLocale;

        // Remove extra whitespace and normalize
        $reference = trim($reference);

        // Try to match pattern: "Book Chapter" or "Book Chapter:Verse"
        if (preg_match('/^(.+?)\s+(\d+)(?::(\d+))?$/i', $reference, $matches)) {
            $bookName = trim($matches[1]);
            $chapter = (int) $matches[2];
            $verse = isset($matches[3]) ? (int) $matches[3] : null;

            // Find book by name
            $book = $this->getBibleBook($bookName, $locale);
            if (! $book) {
                return null;
            }

            // Validate chapter
            if (! $this->validateChapterNumber($book['id'], $chapter)) {
                return null;
            }

            return [
                'book_id' => $book['id'],
                'book_name' => $this->getLocalizedBookName($book['id'], $locale),
                'chapter' => $chapter,
                'verse' => $verse,
                'formatted' => $this->formatBibleReference($book['id'], $chapter, $locale),
            ];
        }

        return null;
    }

    /**
     * Get testament information
     */
    public function getTestament(string $testament, ?string $locale = null): ?array
    {
        $locale = $locale ?? $this->defaultLocale;

        if (! isset($this->bibleConfig['testaments'][$testament])) {
            return null;
        }

        $testamentData = $this->bibleConfig['testaments'][$testament];

        // Get localized testament name
        $translationKey = "bible.testaments.{$testament}";
        $name = (function_exists('__') && app()->bound('translator'))
            ? __($translationKey, [], $locale)
            : $this->getTestamentFallback($testament, $locale);

        return [
            'name' => $name,
            'range' => $testamentData['range'],
            'books_count' => $testamentData['range'][1] - $testamentData['range'][0] + 1,
        ];
    }

    /**
     * Fallback for testament names
     */
    private function getTestamentFallback(string $testament, string $locale): string
    {
        $translations = [
            'en' => ['old' => 'Old Testament', 'new' => 'New Testament'],
            'fr' => ['old' => 'Ancien Testament', 'new' => 'Nouveau Testament'],
        ];

        return $translations[$locale][$testament] ?? $translations[$this->defaultLocale][$testament] ?? ucfirst($testament).' Testament';
    }

    /**
     * Get all testaments
     */
    public function listTestaments(?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $testaments = [];

        foreach ($this->bibleConfig['testaments'] as $key => $testament) {
            $testaments[$key] = $this->getTestament($key, $locale);
        }

        return $testaments;
    }

    /**
     * Get supported locales
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if locale is supported
     */
    public function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales);
    }

    /**
     * Get random Bible book for suggestions/demos
     */
    public function getRandomBook(?string $testament = null, ?string $locale = null): array
    {
        $books = $this->listBibleBooks($testament, $locale);

        if (empty($books)) {
            throw new InvalidArgumentException("No books found for testament: {$testament}");
        }

        return $books[array_rand($books)];
    }

    /**
     * Get next/previous book in canonical order
     */
    public function getAdjacentBook(int $bookId, string $direction = 'next'): ?array
    {
        if (! $this->validateBookId($bookId)) {
            return null;
        }

        $targetId = $direction === 'next' ? $bookId + 1 : $bookId - 1;

        if (isset($this->bibleConfig['books'][$targetId])) {
            $book = $this->bibleConfig['books'][$targetId];
            $book['name'] = $this->getLocalizedBookName($targetId);

            return $book;
        }

        return null;
    }

    /**
     * Get books in a testament range
     */
    public function getBooksInTestament(string $testament, ?string $locale = null): array
    {
        $testamentInfo = $this->getTestament($testament, $locale);
        if (! $testamentInfo) {
            return [];
        }

        $books = [];
        [$start, $end] = $testamentInfo['range'];

        for ($i = $start; $i <= $end; $i++) {
            if (isset($this->bibleConfig['books'][$i])) {
                $book = $this->bibleConfig['books'][$i];
                $books[] = [
                    'id' => $book['id'],
                    'name' => $this->getLocalizedBookName($i, $locale),
                    'chapters' => $book['chapters'],
                    'testament' => $book['testament'],
                ];
            }
        }

        return $books;
    }
}
