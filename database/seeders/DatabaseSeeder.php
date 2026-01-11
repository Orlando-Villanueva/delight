<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\BibleReferenceService;
use App\Services\BookProgressSyncService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->createAnnualRecapAnnouncement();

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

        // Sync book progress for seeduser2
        $this->command->info('Syncing book progress for seeduser2...');
        $stats2 = $syncService->syncBookProgressForUser($seedUser2);
        $this->command->info("Synced {$stats2['processed_logs']} reading logs and updated {$stats2['updated_books_count']} books with book progress.");

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

        return User::factory()->create([
            'name' => $name,
            'email' => $email,
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
                'type' => 'success',
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
        $faker = FakerFactory::create();
        $faker->seed(12345);

        $today = Carbon::today();
        $launchDate = Carbon::parse('2025-08-01');
        $bibleService = app(BibleReferenceService::class);

        // Books to bias towards (e.g., Psalms, Matthew, Genesis)
        $favoriteBooks = [19, 40, 1];

        // Calculate days since launch (or 0 if before launch)
        $daysSinceLaunch = max(0, $launchDate->diffInDays($today));

        // Generate logs from launch date to today
        for ($i = 0; $i <= $daysSinceLaunch; $i++) {
            $readingDate = $launchDate->copy()->addDays($i);

            // Skip future dates
            if ($readingDate->gt($today)) {
                break;
            }

            // 70% chance to read on any given day (creates streaks and gaps)
            if ($faker->boolean(70)) {
                $logsForDay = $faker->numberBetween(1, 5); // 1-5 chapters per sitting

                // Occasional "Deep Dive" days (e.g., Sundays)
                if ($readingDate->isSunday()) {
                    $logsForDay = $faker->numberBetween(5, 10);
                }

                $combos = [];
                $attempts = 0;

                while (count($combos) < $logsForDay && $attempts < 50) {
                    $attempts++;

                    // 50% chance to pick a favorite book, otherwise random
                    if ($faker->boolean(50)) {
                        $bookId = $faker->randomElement($favoriteBooks);
                    } else {
                        $bookId = $faker->numberBetween(1, 66);
                    }

                    $maxChapters = $bibleService->getBookChapterCount($bookId);
                    $chapter = $faker->numberBetween(1, $maxChapters);

                    $key = "{$bookId}-{$chapter}";

                    // Check if this specific chapter has been read by user EVER (simulate progress)
                    // For simplicity in seeder, we just check local combos to avoid dups in same day
                    if (! in_array($key, $combos)) {

                        // Check uniqueness against DB to avoid constraint violation if seeding multiple batches
                        $exists = ReadingLog::where('user_id', $user->id)
                            ->where('book_id', $bookId)
                            ->where('chapter', $chapter)
                            ->exists();

                        if (! $exists) {
                            $combos[] = $key;

                            $loggedAt = $readingDate->copy()
                                ->addHours($faker->numberBetween(6, 22))
                                ->addMinutes($faker->numberBetween(0, 59));

                            ReadingLog::create([
                                'user_id' => $user->id,
                                'book_id' => $bookId,
                                'chapter' => $chapter,
                                'passage_text' => $bibleService->formatBibleReference($bookId, $chapter),
                                'date_read' => $readingDate->toDateString(),
                                'notes_text' => $faker->optional(0.2)->sentence(), // 20% chance of notes
                                'created_at' => $loggedAt,
                                'updated_at' => $loggedAt,
                            ]);
                        }
                    }
                }
            }
        }

        $this->command->info("Created reading history from launch date (Aug 1, 2025) for {$user->name}");
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
}
