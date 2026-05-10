<?php

use App\Models\BookProgress;
use App\Models\ReadingLog;
use App\Models\User;
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
        ->assertSee('Trophy Shelf')
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
        ->assertDontSee('Locked');

    expect(substr_count($response->getContent(), 'You completed John.'))->toBe(1);
});

it('returns the achievements content fragment for htmx navigation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('achievements.index'), [
        'HX-Request' => 'true',
    ]);

    $response->assertSuccessful()
        ->assertSee('Trophy Shelf')
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
        ->assertSee('Latest trophy')
        ->assertSee('images/achievements/badge-book-completed.png')
        ->assertSee('Completed John');
});
