<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('preserves web Google sign-in and records a compatible stable subject', function (): void {
    $user = User::factory()->create(['email' => 'reader@gmail.com']);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'email' => $user->email,
        'name' => 'Delight Reader',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->google_subject)->toBe('google-subject-1');
});

it('normalizes the web Google email before resolving an existing user', function (): void {
    $user = User::factory()->create(['email' => 'Reader@Example.com']);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'email' => 'READER@EXAMPLE.COM',
        'name' => 'Delight Reader',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    expect(User::query()->count())->toBe(1)
        ->and($user->fresh()->google_subject)->toBe('google-subject-1');
});

it('returns to login when the shared Google identity lock times out', function (): void {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'email' => 'reader@example.com',
        'name' => 'Delight Reader',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $emailLock = Cache::lock(
        'google-mobile-identity-email-lock:'.hash_hmac(
            'sha256',
            'reader@example.com',
            (string) config('app.key')
        ),
        30
    );

    expect($emailLock->get())->toBeTrue();

    Sleep::fake(syncWithCarbon: true);

    try {
        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
    } finally {
        $emailLock->release();
    }

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('rejects a web Google subject that is already bound to a different email owner', function (): void {
    $subjectOwner = User::factory()->create([
        'email' => 'subject-owner@gmail.com',
        'google_subject' => 'google-subject-1',
    ]);
    $emailOwner = User::factory()->create(['email' => 'reader@gmail.com']);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'email' => $emailOwner->email,
        'name' => 'Delight Reader',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    $this->assertGuest();

    expect($subjectOwner->fresh()->google_subject)->toBe('google-subject-1')
        ->and($emailOwner->fresh()->google_subject)->toBeNull();
});

it('rejects a web Google subject that conflicts with an existing email binding', function (): void {
    $user = User::factory()->create([
        'email' => 'reader@gmail.com',
        'google_subject' => 'google-subject-1',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-2',
        'email' => $user->email,
        'name' => 'Delight Reader',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    $this->assertGuest();

    expect($user->fresh()->google_subject)->toBe('google-subject-1');
});
