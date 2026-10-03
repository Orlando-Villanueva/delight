<?php

use App\Mail\AccountDeletionVerification;
use App\Mail\AnnouncementEmail;
use App\Mail\ChurnRecoveryEmail;
use App\Mail\OnboardingReminderEmail;
use App\Mail\PasswordResetMail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('sends subscriber mail from the configured updates sender and preserves unsubscribe headers', function (string $type) {
    config()->set('mail.from', ['address' => 'noreply@mydelight.app', 'name' => 'Delight Accounts']);
    config()->set('mail.updates_from', ['address' => 'updates@mydelight.app', 'name' => 'Delight Updates']);
    $user = User::factory()->create();
    $mail = match ($type) {
        'announcement' => new AnnouncementEmail(
            Announcement::factory()->create(),
            $user,
            AnnouncementEmailDelivery::factory()->make(['message_id' => 'sender-test@mydelight.app']),
        ),
        'onboarding' => new OnboardingReminderEmail($user),
        default => new ChurnRecoveryEmail($user, (int) $type),
    };

    $message = Mail::mailer('array')->to($user)->send($mail)->getSymfonySentMessage()->getOriginalMessage();

    expect($message->getFrom())->toHaveCount(1)
        ->and($message->getFrom()[0]->getAddress())->toBe('updates@mydelight.app')
        ->and($message->getFrom()[0]->getName())->toBe('Delight Updates')
        ->and($message->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toBe("<{$mail->oneClickUnsubscribeUrl}>")
        ->and($message->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString())->toBe('List-Unsubscribe=One-Click');
})->with(['announcement', 'onboarding', '1', '2', '3']);

it('uses the updates sender for announcement previews without subscriber unsubscribe headers', function () {
    config()->set('mail.updates_from', ['address' => 'updates@mydelight.app', 'name' => 'Delight']);
    $mail = AnnouncementEmail::forTest(Announcement::factory()->make());

    $message = Mail::mailer('array')->to('reader@example.com')->send($mail)->getSymfonySentMessage()->getOriginalMessage();

    expect($message->getFrom()[0]->getAddress())->toBe('updates@mydelight.app')
        ->and($message->getSubject())->toStartWith('[TEST] ')
        ->and($message->getHeaders()->has('List-Unsubscribe'))->toBeFalse();
});

it('keeps account mail on the default sender', function (string $type) {
    config()->set('mail.from', ['address' => 'noreply@mydelight.app', 'name' => 'Delight Accounts']);
    config()->set('mail.updates_from', ['address' => 'updates@mydelight.app', 'name' => 'Delight Updates']);
    $mail = $type === 'password-reset'
        ? new PasswordResetMail('https://mydelight.app/reset-password/test-token')
        : new AccountDeletionVerification('https://mydelight.app/account/delete/test-token');

    $message = Mail::mailer('array')->to('reader@example.com')->send($mail)->getSymfonySentMessage()->getOriginalMessage();

    expect($message->getFrom())->toHaveCount(1)
        ->and($message->getFrom()[0]->getAddress())->toBe('noreply@mydelight.app')
        ->and($message->getFrom()[0]->getName())->toBe('Delight Accounts')
        ->and($message->getHeaders()->has('List-Unsubscribe'))->toBeFalse();
})->with(['password-reset', 'account-deletion']);
