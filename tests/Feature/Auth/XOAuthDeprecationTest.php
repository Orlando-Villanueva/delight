<?php

use Illuminate\Support\Facades\Route;

it('does not expose X OAuth routes', function () {
    expect(Route::has('x.redirect'))->toBeFalse()
        ->and(Route::has('x.callback'))->toBeFalse();

    $this->get('/auth/x/redirect')->assertNotFound();
    $this->get('/auth/x/callback')->assertNotFound();
});

it('does not show X-specific messaging on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSeeText('Forgot your password?')
        ->assertSee(route('password.request'), false)
        ->assertSeeText('Continue with Google')
        ->assertDontSeeText('Used X before?')
        ->assertDontSeeText('X account email')
        ->assertDontSeeText('Continue with X');
});

it('does not offer X sign-in on the registration page', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSeeText('Continue with Google')
        ->assertDontSeeText('Continue with X');
});
