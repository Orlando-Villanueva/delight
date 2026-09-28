<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-05-06 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function achievement_page_completed_john(User $user): void
{
    foreach (range(1, 21) as $chapter) {
        $user->readingLogs()->firstOrCreate(
            [
                'book_id' => 43,
                'chapter' => $chapter,
                'date_read' => today()->toDateString(),
            ],
            ['passage_text' => "John {$chapter}"]
        );
    }

}

function achievement_page_read_chapters(User $user, int $bookId, string $bookName, array $chaptersRead): void
{
    $readingDate = $user->readingLogs()->orderBy('date_read')->value('date_read') ?? today()->toDateString();

    foreach ($chaptersRead as $chapter) {
        $user->readingLogs()->firstOrCreate(
            [
                'book_id' => $bookId,
                'chapter' => $chapter,
                'date_read' => $readingDate,
            ],
            ['passage_text' => "{$bookName} {$chapter}"]
        );
    }

}

function achievement_page_complete_dashboard_teaser_goals(User $user): void
{
    $definitions = config('achievements.definitions');

    collect([
        ['first_reading', 'first-reading'],
        ['first_month', 'reading-days:30'],
        ['reading_streak_7', 'streak:7'],
        ['reading_streak_30', 'streak:30'],
        ['reading_streak_100', 'streak:100'],
        ['reading_streak_365', 'streak:365'],
        ['bible_progress_25', 'progress:25'],
        ['bible_progress_50', 'progress:50'],
        ['bible_progress_75', 'progress:75'],
        ['bible_progress_100', 'progress:100'],
    ])->each(function (array $achievement) use ($user, $definitions): void {
        [$key, $contextKey] = $achievement;
        $definition = $definitions[$key];

        UserAchievement::factory()->for($user)->create([
            'achievement_key' => $key,
            'context_key' => $contextKey,
            'category' => $definition['category'],
            'display_name' => $definition['display_name'],
            'description' => $definition['description'],
            'icon' => $definition['icon'],
            'style' => $definition['style'],
            'sort_order' => $definition['sort_order'],
            'earned_at' => now()->addSeconds($definition['sort_order']),
        ]);
    });
}

it('requires authentication for the trophy shelf', function () {
    $this->get(route('achievements.index'))->assertRedirect(route('login'));
});

it('renders earned achievements and curated next goals on the trophy shelf', function () {
    $user = User::factory()->create();
    achievement_page_completed_john($user);

    foreach (range(1, 18) as $chapter) {
        $user->readingLogs()->create([
            'book_id' => 43,
            'chapter' => $chapter,
            'passage_text' => "John {$chapter}",
            'date_read' => today()->subDay()->toDateString(),
        ]);
    }

    achievement_page_read_chapters($user, 1, 'Genesis', array_values(array_diff(range(1, 50), [7, 19, 28, 41])));
    achievement_page_read_chapters($user, 2, 'Exodus', [1, 2, 3]);

    app(AchievementService::class)->evaluateAndAward($user);

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertSuccessful()
        ->assertSee('Achievements')
        ->assertSee('Permanent milestones from your Bible reading journey.')
        ->assertSee('Next goals')
        ->assertSee('The closest milestones in your reading journey.')
        ->assertSee('Almost finished')
        ->assertSee('Genesis')
        ->assertSee('46/50 chapters')
        ->assertSee('4 left')
        ->assertSee('Completion 2 · 18/21 chapters · 3 left')
        ->assertSee('Missing 7, 19, 28, 41')
        ->assertDontSee('Exodus')
        ->assertDontSee('Latest wins')
        ->assertDontSee('Recently earned achievements')
        ->assertSee('Completed John')
        ->assertSee('images/achievements/badge-book-completed.png')
        ->assertSee('images/achievements/badge-streak.png')
        ->assertSee('Completed on May 6, 2026')
        ->assertDontSee('View 1 completions')
        ->assertSee('Read on May 5, 2026')
        ->assertSee('7-day reading streak')
        ->assertSee('25% Bible progress')
        ->assertSee('In progress')
        ->assertDontSee('Later milestones')
        ->assertDontSee('Weekly consistency')
        ->assertDontSee('4-week target streak')
        ->assertDontSee('Locked');

    expect(substr_count($response->getContent(), 'You completed John.'))->toBe(1);
});

it('shows the first reading date with its passage without repeating the award date', function () {
    $user = User::factory()->create();
    UserAchievement::factory()->for($user)->create([
        'metadata' => ['passage' => 'Genesis 1', 'date_read' => '2025-05-01'],
        'earned_at' => '2026-05-06',
    ]);

    $this->actingAs($user)->get(route('achievements.index'))
        ->assertSee('Genesis 1 · Read on May 1, 2025')
        ->assertDontSee('Earned May 6, 2026');
});

it('summarizes grouped book completions and keeps their full history available', function () {
    $user = User::factory()->create();

    foreach ([
        ['number' => 1, 'completed_on' => null, 'earned_at' => '2025-05-01'],
        ['number' => 2, 'completed_on' => '2026-03-12', 'earned_at' => '2026-05-06'],
        ['number' => 3, 'completed_on' => '2026-05-06', 'earned_at' => '2026-05-06'],
    ] as $completion) {
        UserAchievement::factory()->for($user)->create([
            'achievement_key' => 'book_completed',
            'context_key' => $completion['number'] === 1
                ? 'book:1'
                : "book:1:completion:{$completion['number']}",
            'category' => 'books',
            'display_name' => 'Completed Genesis',
            'description' => 'You completed Genesis.',
            'metadata' => [
                'book_id' => 1,
                'book_name' => 'Genesis',
                'completion_number' => $completion['number'],
            ],
            'earned_at' => $completion['earned_at'],
            'completed_on' => $completion['completed_on'],
        ]);
    }

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertSee('Latest completion: May 6, 2026')
        ->assertSee('History')
        ->assertSee('aria-label="View all 3 completions for Completed Genesis"', false)
        ->assertSee('data-achievement-history-dialog', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="false"', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('aria-haspopup="dialog"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('Completed Genesis history')
        ->assertSee('data-achievement-history-close', false)
        ->assertSee('data-achievement-history-backdrop', false)
        ->assertDontSee('×3')
        ->assertDontSee('<details', false)
        ->assertSeeInOrder([
            'Completed Genesis',
            'You completed Genesis.',
            'Latest completion: May 6, 2026',
            'History',
            'Completed Genesis history',
            'Earned May 1, 2025',
            'Completed on March 12, 2026',
            'Completed on May 6, 2026',
        ]);

    expect(substr_count($response->getContent(), 'You completed Genesis.'))->toBe(1);
});

it('shows completion histories for grouped testament and Bible awards', function () {
    $user = User::factory()->create();

    foreach ([
        ['key' => 'testament_completed', 'context' => 'testament:old', 'metadata' => ['testament' => 'old'], 'name' => 'Completed Old Testament'],
        ['key' => 'bible_completed', 'context' => 'bible:standard', 'metadata' => ['collection_key' => 'standard'], 'name' => 'Completed Bible'],
    ] as $type) {
        foreach ([1, 2] as $number) {
            UserAchievement::factory()->for($user)->create([
                'achievement_key' => $type['key'],
                'context_key' => "{$type['context']}:completion:{$number}",
                'category' => 'completions',
                'display_name' => $type['name'],
                'metadata' => [...$type['metadata'], 'completion_number' => $number],
                'earned_at' => '2026-05-06',
                'completed_on' => $number === 1 ? '2025-05-01' : '2026-05-06',
            ]);
        }
    }

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertSee('Completed Old Testament')
        ->assertSee('Completed Bible')
        ->assertSee('aria-label="View all 2 completions for Completed Old Testament"', false)
        ->assertSee('aria-label="View all 2 completions for Completed Bible"', false)
        ->assertSee('Completed on May 1, 2025')
        ->assertSee('Latest completion: May 6, 2026');

    expect(substr_count($response->getContent(), 'data-achievement-history-open='))->toBe(2);
});

it('returns the achievements content fragment for htmx navigation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('achievements.index'), [
        'HX-Request' => 'true',
    ]);

    $response->assertSuccessful()
        ->assertSee('Achievements')
        ->assertSee('Permanent milestones from your Bible reading journey.')
        ->assertDontSee('<html', false)
        ->assertDontSee('<!DOCTYPE', false);
});

it('links achievements from navigation and shows a dashboard teaser', function () {
    $user = User::factory()->create();
    achievement_page_completed_john($user);

    app(AchievementService::class)->evaluateAndAward($user);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee(route('achievements.index'))
        ->assertSee('Achievements')
        ->assertSee('Next Milestone')
        ->assertSee('View shelf')
        ->assertSee('images/achievements/badge-streak.png')
        ->assertSee('7-day reading streak')
        ->assertSee('1/7')
        ->assertSee('Latest trophy: <span class="font-medium text-gray-700 dark:text-gray-200">Completed John</span>', false)
        ->assertSeeInOrder(['Daily Streak', 'Next Milestone', 'Days Read'])
        ->assertSee('Best: 1')
        ->assertDontSee('RECORD')
        ->assertDontSee('Weekly Journey')
        ->assertDontSee('First week')
        ->assertDontSee('New longest streak');
});

it('shows the first reading milestone on the dashboard for new users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee('Next Milestone')
        ->assertSee('First reading')
        ->assertSee('0/1')
        ->assertSee('images/achievements/badge-first-reading.png')
        ->assertSeeInOrder(['Daily Streak', 'Next Milestone', 'Days Read'])
        ->assertDontSee('RECORD')
        ->assertDontSee('Weekly Journey');
});

it('falls back to the latest trophy on the dashboard when no milestone remains', function () {
    $user = User::factory()->create();
    ReadingLog::factory()->for($user)->create([
        'book_id' => 1,
        'chapter' => 1,
        'passage_text' => 'Genesis 1',
        'date_read' => today()->subMonth()->toDateString(),
    ]);
    achievement_page_complete_dashboard_teaser_goals($user);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertSee('Next Milestone')
        ->assertSee('Latest trophy: <span class="font-medium text-gray-700 dark:text-gray-200">100% Bible progress</span>', false)
        ->assertSee('You completed the Bible by chapters.')
        ->assertSee('images/achievements/badge-progress.png')
        ->assertDontSee('First milestone');
});

it('advertises achievements alongside the other landing page features', function () {
    $response = $this->get('/');
    $content = $response->getContent();

    $response->assertSuccessful()
        ->assertSee('Next Milestone Guidance')
        ->assertSee('Achievements')
        ->assertSee('Keep the milestones you earn')
        ->assertSee('Streaks')
        ->assertSee('Books')
        ->assertSee('Progress')
        ->assertSee('daily streak, next milestone, summary stats, calendar, and reading progress grid')
        ->assertDontSee('Weekly Journey')
        ->assertDontSee('weekly journey')
        ->assertDontSee('weekly momentum')
        ->assertDontSee('First reading');

    expect($content)
        ->toMatch('/<li class="order-1">.*Daily Reading Log/s')
        ->toMatch('/<li class="order-2">.*Daily Streak Tracking/s')
        ->toMatch('/<li class="order-3">.*Reading Reminders/s')
        ->toMatch('/<li class="order-4">.*Book Completion Grid/s')
        ->toMatch('/<li class="order-5">.*Achievements/s')
        ->toMatch('/<li class="order-6">.*Reading Plans/s');
});

it('renders the landing page when a screenshot asset is missing', function () {
    $path = public_path('images/screenshots/link-preview.png');

    if (! file_exists($path)) {
        $this->get('/')->assertSuccessful();

        return;
    }

    $backupPath = $path.'.'.bin2hex(random_bytes(4)).'.bak';

    rename($path, $backupPath);

    try {
        $response = $this->get('/');

        $response->assertSuccessful()
            ->assertSee('images/screenshots/link-preview.png', false)
            ->assertDontSee('images/screenshots/link-preview.png?v=', false);
    } finally {
        rename($backupPath, $path);
    }
});
