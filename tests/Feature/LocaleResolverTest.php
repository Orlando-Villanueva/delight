<?php

use App\Models\ReadingLog;
use App\Models\User;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;

it('detects a supported language from the browser preferences', function (string $header, string $expected) {
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $header]);

    expect(app(LocaleResolver::class)->resolve($request))->toBe($expected);
})->with([
    'Canadian French' => ['fr-CA,fr;q=0.9,en;q=0.8', 'fr'],
    'French from France' => ['fr-FR', 'fr'],
    'regional underscore' => ['fr_CA', 'fr'],
    'case insensitive' => ['FR-ca', 'fr'],
    'regional English' => ['en-CA', 'en'],
    'weights outrank header order' => ['en;q=0.5,fr;q=0.9', 'fr'],
    'English preferred over secondary French' => ['en,fr;q=0.8', 'en'],
    'unsupported first language' => ['es-MX,fr;q=0.8,en;q=0.6', 'fr'],
    'zero quality is excluded' => ['fr;q=0,en;q=0.5', 'en'],
    'unsupported languages' => ['es,de;q=0.8', 'en'],
    'wildcard does not select French' => ['*', 'en'],
    'missing preference' => ['', 'en'],
]);

it('uses an explicit browser choice before detected languages', function (mixed $cookie, string $header, string $expected) {
    $request = Request::create('/', cookies: ['delight_locale' => $cookie], server: ['HTTP_ACCEPT_LANGUAGE' => $header]);

    expect(app(LocaleResolver::class)->resolve($request))->toBe($expected);
})->with([
    'French choice on English browser' => ['fr', 'en', 'fr'],
    'English choice on French browser' => ['en', 'fr', 'en'],
    'regional French choice' => ['fr-CA', 'en', 'fr'],
    'unsupported choice is ignored' => ['es', 'fr', 'fr'],
    'empty choice is ignored' => ['', 'fr', 'fr'],
    'non-string choice is ignored' => [['fr'], 'fr', 'fr'],
]);

it('uses the account language before the browser choice and detection', function () {
    $user = User::factory()->make(['preferred_locale' => 'en']);
    $request = Request::create('/', cookies: ['delight_locale' => 'fr'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA']);
    $request->setUserResolver(fn () => $user);

    expect(app(LocaleResolver::class)->resolve($request))->toBe('en');
});

it('resolves a language without saving a preference or changing the application locale', function () {
    $user = User::factory()->create();
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA']);
    $request->setUserResolver(fn () => $user);
    app()->setLocale('en');

    expect(app(LocaleResolver::class)->resolve($request))->toBe('fr');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => null]);
    expect(app()->getLocale())->toBe('en');
});

it('establishes the account language from the visitor context', function (string $header, array $cookies, string $expected) {
    $user = User::factory()->create();
    $request = Request::create('/', cookies: $cookies, server: ['HTTP_ACCEPT_LANGUAGE' => $header]);

    $locale = app(LocaleResolver::class)->establishLocale($user, $request);

    expect($locale)->toBe($expected)
        ->and($user->preferred_locale)->toBe($expected);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => $expected]);
})->with([
    'detected French' => ['fr-CA', [], 'fr'],
    'explicit English choice' => ['fr-CA', ['delight_locale' => 'en'], 'en'],
    'explicit French choice' => ['en', ['delight_locale' => 'fr'], 'fr'],
    'fallback without browser context' => ['', [], 'en'],
    'fallback for unsupported language' => ['es', [], 'en'],
]);

it('preserves the first language when another device has a stale account snapshot', function () {
    $user = User::factory()->create();
    $otherDevice = User::findOrFail($user->id);
    $resolver = app(LocaleResolver::class);
    $resolver->establishLocale($user, Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']));

    $locale = $resolver->establishLocale($otherDevice, Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']));

    expect($locale)->toBe('fr')->and($otherDevice->preferred_locale)->toBe('fr');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'fr']);
});

it('honors a later explicit account choice instead of a previously loaded language', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $otherDevice = User::findOrFail($user->id);
    $user->update(['preferred_locale' => 'en']);
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

    $locale = app(LocaleResolver::class)->establishLocale($otherDevice, $request);

    expect($locale)->toBe('en')->and($otherDevice->preferred_locale)->toBe('en');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'en']);
});

it('keeps reading history and other account preferences intact during language initialization', function () {
    $user = User::factory()->create([
        'reading_timezone' => 'Asia/Tokyo',
        'deuterocanonical_books_enabled_at' => '2026-10-01 12:00:00',
        'daily_reading_reminder_enabled_at' => '2026-10-01 12:00:00',
    ]);
    ReadingLog::factory()->for($user)->create(['date_read' => '2026-11-01', 'notes_text' => 'Private reading note']);
    $account = $user->getAttributes();
    unset($account['preferred_locale'], $account['updated_at']);
    $history = $user->readingLogs()->get()->toArray();

    app(LocaleResolver::class)->establishLocale($user, Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']));

    $this->assertDatabaseHas('users', $account + ['preferred_locale' => 'fr']);
    expect($user->readingLogs()->get()->toArray())->toBe($history);
});
