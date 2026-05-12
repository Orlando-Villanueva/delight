<?php

use App\Models\BookProgress;
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
    ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 1,
        'passage_text' => 'John 1',
        'date_read' => today()->toDateString(),
    ]);

    BookProgress::factory()->for($user)->create([
        'book_id' => 43,
        'book_name' => 'John',
        'total_chapters' => 21,
        'chapters_read' => range(1, 21),
        'completion_percent' => 100,
        'is_completed' => true,
        'last_updated' => now(),
    ]);
}

function achievement_page_book_progress(User $user, int $bookId, string $bookName, int $totalChapters, array $chaptersRead): void
{
    BookProgress::factory()->for($user)->create([
        'book_id' => $bookId,
        'book_name' => $bookName,
        'total_chapters' => $totalChapters,
        'chapters_read' => $chaptersRead,
        'completion_percent' => round((count($chaptersRead) / $totalChapters) * 100, 2),
        'is_completed' => count($chaptersRead) >= $totalChapters,
        'last_updated' => now(),
    ]);
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
    achievement_page_book_progress($user, 1, 'Genesis', 50, array_values(array_diff(range(1, 50), [7, 19, 28, 41])));
    achievement_page_book_progress($user, 2, 'Exodus', 40, [1, 2, 3]);

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
        ->assertSee('Missing 7, 19, 28, 41')
        ->assertDontSee('Exodus')
        ->assertDontSee('Latest wins')
        ->assertDontSee('Recently earned achievements')
        ->assertSee('Completed John')
        ->assertSee('images/achievements/badge-book-completed.png')
        ->assertSee('images/achievements/badge-streak.png')
        ->assertSee('Earned May 6, 2026')
        ->assertSee('7-day reading streak')
        ->assertSee('25% Bible progress')
        ->assertSee('In progress')
        ->assertDontSee('Later milestones')
        ->assertDontSee('Weekly consistency')
        ->assertDontSee('4-week target streak')
        ->assertDontSee('Locked');

    expect(substr_count($response->getContent(), 'You completed John.'))->toBe(1);
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

it('advertises the simplified milestone and achievement model on the landing page', function () {
    $response = $this->get('/');
    $content = $response->getContent();

    $response->assertSuccessful()
        ->assertSee('Next Milestone')
        ->assertSee('Next Milestone Guidance')
        ->assertSee('Finish Amos')
        ->assertSee('One chapter completes the book.')
        ->assertSee('8/9')
        ->assertSee('Permanent Achievements')
        ->assertSee('A trophy shelf that never rewinds')
        ->assertSee('Streaks')
        ->assertSee('Books')
        ->assertSee('Progress')
        ->assertSee('from-amber-50 to-white border border-amber-100', false)
        ->assertSee('daily streak, next milestone, summary stats, calendar, and reading progress grid')
        ->assertDontSee('Weekly Journey')
        ->assertDontSee('weekly journey')
        ->assertDontSee('weekly momentum')
        ->assertDontSee('First reading');

    expect($content)
        ->toMatch('/<div role="listitem" class="order-1">.*Daily Reading Log/s')
        ->toMatch('/<div role="listitem" class="order-2">.*Daily Streak Tracking/s')
        ->toMatch('/<div role="listitem" class="order-3">.*Next Milestone/s')
        ->toMatch('/<div role="listitem" class="order-4">.*Book Completion Grid/s')
        ->toMatch('/<div role="listitem" class="order-5">.*Permanent Achievements.*>New<\/span>/s')
        ->toMatch('/<div role="listitem" class="order-6">.*Reading Plans/s');
});
