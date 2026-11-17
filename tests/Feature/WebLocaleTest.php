<?php

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('initializes a reader once and keeps the account language on another browser', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withHeader('Accept-Language', 'fr-CA')->get('/settings')
        ->assertOk()->assertHeader('Content-Language', 'fr')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('reader.locale', 'fr')->where('values.preferred_locale', 'fr')->etc());

    $this->withHeader('Accept-Language', 'en')->get('/settings')->assertOk()->assertHeader('Content-Language', 'fr');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'fr']);
});

it('persists an explicit language change without changing the calendar', function () {
    $user = User::factory()->create(['preferred_locale' => 'en', 'reading_timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)->patch('/settings', ['preferred_locale' => 'fr'])->assertRedirect('/settings');

    $this->get('/settings')->assertOk()->assertHeader('Content-Language', 'fr');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'fr', 'reading_timezone' => 'Asia/Tokyo']);
});

it('does not initialize locale or touch the profile on a no-op non-GET request', function () {
    $user = User::factory()->create(['preferred_locale' => null]);
    $updatedAt = $user->fresh()->updated_at;

    $this->actingAs($user)->withHeader('Accept-Language', 'fr-CA')->patch('/settings', [])->assertRedirect('/settings');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'preferred_locale' => null,
        'updated_at' => $updatedAt,
    ]);
});

it('rejects unsupported settings languages without changing other preferences', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    $this->actingAs($user)->patch('/settings', ['preferred_locale' => 'es', 'daily_reading_reminder_enabled' => true])
        ->assertSessionHasErrors('preferred_locale');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'en', 'daily_reading_reminder_enabled_at' => null]);
});

it('establishes the new account language before the welcome notification', function () {
    Notification::fake();

    $this->withHeader('Accept-Language', 'fr-CA')->post('/register', [
        'name' => 'Lectrice', 'email' => 'lectrice@example.test',
        'password' => 'VeryUniquePass123!', 'password_confirmation' => 'VeryUniquePass123!',
    ])->assertRedirect('/dashboard');

    $user = User::where('email', 'lectrice@example.test')->firstOrFail();
    expect($user->preferredLocale())->toBe('fr');
    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('saves a guest page language without creating an account', function () {
    $this->post('/language', ['locale' => 'fr', 'return_to' => '/register'])
        ->assertRedirect('/register')->assertCookie('delight_locale', 'fr');
    $this->assertDatabaseCount('users', 0);
});

it('rejects external language redirect targets', function (string $target) {
    $this->post('/language', ['locale' => 'fr', 'return_to' => $target])
        ->assertSessionHasErrors('return_to')->assertCookieMissing('delight_locale');
})->with(['https://example.test', '//example.test', '/\\example.test']);

it('does not initialize reader language from administrative access', function () {
    config(['mail.admin_address' => 'admin@example.test']);
    $user = User::factory()->create(['email' => 'admin@example.test']);

    $this->actingAs($user)->withHeader('Accept-Language', 'fr')->get('/admin');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => null]);
});

it('initializes a new Google account in the page language', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'fr-google-reader', 'email' => 'reader@gmail.com', 'name' => 'Lecteur',
    ]));

    $this->withCookie('delight_locale', 'fr')->withHeader('Accept-Language', 'en')
        ->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertDatabaseHas('users', ['email' => 'reader@gmail.com', 'preferred_locale' => 'fr']);
});

it('keeps an existing Google account language despite another browser language', function () {
    $user = User::factory()->create(['email' => 'reader@gmail.com', 'preferred_locale' => 'en']);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'en-google-reader', 'email' => 'reader@gmail.com', 'name' => 'Reader',
    ]));

    $this->withHeader('Accept-Language', 'fr')->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'en']);
});
