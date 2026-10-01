<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\BibleReferenceService;
use App\Services\BookProgressSyncService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->createAnnualRecapAnnouncement();
        $this->call(ReleaseAnnouncementsSeeder::class);

        $this->call(ReadingPlanSeeder::class);

        $seedUser = $this->getOrCreateSeedUser('Seed User', 'seed.user@example.com');

        // Create varied reading logs for testing filters
        $this->createTestReadingLogs($seedUser);

        // Sync book progress with the seeded reading logs
        $this->command->info('Syncing book progress for seeded reading logs...');
        $syncService = app(BookProgressSyncService::class);
        $stats = $syncService->syncBookProgressForUser($seedUser);
        $this->command->info("Synced {$stats['processed_logs']} reading logs and updated {$stats['updated_books_count']} books with book progress.");

        // Create test user with 3 reading days this week
        $seedUser2 = $this->getOrCreateSeedUser('Seed User 2', 'seed.user2@example.com');

        $this->createCurrentWeekTestData($seedUser2);

        // Create brand-new user with no readings yet
        $newUser = $this->getOrCreateSeedUser('New Seed User', 'seed.user.new@example.com');
        $this->command->info("Created new seed user with no readings: {$newUser->email}");

        // Sync book progress for seeduser2
        $this->command->info('Syncing book progress for seeduser2...');
        $stats2 = $syncService->syncBookProgressForUser($seedUser2);
        $this->command->info("Synced {$stats2['processed_logs']} reading logs and updated {$stats2['updated_books_count']} books with book progress.");

        $this->seedAchievementRecords([$seedUser, $seedUser2]);

        // Clear all caches to ensure fresh statistics
        $this->command->info('Clearing application caches...');
        cache()->flush();
        $this->command->info('All caches cleared.');
    }

    private function getOrCreateSeedUser(string $name, string $email): User
    {
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $existingUser->update(['name' => $name]);

            return $existingUser;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'avatar_url' => null,
        ]);
    }

    /**
     * Create the Annual Recap release announcement.
     */
    private function createAnnualRecapAnnouncement(): void
    {
        $title = 'Your 2025 Annual Recap is here';

        $content = <<<'MD'
### Your 2025 Annual Recap is ready

Relive your first year with Delight with:
- Your reader style and top books
- Total chapters read and active days
- Best streak and books completed
- A daily activity heatmap

Your recap will keep updating through December 31, 2025 - keep reading to shape the final story.

[View your recap](/recap/2025)

Thank you for reading with us - here's to 2026!
MD;

        Announcement::updateOrCreate(
            ['slug' => 'annual-recap-2025'],
            [
                'title' => $title,
                'content' => $content,
                'starts_at' => now(),
                'ends_at' => null,
            ]
        );
    }

    /**
     * Create reading data from launch date onwards.
     * Includes bias towards specific books to generate clear "Top Books".
     */
    private function createTestReadingLogs(User $user): void
    {
        $random = new Randomizer(new Mt19937(12345));

        $today = Carbon::today();
        $launchDate = Carbon::parse('2025-08-01');
        $bibleService = app(BibleReferenceService::class);

        // Follow a small, readable selection instead of scattering chapters across the Bible.
        $sampleBookOrder = [19, 40, 43, 20, 45, 50, 32];
        $chaptersReadByBook = $user->readingLogs()
            ->get(['book_id', 'chapter'])
            ->groupBy('book_id')
            ->map(fn ($readings): array => $readings
                ->pluck('chapter')
                ->map(fn ($chapter): int => (int) $chapter)
                ->unique()
                ->values()
                ->all())
            ->all();
        $sampleBookIndex = 0;

        // Calculate days since launch (or 0 if before launch)
        $daysSinceLaunch = max(0, $launchDate->diffInDays($today));

        // Generate logs from launch date to today
        for ($i = 0; $i <= $daysSinceLaunch; $i++) {
            $readingDate = $launchDate->copy()->addDays($i);

            // Skip future dates
            if ($readingDate->gt($today)) {
                break;
            }

            // Model a steady but imperfect reading habit with plenty of room for growth.
            if ($random->getInt(1, 100) <= 25) {
                $logsForDay = $random->getInt(1, 2); // 1-2 chapters per sitting

                // Occasional "Deep Dive" days (e.g., Sundays)
                if ($readingDate->isSunday()) {
                    $logsForDay = $random->getInt(2, 4);
                }

                while (isset($sampleBookOrder[$sampleBookIndex])
                    && count($chaptersReadByBook[$sampleBookOrder[$sampleBookIndex]] ?? [])
                        >= $bibleService->getBookChapterCount($sampleBookOrder[$sampleBookIndex])) {
                    $sampleBookIndex++;
                }

                if (! isset($sampleBookOrder[$sampleBookIndex])) {
                    break;
                }

                $bookId = $sampleBookOrder[$sampleBookIndex];
                $chaptersRead = $chaptersReadByBook[$bookId] ?? [];
                $remainingChapters = array_values(array_diff(
                    range(1, $bibleService->getBookChapterCount($bookId)),
                    $chaptersRead,
                ));
                $chaptersForDay = array_slice($remainingChapters, 0, $logsForDay);

                foreach ($chaptersForDay as $chapter) {
                    $loggedAt = $readingDate->copy()
                        ->addHours($random->getInt(6, 22))
                        ->addMinutes($random->getInt(0, 59));

                    ReadingLog::create([
                        'user_id' => $user->id,
                        'book_id' => $bookId,
                        'chapter' => $chapter,
                        'passage_text' => $bibleService->formatBibleReference($bookId, $chapter),
                        'date_read' => $readingDate->toDateString(),
                        'notes_text' => $random->getInt(1, 100) <= 20
                            ? 'Reflecting on '.$bibleService->formatBibleReference($bookId, $chapter).'.'
                            : null,
                        'created_at' => $loggedAt,
                        'updated_at' => $loggedAt,
                    ]);

                    $chaptersReadByBook[$bookId][] = $chapter;
                }
            }
        }

        $this->ensureSampleBookCompletions($user, $bibleService);

        $this->command->info("Created reading history from launch date (Aug 1, 2025) for {$user->name}");
    }

    /**
     * Ensure the primary seed reader has representative single and repeat book completions.
     */
    private function ensureSampleBookCompletions(User $user, BibleReferenceService $bibleService): void
    {
        $bookCompletionTargets = [
            1 => 2,
            2 => 1,
        ];
        $chapterDates = $user->readingLogs()
            ->whereIn('book_id', array_keys($bookCompletionTargets))
            ->get(['book_id', 'chapter', 'date_read'])
            ->groupBy(fn (ReadingLog $reading): string => "{$reading->book_id}:{$reading->chapter}")
            ->map(fn ($readings): array => $readings
                ->pluck('date_read')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique()
                ->values()
                ->all());

        foreach ($bookCompletionTargets as $bookId => $targetOccurrences) {
            $chapterCount = $bibleService->getBookChapterCount($bookId);

            for ($chapter = 1; $chapter <= $chapterCount; $chapter++) {
                $chapterKey = "{$bookId}:{$chapter}";
                $dates = $chapterDates->get($chapterKey, []);

                for ($occurrence = count($dates); $occurrence < $targetOccurrences; $occurrence++) {
                    $startOffsetDays = match ($bookId) {
                        1 => [270, 120][$occurrence],
                        2 => 200,
                    };
                    $readingDate = Carbon::today()
                        ->subDays($startOffsetDays)
                        ->addDays(intdiv($chapter - 1, 2) * 2);

                    while (in_array($readingDate->toDateString(), $dates, true)) {
                        $readingDate->subDay();
                    }

                    $dateString = $readingDate->toDateString();
                    $loggedAt = $readingDate->copy()->setTime(12, 0);

                    ReadingLog::create([
                        'user_id' => $user->id,
                        'book_id' => $bookId,
                        'chapter' => $chapter,
                        'passage_text' => $bibleService->formatBibleReference($bookId, $chapter),
                        'date_read' => $dateString,
                        'created_at' => $loggedAt,
                        'updated_at' => $loggedAt,
                    ]);

                    $dates[] = $dateString;
                }

                $chapterDates->put($chapterKey, $dates);
            }
        }

        $this->command->info("Ensured sample book completions for {$user->name}.");
    }

    /**
     * Create test data for the most recent week with 3 reading days (goal not achieved yet).
     * If a target day would land in the future, shift it back one week.
     */
    private function createCurrentWeekTestData(User $user): void
    {
        $today = Carbon::today();
        $currentWeekStart = $today->copy()->startOfWeek(Carbon::SUNDAY);

        // Create 3 reading logs in current week (Sunday, Tuesday, Thursday)
        $readingLogs = [
            [
                'book_id' => 19,
                'chapter' => 1,
                'passage_text' => 'Psalms 1',
                'date' => $currentWeekStart->copy(), // Sunday
                'notes' => 'Blessed is the man who walks not in the counsel of the wicked.',
            ],
            [
                'book_id' => 40,
                'chapter' => 5,
                'passage_text' => 'Matthew 5',
                'date' => $currentWeekStart->copy()->addDays(2), // Tuesday
                'notes' => 'The Beatitudes - Blessed are the poor in spirit.',
            ],
            [
                'book_id' => 43,
                'chapter' => 3,
                'passage_text' => 'John 3',
                'date' => $currentWeekStart->copy()->addDays(4), // Thursday
                'notes' => 'For God so loved the world that he gave his one and only Son.',
            ],
        ];

        foreach ($readingLogs as $logData) {
            $readingDate = $logData['date']->copy();

            if ($readingDate->gt($today)) {
                $readingDate->subWeek();
            }

            $loggedAt = $readingDate->copy()->addHours(2)->addMinutes(30);

            if ($user->readingLogs()
                ->where('book_id', $logData['book_id'])
                ->where('chapter', $logData['chapter'])
                ->whereDate('date_read', $readingDate->toDateString())
                ->exists()) {
                continue;
            }

            ReadingLog::create([
                'user_id' => $user->id,
                'book_id' => $logData['book_id'],
                'chapter' => $logData['chapter'],
                'passage_text' => $logData['passage_text'],
                'date_read' => $readingDate->toDateString(),
                'notes_text' => $logData['notes'],
                'created_at' => $loggedAt,
                'updated_at' => $loggedAt,
            ]);
        }

        $this->command->info("Created recent week test data for {$user->name}:");
        $this->command->info('- 3 reading days in the most recent week (goal not achieved yet)');
        $this->command->info('- Weekly goal remains below target until a 4th reading is added');
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedAchievementRecords(array $users): void
    {
        $achievementService = app(AchievementService::class);

        foreach ($users as $user) {
            if (! $user->readingLogs()->exists()) {
                continue;
            }

            $achievementService->evaluateAndAward($user);
        }

        $this->command->info('Prepared achievement records for seeded users.');
    }
}
