<?php

use App\Mail\FeedbackReceived;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

test('feedback page is accessible', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('feedback.create'));

    $response->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Feedback')
            ->where('submitUrl', route('feedback.store')))
        ->assertHeader('Cache-Control', 'no-store, private');
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

    $response->assertRedirect(route('feedback.create'));
    $response->assertSessionHas('status', 'Thank you! Your feedback has been sent.');

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

test('legacy feedback navigation redirects into the reader shell', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('feedback.create'), [
        'HX-Request' => 'true',
    ]);

    $response->assertSuccessful()->assertHeader('HX-Redirect', route('feedback.create'));
});

test('feedback sending failure preserves the form and does not announce success', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport unavailable'));
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('feedback.store'), [
        'category' => 'bug',
        'message' => 'Please keep this draft.',
    ])->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors('submission')
        ->assertSessionMissing('status');
});

test('feedback rejects invalid categories and messages over the limit without sending', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create())->post(route('feedback.store'), [
        'category' => 'unknown',
        'message' => str_repeat('a', 5001),
    ])->assertSessionHasErrors(['category', 'message']);
    Mail::assertNothingSent();
});

test('guests cannot submit feedback', function () {
    Mail::fake();
    $this->post(route('feedback.store'), ['category' => 'general', 'message' => 'Hello'])
        ->assertRedirect(route('login'));
    Mail::assertNothingSent();
});
