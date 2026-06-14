<?php

use App\Models\ReadingPlan;
use App\Models\User;
use App\Services\ReadingPlanService;
use Carbon\Carbon;
use Database\Seeders\ReadingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReadingPlanSeeder::class);
});

function seededPlan(string $slug): ReadingPlan
{
    return ReadingPlan::query()->where('slug', $slug)->firstOrFail();
}

function scheduledChapterKeys(ReadingPlan $plan): array
{
    return collect($plan->days)
        ->flatMap(fn (array $day): array => $day['chapters'])
        ->map(fn (array $chapter): string => $chapter['book_id'].':'.$chapter['chapter'])
        ->all();
}

function expectedChapterKeys(array $bookIds, bool $useCatholicChapterTotals = false): array
{
    $books = config('bible.books');

    return collect($bookIds)
        ->flatMap(function (int $bookId) use ($books, $useCatholicChapterTotals): array {
            $chapterCount = $useCatholicChapterTotals
                ? ($books[$bookId]['deuterocanonical_chapters'] ?? $books[$bookId]['chapters'])
                : $books[$bookId]['chapters'];

            return collect(range(1, $chapterCount))
                ->map(fn (int $chapter): string => $bookId.':'.$chapter)
                ->all();
        })
        ->all();
}

it('seeds the MCheyne and Catholic canonical plans as active 365 day plans', function () {
    $mcheyne = seededPlan('mcheyne');
    $catholic = seededPlan('catholic-canonical');

    expect($mcheyne->is_active)->toBeTrue()
        ->and($mcheyne->days)->toHaveCount(365)
        ->and($mcheyne->name)->toContain('M’Cheyne')
        ->and($mcheyne->description)->toContain('four readings')
        ->and($catholic->is_active)->toBeTrue()
        ->and($catholic->days)->toHaveCount(365)
        ->and($catholic->name)->toContain('Catholic')
        ->and($catholic->description)->toContain('73-book');
});

it('contains only complete configured chapters in both plans', function (string $slug, bool $useCatholicChapterTotals) {
    $books = config('bible.books');
    $plan = seededPlan($slug);

    foreach ($plan->days as $day) {
        expect($day['chapters'])->not->toBeEmpty();

        foreach ($day['chapters'] as $chapter) {
            $book = $books[$chapter['book_id']] ?? null;
            $maxChapter = $useCatholicChapterTotals
                ? ($book['deuterocanonical_chapters'] ?? $book['chapters'] ?? 0)
                : ($book['chapters'] ?? 0);

            expect($book)->not->toBeNull()
                ->and($chapter['chapter'])->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual($maxChapter);
        }
    }

    $csv = file_get_contents(database_path("data/reading-plans/{$slug}.csv"));

    expect($csv)->not->toContain(':');
})->with([
    'M’Cheyne' => ['mcheyne', false],
    'Catholic canonical' => ['catholic-canonical', true],
]);

it('covers the complete Catholic canon exactly once in USCCB canonical order', function () {
    $catholicBookOrder = [
        ...range(1, 16), 67, 68, 17, 72, 73,
        ...range(18, 22), 69, 70,
        ...range(23, 25), 71, ...range(26, 66),
    ];

    $actual = scheduledChapterKeys(seededPlan('catholic-canonical'));
    $expected = expectedChapterKeys($catholicBookOrder, useCatholicChapterTotals: true);

    expect($actual)->toBe($expected)
        ->and(array_count_values($actual))->each->toBe(1)
        ->and($actual)->toContain('17:11', '17:16', '27:13', '27:14');
});

it('balances the Catholic canonical plan across three or four chapters per day', function () {
    $dailyChapterCounts = collect(seededPlan('catholic-canonical')->days)
        ->map(fn (array $day): int => count($day['chapters']));

    expect($dailyChapterCounts->min())->toBe(3)
        ->and($dailyChapterCounts->max())->toBe(4);
});

it('preserves the MCheyne once and twice yearly chapter coverage', function () {
    $actualCounts = array_count_values(scheduledChapterKeys(seededPlan('mcheyne')));

    foreach (config('bible.books') as $bookId => $book) {
        if ($bookId > 66) {
            continue;
        }

        $expectedCount = $bookId === 19 || $bookId >= 40 ? 2 : 1;

        foreach (range(1, $book['chapters']) as $chapter) {
            expect($actualCounts[$bookId.':'.$chapter] ?? 0)->toBe($expectedCount);
        }
    }

    expect(array_keys($actualCounts))->toHaveCount(count(expectedChapterKeys(range(1, 66))));
});

it('supports subscribing, pausing, and resuming both seeded plans', function () {
    $user = User::factory()->create(['deuterocanonical_books_enabled_at' => now()]);
    $service = app(ReadingPlanService::class);

    $mcheyneSubscription = $service->subscribe($user, seededPlan('mcheyne'));
    $catholicSubscription = $service->subscribe($user, seededPlan('catholic-canonical'));

    expect($mcheyneSubscription->fresh()->is_active)->toBeFalse()
        ->and($catholicSubscription->fresh()->is_active)->toBeTrue();

    $service->activate($mcheyneSubscription->fresh());

    expect($mcheyneSubscription->fresh()->is_active)->toBeTrue()
        ->and($catholicSubscription->fresh()->is_active)->toBeFalse();
});

it('can log and complete every day of each seeded plan', function (string $slug) {
    $user = User::factory()->create(['deuterocanonical_books_enabled_at' => now()]);
    $plan = seededPlan($slug);
    $service = app(ReadingPlanService::class);
    $subscription = $service->subscribe($user, $plan, startDate: Carbon::parse('2026-01-01'));

    foreach ($plan->days as $day) {
        $date = Carbon::parse('2026-01-01')->addDays($day['day'] - 1);

        foreach ($day['chapters'] as $chapter) {
            $service->logChapter(
                $user,
                $subscription,
                $day['day'],
                $chapter,
                $date,
                resetCache: false,
                evaluateAchievements: false,
            );
        }
    }

    $subscription->resetCompletedDaysCountCache();

    expect($subscription->getCompletedDaysCount())->toBe(365)
        ->and($subscription->getDayNumber())->toBe(365)
        ->and($subscription->getProgress())->toBe(100.0)
        ->and($subscription->isComplete())->toBeTrue();
})->with([
    'M’Cheyne' => 'mcheyne',
    'Catholic canonical' => 'catholic-canonical',
]);
