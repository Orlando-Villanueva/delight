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
use Symfony\Component\Process\Process;

it('sends subscriber mail using environment sender overrides with independent default fallbacks', function (
    string|false $updatesAddress,
    string|false $updatesName,
    string $expectedAddress,
    string $expectedName,
) {
    $process = new Process([
        PHP_BINARY,
        '-r',
        'require $argv[1]; $config = require $argv[2]; echo json_encode([
            "from" => $config["from"], "updates_from" => $config["updates_from"],
        ], JSON_THROW_ON_ERROR);',
        base_path('vendor/autoload.php'),
        config_path('mail.php'),
    ], base_path(), [
        'MAIL_FROM_ADDRESS' => 'accounts@example.com',
        'MAIL_FROM_NAME' => 'Account Sender',
        'MAIL_UPDATES_FROM_ADDRESS' => $updatesAddress,
        'MAIL_UPDATES_FROM_NAME' => $updatesName,
    ]);
    $process->mustRun();
    $mailConfig = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    config()->set('mail.from', $mailConfig['from']);
    config()->set('mail.updates_from', $mailConfig['updates_from']);
    $mail = AnnouncementEmail::forTest(Announcement::factory()->make());

    $message = Mail::mailer('array')->to('reader@example.com')->send($mail)->getSymfonySentMessage()->getOriginalMessage();

    expect($message->getFrom()[0]->getAddress())->toBe($expectedAddress)
        ->and($message->getFrom()[0]->getName())->toBe($expectedName);
})->with([
    'both unset' => [false, false, 'accounts@example.com', 'Account Sender'],
    'address only' => ['subscriber@example.com', false, 'subscriber@example.com', 'Account Sender'],
    'name only' => [false, 'Subscriber Sender', 'accounts@example.com', 'Subscriber Sender'],
    'both overridden' => ['subscriber@example.com', 'Subscriber Sender', 'subscriber@example.com', 'Subscriber Sender'],
]);

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
