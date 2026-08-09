<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-08-09 00:30:00', config('app.timezone')));
});

afterEach(function (): void {
    Cache::flush();
    Carbon::setTestNow();
});

it('requires a Sanctum token with the mobile ability', function (): void {
    $this->getJson('/api/v1/bootstrap')->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('Reporting integration', ['reporting'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/bootstrap')
        ->assertForbidden();
});

it('returns complete zero-filled bootstrap data in the application timezone', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel', ['mobile'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/bootstrap')
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.today', '2026-08-09')
        ->assertJsonPath('data.yesterday', '2026-08-08')
        ->assertJsonPath('data.recent_book_ids', [])
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.current_streak', 0)
        ->assertJsonPath('data.longest_streak', 0)
        ->assertJsonPath('data.this_week_days', 0)
        ->assertJsonPath('data.this_month_days', 0)
        ->assertJsonCount(66, 'data.books')
        ->assertJsonCount(14, 'data.activity')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'today',
                'yesterday',
                'books' => ['*' => ['id', 'name', 'chapters', 'testament']],
                'recent_book_ids',
                'has_read_today',
                'current_streak',
                'longest_streak',
                'this_week_days',
                'this_month_days',
                'activity' => ['*' => ['date', 'count']],
            ],
        ]);

    expect($response->json('data.activity'))
        ->toHaveCount(14)
        ->and($response->json('data.activity.0'))->toBe(['date' => '2026-07-27', 'count' => 0])
        ->and($response->json('data.activity.13'))->toBe(['date' => '2026-08-09', 'count' => 0]);
});

it('returns exactly the dates accepted by web reading validation', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel', ['mobile'])->plainTextToken;

    $dates = $this->withToken($token)
        ->getJson('/api/v1/bootstrap')
        ->assertSuccessful()
        ->json('data');

    foreach (['today', 'yesterday'] as $index => $dateKey) {
        $this->actingAs($user)
            ->post(route('logs.store'), [
                'book_id' => 1,
                'start_chapter' => $index + 1,
                'date_read' => $dates[$dateKey],
            ])
            ->assertSuccessful();
    }

    $this->actingAs($user)
        ->post(route('logs.store'), [
            'book_id' => 1,
            'start_chapter' => 3,
            'date_read' => Carbon::parse($dates['yesterday'])->subDay()->toDateString(),
        ])
        ->assertSuccessful();

    expect($user->readingLogs()->count())->toBe(2);
});

it('returns canon-aware books and filters recent books before limiting', function (): void {
    $user = User::factory()->create();

    createBootstrapReading($user, 67, '2026-08-09', '2026-08-09 00:20:00');
    createBootstrapReading($user, 1, '2026-08-08', '2026-08-08 12:00:00');
    createBootstrapReading($user, 40, '2026-08-07', '2026-08-07 12:00:00');
    createBootstrapReading($user, 43, '2026-08-06', '2026-08-06 12:00:00');
    createBootstrapReading($user, 19, '2026-08-05', '2026-08-05 12:00:00');

    $standardResponse = $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/bootstrap')
        ->assertSuccessful()
        ->assertJsonCount(66, 'data.books')
        ->assertJsonPath('data.recent_book_ids', [1, 40, 43]);

    expect(collect($standardResponse->json('data.books'))->pluck('id')->all())
        ->not->toContain(67);

    $user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
    Cache::flush();
    $this->app['auth']->forgetGuards();

    $deuterocanonicalResponse = $this->withToken($user->createToken('Catholic Pixel', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/bootstrap')
        ->assertSuccessful()
        ->assertJsonCount(73, 'data.books')
        ->assertJsonPath('data.recent_book_ids', [67, 1, 40]);

    expect(collect($deuterocanonicalResponse->json('data.books'))->pluck('id')->all())
        ->toContain(67, 73);
});

it('returns the existing dashboard statistics and fourteen-day activity counts', function (): void {
    $user = User::factory()->create();

    createBootstrapReading($user, 1, '2026-08-07', '2026-08-07 08:00:00');
    createBootstrapReading($user, 1, '2026-08-08', '2026-08-08 08:00:00', 2);
    createBootstrapReading($user, 1, '2026-08-09', '2026-08-09 00:10:00', 3);
    createBootstrapReading($user, 40, '2026-08-09', '2026-08-09 00:20:00');

    $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/bootstrap')
        ->assertSuccessful()
        ->assertJsonPath('data.has_read_today', true)
        ->assertJsonPath('data.current_streak', 3)
        ->assertJsonPath('data.longest_streak', 3)
        ->assertJsonPath('data.this_week_days', 1)
        ->assertJsonPath('data.this_month_days', 3)
        ->assertJsonPath('data.activity.11', ['date' => '2026-08-07', 'count' => 1])
        ->assertJsonPath('data.activity.12', ['date' => '2026-08-08', 'count' => 1])
        ->assertJsonPath('data.activity.13', ['date' => '2026-08-09', 'count' => 2]);

    expect(Cache::has("user_dashboard_stats_{$user->id}"))->toBeTrue()
        ->and(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeTrue();
});

function createBootstrapReading(
    User $user,
    int $bookId,
    string $dateRead,
    string $createdAt,
    int $chapter = 1,
): ReadingLog {
    return ReadingLog::factory()->for($user)->create([
        'book_id' => $bookId,
        'chapter' => $chapter,
        'passage_text' => "Book {$bookId} {$chapter}",
        'date_read' => $dateRead,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
