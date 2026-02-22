<?php

use App\Mail\OnboardingReminderEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

it('uses the onboarding reminder subject', function () {
    $user = User::factory()->create();
    $mail = new OnboardingReminderEmail($user);

    expect($mail->envelope()->subject)->toBe('A gentle reminder from Delight');
});

it('renders john 1 suggestion in the template', function () {
    $user = User::factory()->create(['name' => 'Reader']);
    $mail = new OnboardingReminderEmail($user);

    $html = $mail->render();

    expect($html)->toContain('Try John 1 to get started');
});

it('includes the logs create CTA link', function () {
    $user = User::factory()->create();
    $mail = new OnboardingReminderEmail($user);

    $html = $mail->render();

    expect($html)->toContain(route('logs.create'));
});

it('includes a signed unsubscribe URL in template content', function () {
    $user = User::factory()->create();
    $mail = new OnboardingReminderEmail($user);
    $escapedUnsubscribeUrl = e($mail->unsubscribeUrl);

    expect(URL::hasValidSignature(Request::create($mail->unsubscribeUrl, 'GET')))->toBeTrue();
    expect($mail->render())->toContain($escapedUnsubscribeUrl);
});

it('sets the list-unsubscribe header', function () {
    $user = User::factory()->create();
    $mail = new OnboardingReminderEmail($user);

    $headers = $mail->headers();

    expect($headers->text)->toHaveKey('List-Unsubscribe');
    expect($headers->text['List-Unsubscribe'])->toContain($mail->unsubscribeUrl);
});
