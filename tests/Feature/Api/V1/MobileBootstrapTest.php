<?php

use App\Models\ReadingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

const MOBILE_BOOTSTRAP_ENDPOINT = '/api/v1/bootstrap';
const MOBILE_BOOTSTRAP_TODAY = '2026-08-09';
const MOBILE_BOOTSTRAP_YESTERDAY = '2026-08-08';
const MOBILE_BOOTSTRAP_TWO_DAYS_AGO = '2026-08-07';

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse(MOBILE_BOOTSTRAP_TODAY.' 00:30:00', config('app.timezone')));
});

afterEach(function (): void {
    Cache::flush();
    Carbon::setTestNow();
});

it('requires a Sanctum token with the mobile ability', function (): void {
    $this->getJson(MOBILE_BOOTSTRAP_ENDPOINT)->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('Reporting integration', ['reporting'])->plainTextToken;

    $this->withToken($token)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertForbidden();
});

it('returns complete zero-filled bootstrap data in the application timezone', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel', ['mobile'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.today', MOBILE_BOOTSTRAP_TODAY)
        ->assertJsonPath('data.yesterday', MOBILE_BOOTSTRAP_YESTERDAY)
        ->assertJsonPath('data.recent_book_ids', [])
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.streak_state', 'inactive')
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
                'streak_state',
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
        ->and($response->json('data.activity.13'))->toBe(['date' => MOBILE_BOOTSTRAP_TODAY, 'count' => 0]);
});

it('returns exactly the dates accepted by web reading validation', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel', ['mobile'])->plainTextToken;

    $dates = $this->withToken($token)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
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

    $invalidDateResponse = $this->actingAs($user)
        ->post(route('logs.store'), [
            'book_id' => 1,
            'start_chapter' => 3,
            'date_read' => Carbon::parse($dates['yesterday'])->subDay()->toDateString(),
        ])
        ->assertSuccessful()
        ->assertViewHas('errors');

    expect($invalidDateResponse->viewData('errors')->has('date_read'))->toBeTrue()
        ->and($user->readingLogs()->count())->toBe(2);
});

it('returns canon-aware books and filters recent books before limiting', function (): void {
    $user = User::factory()->create();

    createBootstrapReading($user, 67, MOBILE_BOOTSTRAP_TODAY, MOBILE_BOOTSTRAP_TODAY.' 00:20:00');
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 12:00:00');
    createBootstrapReading($user, 40, MOBILE_BOOTSTRAP_TWO_DAYS_AGO, MOBILE_BOOTSTRAP_TWO_DAYS_AGO.' 12:00:00');
    createBootstrapReading($user, 43, '2026-08-06', '2026-08-06 12:00:00');
    createBootstrapReading($user, 19, '2026-08-05', '2026-08-05 12:00:00');

    $standardResponse = $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonCount(66, 'data.books')
        ->assertJsonPath('data.recent_book_ids', [1, 40, 43]);

    expect(collect($standardResponse->json('data.books'))->pluck('id')->all())
        ->not->toContain(67);

    $user->forceFill(['deuterocanonical_books_enabled_at' => now()])->save();
    Cache::flush();
    $this->app['auth']->forgetGuards();

    $deuterocanonicalResponse = $this->withToken($user->createToken('Catholic Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonCount(73, 'data.books')
        ->assertJsonPath('data.recent_book_ids', [67, 1, 40]);

    expect(collect($deuterocanonicalResponse->json('data.books'))->pluck('id')->all())
        ->toContain(67, 73);
});

it('returns the existing statistics and fourteen-day activity counts', function (): void {
    $user = User::factory()->create();

    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_TWO_DAYS_AGO, MOBILE_BOOTSTRAP_TWO_DAYS_AGO.' 08:00:00');
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 08:00:00', 2);
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_TODAY, MOBILE_BOOTSTRAP_TODAY.' 00:10:00', 3);
    createBootstrapReading($user, 40, MOBILE_BOOTSTRAP_TODAY, MOBILE_BOOTSTRAP_TODAY.' 00:20:00');

    $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.has_read_today', true)
        ->assertJsonPath('data.streak_state', 'active')
        ->assertJsonPath('data.current_streak', 3)
        ->assertJsonPath('data.longest_streak', 3)
        ->assertJsonPath('data.this_week_days', 1)
        ->assertJsonPath('data.this_month_days', 3)
        ->assertJsonPath('data.activity.11', ['date' => MOBILE_BOOTSTRAP_TWO_DAYS_AGO, 'count' => 1])
        ->assertJsonPath('data.activity.12', ['date' => MOBILE_BOOTSTRAP_YESTERDAY, 'count' => 1])
        ->assertJsonPath('data.activity.13', ['date' => MOBILE_BOOTSTRAP_TODAY, 'count' => 2]);

    expect(Cache::has("user_current_streak_{$user->id}"))->toBeTrue()
        ->and(Cache::has("user_recent_reading_activity_series_{$user->id}"))->toBeTrue()
        ->and(Cache::has("user_total_reading_days_{$user->id}"))->toBeTrue();
});

it('returns the server-computed warning state only for an unread active streak after 18:00', function (): void {
    Carbon::setTestNow(Carbon::parse(MOBILE_BOOTSTRAP_TODAY.' 18:00:00', config('app.timezone')));

    $user = User::factory()->create();
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 08:00:00');

    $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.streak_state', 'warning');
});

it('returns active instead of warning before 18:00 for an unread active streak', function (): void {
    Carbon::setTestNow(Carbon::parse(MOBILE_BOOTSTRAP_TODAY.' 17:59:00', config('app.timezone')));

    $user = User::factory()->create();
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 08:00:00');

    $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.streak_state', 'active');
});

it('does not return warning after today has been read', function (): void {
    Carbon::setTestNow(Carbon::parse(MOBILE_BOOTSTRAP_TODAY.' 18:00:00', config('app.timezone')));

    $user = User::factory()->create();
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 08:00:00');
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_TODAY, MOBILE_BOOTSTRAP_TODAY.' 12:00:00');

    $this->withToken($user->createToken('Pixel', ['mobile'])->plainTextToken)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.has_read_today', true)
        ->assertJsonPath('data.current_streak', 2)
        ->assertJsonPath('data.streak_state', 'active');
});

it('does not serve previous-day statistics after midnight', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-08 23:59:00', config('app.timezone')));

    $user = User::factory()->create();
    createBootstrapReading($user, 1, MOBILE_BOOTSTRAP_YESTERDAY, MOBILE_BOOTSTRAP_YESTERDAY.' 23:50:00');
    $token = $user->createToken('Pixel', ['mobile'])->plainTextToken;

    $this->withToken($token)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.today', MOBILE_BOOTSTRAP_YESTERDAY)
        ->assertJsonPath('data.activity.13', ['date' => MOBILE_BOOTSTRAP_YESTERDAY, 'count' => 1]);

    Carbon::setTestNow(Carbon::parse(MOBILE_BOOTSTRAP_TODAY.' 00:01:00', config('app.timezone')));
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson(MOBILE_BOOTSTRAP_ENDPOINT)
        ->assertSuccessful()
        ->assertJsonPath('data.today', MOBILE_BOOTSTRAP_TODAY)
        ->assertJsonPath('data.yesterday', MOBILE_BOOTSTRAP_YESTERDAY)
        ->assertJsonPath('data.has_read_today', false)
        ->assertJsonPath('data.this_week_days', 0)
        ->assertJsonPath('data.activity.13', ['date' => MOBILE_BOOTSTRAP_TODAY, 'count' => 0]);
});

function createBootstrapReading(
    User $user,
    int $bookId,
    string $dateRead,
    string $createdAt,
    int $chapter = 1,
): ReadingLog {
    $readingLog = ReadingLog::factory()->for($user)->createOne([
        'book_id' => $bookId,
        'chapter' => $chapter,
        'passage_text' => "Book {$bookId} {$chapter}",
        'date_read' => $dateRead,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    assert($readingLog instanceof ReadingLog);

    return $readingLog;
}
