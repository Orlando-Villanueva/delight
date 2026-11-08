<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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

    $response->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('badgeBaseUrl', asset('images/achievements'))
        ->has('categories', 2)
        ->where('categories.0.key', 'firsts')
        ->where('categories.0.label', 'First milestones')
        ->where('nextGoals.books', fn ($goals): bool => collect($goals)->contains(fn (array $goal): bool => $goal['name'] === 'Genesis'
            && $goal['chaptersRead'] === 46
            && $goal['totalChapters'] === 50
            && $goal['chaptersRemaining'] === 4
            && $goal['missingChapters'] === [7, 19, 28, 41]))
        ->where('nextGoals.progress', fn ($goals): bool => collect($goals)->contains(fn (array $goal): bool => $goal['kind'] === 'longest_streak' && $goal['unit'] === 'days')
            && collect($goals)->contains(fn (array $goal): bool => $goal['kind'] === 'bible_progress' && $goal['unit'] === 'percent'))
        ->where('categories.1.achievements.0.name', 'Completed John')
        ->where('categories.1.achievements.0.description', 'You completed John.')
        ->where('categories.1.achievements.0.icon', 'book-open')
        ->where('categories.1.achievements.0.earnedOn', '2026-05-06')
        ->missing('categories.1.achievements.0.metadata')
        ->missing('categories.1.achievements.0.user_id'));
});

it('shows the first reading date with its passage without repeating the award date', function () {
    $user = User::factory()->create();
    UserAchievement::factory()->for($user)->create([
        'metadata' => ['passage' => 'Genesis 1', 'date_read' => '2025-05-01'],
        'earned_at' => '2026-05-06',
    ]);

    $this->actingAs($user)->get(route('achievements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achievements')
            ->where('categories.0.achievements.0.reading', [
                'passage' => 'Genesis 1',
                'date' => '2025-05-01',
            ])
            ->where('categories.0.achievements.0.earnedOn', '2026-05-06'));
});

it('includes a newly started short book among the closest completion goals', function () {
    $user = User::factory()->create();
    achievement_page_read_chapters($user, 50, 'Philippians', [1]);

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->has('nextGoals.books', 1)
        ->where('nextGoals.books.0.name', 'Philippians')
        ->where('nextGoals.books.0.chaptersRead', 1)
        ->where('nextGoals.books.0.totalChapters', 4)
        ->where('nextGoals.books.0.missingChapters', [2, 3, 4]));
});

it('omits empty categories and incomplete first-reading details', function () {
    $user = User::factory()->create();

    $emptyShelfResponse = $this->actingAs($user)->get(route('achievements.index'));

    $emptyShelfResponse->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('categories', [])
        ->where('latestAchievement', null)
        ->where('nextGoals.books', [])
        ->where('nextGoals.progress', []));

    UserAchievement::factory()->for($user)->create([
        'metadata' => ['passage' => 'Genesis 1'],
    ]);

    $this->actingAs($user)->get(route('achievements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achievements')
            ->where('categories.0.achievements.0.reading', null)
            ->where('categories.0.achievements.0.earnedOn', '2026-05-06'));
});

it('highlights the latest awarded completion even when its reading date is older', function () {
    $user = User::factory()->create();
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:43',
        'category' => 'books',
        'display_name' => 'Completed John',
        'icon' => 'book-open',
        'metadata' => ['book_id' => 43, 'completion_number' => 1],
        'earned_at' => '2026-05-01 10:00:00',
        'completed_on' => '2026-05-01',
    ]);
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:8',
        'category' => 'books',
        'display_name' => 'Completed Ruth',
        'earned_at' => '2026-05-05 10:00:00',
        'completed_on' => '2026-05-05',
    ]);
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'book_completed',
        'context_key' => 'book:43:completion:2',
        'category' => 'books',
        'display_name' => 'Completed John',
        'icon' => 'book-open',
        'metadata' => ['book_id' => 43, 'completion_number' => 2],
        'earned_at' => '2026-05-06 10:00:00',
        'completed_on' => '2026-01-01',
    ]);
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'personal_best_streak',
        'context_key' => 'personal-best:8',
        'earned_at' => '2026-05-07 10:00:00',
    ]);
    UserAchievement::factory()->create(['earned_at' => '2026-05-08 10:00:00']);

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('latestAchievement', [
            'name' => 'Completed John',
            'icon' => 'book-open',
            'earnedOn' => '2026-05-06',
        ]));
});

it('breaks identical award timestamps by the newest award id', function () {
    $user = User::factory()->create();
    UserAchievement::factory()->for($user)->create(['sort_order' => 100]);
    UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'reading_streak_7',
        'context_key' => 'streak:7',
        'category' => 'streaks',
        'sort_order' => 10,
    ]);
    $latestAward = UserAchievement::factory()->for($user)->create([
        'achievement_key' => 'first_month',
        'context_key' => 'reading-days:30',
        'display_name' => 'First 30 reading days',
        'icon' => 'calendar-check',
        'sort_order' => 30,
    ]);

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('latestAchievement.name', 'First 30 reading days'));

    expect(app(AchievementService::class)->getDashboardMilestone($user)['latest']->id)->toBe($latestAward->id);
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

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('categories.0.achievements.0.name', 'Completed Genesis')
        ->where('categories.0.achievements.0.count', 3)
        ->where('categories.0.achievements.0.completions', [
            ['completedOn' => null, 'earnedOn' => '2025-05-01'],
            ['completedOn' => '2026-03-12', 'earnedOn' => '2026-05-06'],
            ['completedOn' => '2026-05-06', 'earnedOn' => '2026-05-06'],
        ])
        ->missing('categories.0.achievements.0.metadata')
        ->missing('categories.0.achievements.0.context_key'));
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
                'category' => $type['key'] === 'testament_completed' ? 'testaments' : 'bible',
                'display_name' => $type['name'],
                'metadata' => [...$type['metadata'], 'completion_number' => $number],
                'earned_at' => '2026-05-06',
                'completed_on' => $number === 1 ? '2025-05-01' : '2026-05-06',
            ]);
        }
    }

    $response = $this->actingAs($user)->get(route('achievements.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Achievements')
        ->where('categories.0.achievements.0.name', 'Completed Old Testament')
        ->where('categories.0.achievements.0.count', 2)
        ->where('categories.1.achievements.0.name', 'Completed Bible')
        ->where('categories.1.achievements.0.count', 2));
});

it('redirects htmx navigation to the full achievements page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('achievements.index'), [
        'HX-Request' => 'true',
    ]);

    $response->assertSuccessful()->assertHeader('HX-Redirect', route('achievements.index'));
});

it('links achievements from navigation and shows a dashboard teaser', function () {
    $user = User::factory()->create();
    achievement_page_completed_john($user);

    app(AchievementService::class)->evaluateAndAward($user);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('milestone.milestone.displayName', '7-day reading streak')
            ->where('milestone.milestone.current', 1)
            ->where('milestone.milestone.target', 7)
            ->where('milestone.latest.displayName', 'Completed John')
            ->where('badgeBaseUrl', asset('images/achievements'))
            ->missing('milestone.latest.id')
            ->missing('milestone.latest.user_id')
            ->has('bookProgress.old_testament.processed_books.0.book_id')
            ->has('bookProgress.old_testament.processed_books.0.chapter_count'));
});

it('shows the first reading milestone on the dashboard for new users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('milestone.milestone.displayName', 'First reading')
            ->where('milestone.milestone.current', 0)
            ->where('milestone.milestone.target', 1)
            ->where('milestone.latest', null));
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
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard', false)
            ->where('milestone.milestone', null)
            ->where('milestone.latest.displayName', '100% Bible progress')
            ->where('milestone.latest.description', 'You completed the Bible by chapters.'));
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
