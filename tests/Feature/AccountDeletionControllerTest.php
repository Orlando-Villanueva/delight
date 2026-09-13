<?php

use App\Mail\AccountDeletionRequested;
use App\Mail\AccountDeletionVerification;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\mock;

const GENERIC_DELETION_RESPONSE = 'If this email belongs to a Delight account, we sent a confirmation link. Check your inbox and spam folder.';
const DELETION_SUPPORT_EMAIL = 'orlando@mg.mydelight.app';

function accountDeletionConfirmationUrl(User $user, DateTimeInterface $expiration): string
{
    return URL::temporarySignedRoute(
        'account-deletion.confirm',
        $expiration,
        [
            'user' => $user,
            'email_hash' => hash('sha256', strtolower(trim($user->email))),
        ],
    );
}

test('the account deletion resource is public and explains the request process', function () {
    config(['mail.support_address' => DELETION_SUPPORT_EMAIL]);

    $response = $this->get(route('account-deletion.create'));

    $response
        ->assertOk()
        ->assertSeeText('Request account deletion')
        ->assertSeeText('You do not need the')
        ->assertSeeText('Android app or an active Delight web session.')
        ->assertSeeText('normally completed within 30 days')
        ->assertSeeText(DELETION_SUPPORT_EMAIL)
        ->assertSee('for="email"', false)
        ->assertSee('autocomplete="email"', false);
});

test('a matching email receives an expiring verification link and a generic response', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'reader@example.com']);

    $response = $this->post(route('account-deletion.store'), [
        'email' => ' Reader@Example.com ',
    ]);

    $response
        ->assertRedirectToRoute('account-deletion.create')
        ->assertSessionHas('account_deletion_status', GENERIC_DELETION_RESPONSE);
    Mail::assertSent(AccountDeletionVerification::class, function (AccountDeletionVerification $mail) use ($user) {
        parse_str((string) parse_url($mail->verificationUrl, PHP_URL_QUERY), $query);

        return $mail->hasTo($user->email)
            && ($query['email_hash'] ?? null) === hash('sha256', 'reader@example.com')
            && URL::hasValidSignature(request()->create($mail->verificationUrl));
    });
});

test('an unknown email receives the same generic response without sending mail', function () {
    Mail::fake();

    $response = $this->post(route('account-deletion.store'), [
        'email' => 'unknown@example.com',
    ]);

    $response
        ->assertRedirectToRoute('account-deletion.create')
        ->assertSessionHas('account_deletion_status', GENERIC_DELETION_RESPONSE);
    Mail::assertNothingSent();
});

test('a verification delivery failure does not disclose that the account exists', function () {
    User::factory()->create(['email' => 'reader@example.com']);
    mock(EmailService::class)
        ->shouldReceive('sendWithErrorHandling')
        ->once()
        ->with(Mockery::type(Closure::class), 'account-deletion-verification')
        ->andReturn(false);

    $response = $this->post(route('account-deletion.store'), [
        'email' => 'reader@example.com',
    ]);

    $response
        ->assertRedirectToRoute('account-deletion.create')
        ->assertSessionHas('account_deletion_status', GENERIC_DELETION_RESPONSE);
});

test('the request requires a valid email address', function (string $email) {
    Mail::fake();

    $response = $this->from(route('account-deletion.create'))->post(route('account-deletion.store'), [
        'email' => $email,
    ]);

    $response
        ->assertRedirectToRoute('account-deletion.create')
        ->assertSessionHasErrors(['email' => 'Enter a valid email address.']);
    Mail::assertNothingSent();
})->with(['plain text' => 'not-an-email', 'missing domain' => 'reader@']);

test('the request requires an email address', function () {
    Mail::fake();

    $response = $this->from(route('account-deletion.create'))->post(route('account-deletion.store'));

    $response
        ->assertRedirectToRoute('account-deletion.create')
        ->assertSessionHasErrors([
            'email' => 'Enter the email address associated with your Delight account.',
        ]);
    Mail::assertNothingSent();
});

test('account deletion initiation is rate limited', function () {
    Mail::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('account-deletion.store'), ['email' => 'limited@example.com'])
            ->assertRedirectToRoute('account-deletion.create');
    }

    $this->post(route('account-deletion.store'), ['email' => 'limited@example.com'])
        ->assertTooManyRequests();
    Mail::assertNothingSent();
});

test('a valid signed link opens an explicit confirmation page without submitting the request', function () {
    Mail::fake();
    $user = User::factory()->create();
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());

    $response = $this->get($confirmationUrl);

    $response
        ->assertOk()
        ->assertSeeText('Confirm your deletion request')
        ->assertSeeText('it does not immediately delete any data')
        ->assertSeeText('Confirm deletion request');
    Mail::assertNothingSent();
});

test('an unsigned confirmation link is rejected', function () {
    $user = User::factory()->create();

    $response = $this->get(route('account-deletion.confirm', $user));

    $response->assertForbidden();
});

test('an expired confirmation link is rejected', function () {
    $user = User::factory()->create();
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->subMinute());

    $response = $this->get($confirmationUrl);

    $response->assertForbidden();
});

test('confirming a signed request sends the verified request to monitored support', function () {
    Mail::fake();
    config(['mail.support_address' => DELETION_SUPPORT_EMAIL]);
    $user = User::factory()->create([
        'name' => 'Test Reader',
        'email' => 'reader@example.com',
    ]);
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());

    $response = $this->post($confirmationUrl);

    $response->assertRedirectToRoute('account-deletion.received');
    Mail::assertSent(AccountDeletionRequested::class, function (AccountDeletionRequested $mail) use ($user) {
        return $mail->hasTo(DELETION_SUPPORT_EMAIL)
            && $mail->hasReplyTo($user->email)
            && $mail->userId === $user->id
            && $mail->userName === 'Test Reader'
            && $mail->userEmail === 'reader@example.com';
    });
});

test('reconfirming the same signed request does not send a duplicate support email', function () {
    Mail::fake();
    $user = User::factory()->create();
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());

    $this->post($confirmationUrl)->assertRedirectToRoute('account-deletion.received');
    $response = $this->post($confirmationUrl);

    $response->assertRedirectToRoute('account-deletion.received');
    Mail::assertSentCount(1);
});

test('an already confirmed signed request shows its completed state without another confirmation form', function () {
    Mail::fake();
    $user = User::factory()->create();
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());

    $this->post($confirmationUrl)->assertRedirectToRoute('account-deletion.received');
    $response = $this->get($confirmationUrl);

    $response
        ->assertSeeText('This request has already been confirmed.')
        ->assertDontSee('type="submit"', false);
    Mail::assertSentCount(1);
});

test('a signed confirmation link is rejected after the account email changes', function (string $method) {
    Mail::fake();
    $user = User::factory()->create(['email' => 'original@example.com']);
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());
    $user->update(['email' => 'changed@example.com']);

    $response = $this->{$method}($confirmationUrl);

    $response->assertForbidden();
    Mail::assertNothingSent();
})->with([
    'opening the page' => 'get',
    'submitting the request' => 'post',
]);

test('the received page only confirms a completed verification flow', function () {
    $response = $this->get(route('account-deletion.received'));

    $response->assertRedirectToRoute('account-deletion.create');
});

test('a support delivery failure keeps the verified request retryable', function () {
    $user = User::factory()->create();
    $confirmationUrl = accountDeletionConfirmationUrl($user, now()->addHour());
    mock(EmailService::class)
        ->shouldReceive('sendWithErrorHandling')
        ->once()
        ->with(Mockery::type(Closure::class), 'account-deletion-request')
        ->andReturn(false);

    $response = $this->from($confirmationUrl)->post($confirmationUrl);

    $response
        ->assertRedirect($confirmationUrl)
        ->assertSessionHasErrors([
            'confirmation' => 'We could not record your request right now. Please try again. If the problem continues, contact support.',
        ]);
});

test('account deletion mail escapes requester-provided profile content', function () {
    $mail = new AccountDeletionRequested(
        userId: 42,
        userName: '<script>alert("name")</script>',
        userEmail: 'reader@example.com',
    );

    $mail->assertSeeInHtml('<script>')
        ->assertDontSeeInHtml('<script>alert("name")</script>', false);
});
