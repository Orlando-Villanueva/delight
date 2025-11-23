<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;

test('welcome email content renders correctly', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $notification = new WelcomeNotification;
    $mailable = $notification->toMail($user);

    // Render the mailable to HTML
    $html = $mailable->render();

    // Verify User Name
    expect($html)->toContain('Welcome to Delight, Test User!');

    // Verify New Links
    expect($html)->toContain(url('/dashboard'));
    expect($html)->toContain(url('/logs'));
    expect($html)->toContain(url('/feedback'));

    // Verify Section Titles
    expect($html)->toContain('Weekly Journey');
    expect($html)->toContain('History Logs');
    expect($html)->toContain('We Value Your Feedback');

    // Verify Absence of Legacy Links
    expect($html)->not->toContain(url('/progress'));
    expect($html)->not->toContain(url('/reading-log')); // Old direct link

    // Verify Layout Elements
    expect($html)->toContain('Start Your Journey');
    // Verify css style of new layout
    expect($html)->toContain('border-radius: 16px'); // New card border radius
    expect($html)->toContain('background: #2563eb'); // New button color
});
