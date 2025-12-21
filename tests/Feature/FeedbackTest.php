<?php

use App\Mail\FeedbackReceived;
use App\Models\User;
use Illuminate\Support\Facades\Mail;



test('feedback page is accessible', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('feedback.create'));

    $response->assertStatus(200);
    $response->assertSee('Send Feedback');
});

test('feedback page is not accessible to guests', function () {
    $response = $this->get(route('feedback.create'));

    $response->assertRedirect(route('login'));
});

test('feedback can be submitted', function () {
    Mail::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('feedback.store'), [
        'category' => 'feature',
        'message' => 'I want a dark mode toggle.',
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success');

    Mail::assertSent(FeedbackReceived::class, function ($mail) use ($user) {
        return $mail->hasTo(config('mail.admin_address')) &&
            $mail->hasReplyTo($user->email) &&
            $mail->data['user_id'] === $user->id &&
            $mail->data['category'] === 'feature' &&
            $mail->data['message'] === 'I want a dark mode toggle.';
    });
});

test('feedback requires validation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('feedback.store'), [
        'category' => '',
        'message' => '',
    ]);

    $response->assertSessionHasErrors(['category', 'message']);
});

test('feedback page returns partial for HTMX requests', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('feedback.create'), [
        'HX-Request' => 'true',
    ]);

    $response->assertStatus(200);
    $response->assertSee('id="feedback-form-container"', false);
    $response->assertDontSee('<!DOCTYPE html>');
});
