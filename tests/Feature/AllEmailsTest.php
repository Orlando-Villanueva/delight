<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\View;

// Mock for notification classes that might not exist or be hard to instantiate
// We focus on rendering the views directly where possible or mocking the data.

test('welcome email renders with new styles', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    // Render the view directly
    $view = View::make('emails.welcome', ['user' => $user]);
    $html = $view->render();

    expect($html)->toContain('Welcome to Delight, Test User!');
    expect($html)->toContain('class="card"');
    expect($html)->not->toContain('class="notice"');
    expect($html)->toContain('background-color: #f3f4f6'); // New body bg

    // Check for new sections
    expect($html)->toContain('Log Your Reading');
    expect($html)->toContain('Your Dashboard');
});

test('password reset email renders with new styles', function () {
    $resetUrl = 'http://example.com/reset/token';

    $view = View::make('emails.password-reset', ['resetUrl' => $resetUrl]);
    $html = $view->render();

    expect($html)->toContain('Reset Your Password');
    expect($html)->toContain('class="alert alert-warning"');
    expect($html)->not->toContain('class="notice"');
});

test('pwa announcement email renders with new styles', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $view = View::make('emails.pwa-announcement', ['user' => $user]);
    $html = $view->render();

    expect($html)->toContain('Great news, Test User');
    expect($html)->toContain('class="alert alert-success"');
    expect($html)->toContain('class="card"');
    expect($html)->not->toContain('class="notice"');
});

test('weekly target email renders with new styles', function () {
    $view = View::make('emails.weekly-target-announcement');
    $html = $view->render();

    expect($html)->toContain('Introducing Weekly Targets');
    expect($html)->toContain('class="alert alert-success"');
    expect($html)->toContain('class="card"');
    expect($html)->not->toContain('class="notice"');
});

test('feedback received email renders with new styles', function () {
    $data = [
        'user_name' => 'Test User',
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'category' => 'bug',
        'message' => 'This is a test message.'
    ];

    $view = View::make('emails.feedback.received', ['data' => $data]);
    $html = $view->render();

    expect($html)->toContain('New Feedback Received');
    expect($html)->toContain('Test User');
    expect($html)->toContain('class="card"');
    expect($html)->toContain('class="alert alert-info"');
    expect($html)->not->toContain('x-mail::message');
});
