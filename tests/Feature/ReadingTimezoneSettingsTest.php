<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ReadingCalendarService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;

it('shows the saved timezone alongside other preferences in the same accessible settings form', function () {
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $html = $this->actingAs($user)->get(route('settings.edit'))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//label[@for="reading_timezone"]')->item(0)->textContent)->toContain('Time zone');
    expect($xpath->query('//select[@name="reading_timezone"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('Asia/Tokyo');
    expect($xpath->query('//select[@name="reading_timezone"]/ancestor::form//input[@name="daily_reading_reminder_enabled" and @type="checkbox"]')->length)->toBe(1);
    expect($xpath->query('//input[@name="push_notification_timezone"]')->length)->toBe(0);
});

it('requires authentication to correct the reading timezone', function () {
    $this->patchJson(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])->assertUnauthorized();
});

it('corrects the account timezone without changing history or other preferences', function () {
    $user = User::factory()->create([
        'reading_timezone' => 'America/Toronto',
        'push_notification_timezone' => 'America/Toronto',
        'daily_reading_reminder_enabled_at' => now()->subDay(),
        'deuterocanonical_books_enabled_at' => now()->subDay(),
    ]);
    $log = ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-09']);
    $originalLog = $log->fresh()->getRawOriginal();
    $originalUser = $user->getRawOriginal();

    $this->actingAs($user)->patch(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])
        ->assertRedirect(route('settings.edit'))->assertSessionHas('status', 'Settings saved.');

    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
    expect($user->fresh()->getRawOriginal('daily_reading_reminder_enabled_at'))->toBe($originalUser['daily_reading_reminder_enabled_at']);
    expect($user->fresh()->getRawOriginal('deuterocanonical_books_enabled_at'))->toBe($originalUser['deuterocanonical_books_enabled_at']);
    expect($log->fresh()->getRawOriginal())->toBe($originalLog);

    $this->withHeader('X-Reading-Timezone', 'America/Toronto')->get(route('dashboard'))->assertOk();
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
});

it('rejects invalid timezone corrections without modifying the account', function ($timezone) {
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => $timezone])
        ->assertUnprocessable()->assertJsonValidationErrors('reading_timezone');
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
})->with(['unknown' => 'Not/AZone', 'offset' => '+09:00', 'empty' => '', 'null' => null, 'array' => [['Asia/Tokyo']]]);

it('does not write the account again when saving the same timezone', function () {
    $this->travelTo(Carbon::parse('2026-09-10 12:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $updatedAt = $user->getRawOriginal('updated_at');
    $this->travel(5)->minutes();
    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])
        ->assertOk()->assertJsonPath('reading_timezone', 'Asia/Tokyo');
    expect($user->fresh()->getRawOriginal('updated_at'))->toBe($updatedAt);
});

it('refreshes cached streaks when correcting the timezone and switching back', function () {
    $this->travelTo(Carbon::parse('2026-09-10 01:00:00', 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-08']);
    $stats = app(UserStatisticsService::class);
    expect($stats->getStreakStatistics($user)['current_streak'])->toBe(1);

    $this->actingAs($user)->patchJson(route('settings.update'), ['reading_timezone' => 'Asia/Tokyo'])->assertOk();
    expect($stats->getStreakStatistics($user->fresh())['current_streak'])->toBe(0);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-09-09']);

    $this->patchJson(route('settings.update'), ['reading_timezone' => 'America/Toronto'])->assertOk();
    expect($stats->getStreakStatistics($user->fresh())['current_streak'])->toBe(2);
});

it('keeps an explicit timezone when older reminder forms report a different browser timezone', function (string $route, string $field) {
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $this->actingAs($user)->patchJson(route($route), [$field => 'America/Toronto', 'daily_reading_reminder_enabled' => true])
        ->assertOk()->assertJsonPath('push_notification_timezone', 'Asia/Tokyo');
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
    expect($user->fresh()->push_notification_timezone)->toBeNull();
})->with([
    'settings form' => ['settings.update', 'push_notification_timezone'],
    'reminder preferences' => ['push.preferences.update', 'timezone'],
]);

it('does not overwrite the account timezone when connecting another browser', function () {
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    $this->actingAs($user)->postJson(route('push.subscriptions.store'), [
        'endpoint' => 'https://example.com/tokyo-browser',
        'keys' => ['p256dh' => str_repeat('a', 88), 'auth' => str_repeat('b', 24)],
        'timezone' => 'America/Toronto',
    ])->assertOk()->assertJsonPath('push_notification_timezone', 'Asia/Tokyo');
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
    expect($user->fresh()->push_notification_timezone)->toBeNull();
});

it('rejects invalid timezone changes through the calendar service', function () {
    $user = User::factory()->create(['reading_timezone' => 'Asia/Tokyo']);
    expect(fn () => app(ReadingCalendarService::class)->changeTimezone($user, '+09:00'))
        ->toThrow(InvalidArgumentException::class);
    expect($user->fresh()->reading_timezone)->toBe('Asia/Tokyo');
});

it('labels timezones with their current offset including daylight saving and fractional hours', function (string $date, string $torontoOffset) {
    $this->travelTo(Carbon::parse($date, 'UTC'));
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);
    $this->actingAs($user)->get(route('settings.edit'))->assertOk()
        ->assertSee('Toronto — America/Toronto (UTC'.$torontoOffset.')')
        ->assertSee('Tokyo — Asia/Tokyo (UTC+09:00)')
        ->assertSee('Kathmandu — Asia/Kathmandu (UTC+05:45)');
})->with([
    'summer' => ['2026-07-10 12:00:00', '-04:00'],
    'winter' => ['2026-01-10 12:00:00', '-05:00'],
]);

it('saves all account preferences together with an explicit form submission', function () {
    $user = User::factory()->create(['reading_timezone' => 'America/Toronto']);

    $this->actingAs($user)->patch(route('settings.update'), [
        'reading_timezone' => 'Asia/Tokyo',
        'include_deuterocanonical' => '1',
        'daily_reading_reminder_enabled' => '1',
        'streak_warning_enabled' => '0',
    ])->assertRedirect(route('settings.edit'))->assertSessionHas('status', 'Settings saved.');

    $user->refresh();
    expect($user->reading_timezone)->toBe('Asia/Tokyo');
    expect($user->includesDeuterocanonicalBooks())->toBeTrue();
    expect($user->hasDailyReadingReminderEnabled())->toBeTrue();
    expect($user->hasStreakWarningEnabled())->toBeFalse();
});

it('preserves submitted choices and changes no preferences when the form is invalid', function () {
    $user = User::factory()->create([
        'reading_timezone' => 'America/Toronto',
        'deuterocanonical_books_enabled_at' => null,
        'daily_reading_reminder_enabled_at' => null,
        'streak_warning_enabled_at' => null,
    ]);
    $input = [
        'reading_timezone' => 'Invalid/Zone',
        'include_deuterocanonical' => '1',
        'daily_reading_reminder_enabled' => '1',
        'streak_warning_enabled' => '1',
    ];

    $this->actingAs($user)->from(route('settings.edit'))->patch(route('settings.update'), $input)
        ->assertRedirect(route('settings.edit'))->assertSessionHasErrors('reading_timezone')
        ->assertSessionHasInput('include_deuterocanonical', '1')
        ->assertSessionHasInput('daily_reading_reminder_enabled', '1');

    $user->refresh();
    expect($user->reading_timezone)->toBe('America/Toronto');
    expect($user->includesDeuterocanonicalBooks())->toBeFalse();
    expect($user->hasDailyReadingReminderEnabled())->toBeFalse();
    expect($user->hasStreakWarningEnabled())->toBeFalse();
});
