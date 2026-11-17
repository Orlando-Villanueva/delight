<?php

use App\Mail\ChurnRecoveryEmail;
use App\Mail\OnboardingReminderEmail;
use App\Mail\PasswordResetMail;
use App\Models\ReadingLog;
use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\ReadingReminderPushNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('renders French public entry, guide, and legal pages with a guest language selector', function (string $url, string $copy) {
    $this->withHeader('Accept-Language', 'fr-CA')->get($url)
        ->assertOk()
        ->assertHeader('Content-Language', 'fr')
        ->assertSee($copy)
        ->assertSee('Langue', false)
        ->assertSee('Français', false)
        ->assertSee('English', false);
})->with([
    'landing' => ['/', 'Poursuivez votre lecture de la Bible'],
    'paper Bible guide' => ['/guides/track-bible-reading-with-a-paper-bible', 'Comment suivre vos lectures avec une Bible papier'],
    'privacy policy' => ['/privacy-policy', 'Politique de confidentialité'],
    'terms' => ['/terms-of-service', 'Conditions d’utilisation'],
]);

it('shows the language selector alongside both authentication options', function (string $url) {
    $response = $this->withHeader('Accept-Language', 'fr')->get($url)
        ->assertOk()
        ->assertSee('Continuer avec Google')
        ->assertSee('ou continuer avec votre adresse courriel')
        ->assertSee('Langue', false)
        ->assertSee(route('oauth.google.redirect'), false)
        ->assertSee(route($url === '/login' ? 'login' : 'register'), false);

    expect(substr_count($response->getContent(), 'aria-label="Langue"'))->toBe(1);
})->with(['/login', '/register']);

it('does not show a language control on public pages whose locale is deferred', function () {
    $this->withHeader('Accept-Language', 'fr')->get('/updates')
        ->assertOk()
        ->assertDontSee('aria-label="Langue"', false);
});

it('keeps a French recipient on the French reset screen after opening a link in an English browser', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $notification = new CustomResetPasswordNotification('opaque-token');
    $resetUrl = $notification->resetUrl($user);

    expect($resetUrl)->toContain('locale=fr');

    $response = $this->withHeader('Accept-Language', 'en')->get($resetUrl);

    $response->assertOk()
        ->assertHeader('Content-Language', 'fr')
        ->assertSee('Choisir un nouveau mot de passe')
        ->assertSee('Langue', false)
        ->assertCookie('delight_locale', 'fr');
    $returnTo = '/reset-password/opaque-token?email='.rawurlencode($user->email);
    $response->assertSee('value="'.e($returnTo).'"', false);

    $this->post('/language', ['locale' => 'en', 'return_to' => $returnTo])
        ->assertRedirect($returnTo)
        ->assertCookie('delight_locale', 'en');

    $this->withCookie('delight_locale', 'en')->get($returnTo)
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertSee('Set new password');

    $this->from($resetUrl)->withCookie('delight_locale', 'fr')->post('/reset-password', [
        'token' => 'opaque-token', 'email' => $user->email,
        'password' => '', 'password_confirmation' => '',
    ])->assertSessionHasErrors(['password']);

    $this->withCookie('delight_locale', 'fr')->get($resetUrl)
        ->assertOk()
        ->assertSee('Le champ mot de passe est obligatoire.');

    expect(app()->getLocale())->toBe('en');
});

it('uses the signed marketing recipient language without changing account preferences', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $url = URL::signedRoute('marketing.unsubscribe', ['user' => $user], now()->addHour());

    $this->withCookie('delight_locale', 'en')->get($url)
        ->assertOk()
        ->assertHeader('Content-Language', 'fr')
        ->assertSee('Préférences de courriel')
        ->assertSee('Voulez-vous vraiment vous désabonner');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_locale' => 'fr']);
});

it('localizes lifecycle email subjects and bodies using each recipient preference', function () {
    app()->setLocale('en');
    $user = User::factory()->create(['name' => 'Lectrice', 'preferred_locale' => 'fr']);

    $welcome = (new WelcomeNotification)->toMail($user);
    expect($welcome->subject)->toBe('Bienvenue sur Delight!');
    app()->setLocale('fr');
    expect(view($welcome->view, $welcome->viewData)->render())->toContain('Nous sommes heureux de vous accueillir.');
    app()->setLocale('en');

    $onboarding = new OnboardingReminderEmail($user);
    expect($onboarding->envelope()->subject)->toBe('Un petit rappel de Delight')
        ->and($onboarding->render())->toContain('Pour commencer, essayez Jean 1');

    $resetNotice = (new CustomResetPasswordNotification('opaque-token'))->toMail($user);
    expect($resetNotice->subject)->toBe('Réinitialiser votre mot de passe – Delight');

    foreach ([
        1 => ['Tenez votre historique de lecture à jour', 'Ajouter une lecture'],
        2 => ['Un petit bonjour de Delight', 'Mettre à jour mon historique'],
        3 => ['Ajoutez votre dernière lecture à Delight', 'Ajouter une lecture'],
    ] as $number => [$subject, $cta]) {
        $recovery = new ChurnRecoveryEmail($user, $number);
        expect($recovery->envelope()->subject)->toBe($subject)
            ->and($recovery->render())->toContain($cta);
    }

    $readingLog = ReadingLog::factory()->for($user)->create([
        'book_id' => 43,
        'chapter' => 3,
        'passage_text' => 'John 3',
    ]);
    $lastReading = (new ChurnRecoveryEmail(
        $user,
        1,
        $readingLog->passage_text,
        $readingLog->book_id,
        $readingLog->chapter,
    ))->render();
    expect($lastReading)->toContain('Jean 3')->not->toContain('John 3')
        ->and($readingLog->fresh()->passage_text)->toBe('John 3');

    app()->setLocale('fr');
    $englishUser = User::factory()->create(['preferred_locale' => 'en']);
    foreach ([
        1 => 'Keep your reading history up to date',
        2 => 'A quick check-in from Delight',
        3 => 'Add your latest reading to Delight',
    ] as $number => $subject) {
        expect((new ChurnRecoveryEmail($englishUser, $number))->envelope()->subject)->toBe($subject);
    }
    app()->setLocale('en');

    expect((new PasswordResetMail('https://example.test/reset', 'fr'))->envelope()->subject)
        ->toBe('Réinitialiser votre mot de passe – Delight');

    expect(app()->getLocale())->toBe('en');
});

it('renders notification mail in the recipient language when the ambient language differs', function () {
    config(['mail.default' => 'array']);
    $frenchUser = User::factory()->create(['name' => 'Lectrice', 'preferred_locale' => 'fr']);
    $englishUser = User::factory()->create(['name' => 'Reader', 'preferred_locale' => 'en']);

    app()->setLocale('en');
    Notification::sendNow($frenchUser, new WelcomeNotification);
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $frenchWelcome = $transport->messages()->last()->getOriginalMessage();
    expect($frenchWelcome->getSubject())->toBe('Bienvenue sur Delight!')
        ->and($frenchWelcome->getHtmlBody())->toContain('Nous sommes heureux de vous accueillir.');

    Notification::sendNow($frenchUser, new CustomResetPasswordNotification('opaque-token'));
    $frenchReset = $transport->messages()->last()->getOriginalMessage();
    expect($frenchReset->getSubject())->toBe('Réinitialiser votre mot de passe – Delight')
        ->and($frenchReset->getHtmlBody())->toContain('Bonjour! Nous avons reçu une demande de réinitialisation du mot de passe de votre compte Delight.')
        ->and($frenchReset->getHtmlBody())->not->toContain('This password reset link will expire in');

    app()->setLocale('fr');
    Notification::sendNow($englishUser, new WelcomeNotification);
    $englishWelcome = $transport->messages()->last()->getOriginalMessage();
    expect($englishWelcome->getSubject())->toBe('Welcome to Delight!')
        ->and($englishWelcome->getHtmlBody())->toContain('Delight is designed to help you build a consistent Bible reading habit that brings you joy.');

    app()->setLocale('en');
});

it('includes the recipient locale in reset URLs and preserves English reset output', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    $notification = new CustomResetPasswordNotification('opaque-token');
    $resetUrl = $notification->resetUrl($user);

    expect($resetUrl)->toContain('locale=en');

    $this->get($resetUrl)
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertSee('Set new password');

    expect((new OnboardingReminderEmail($user))->envelope()->subject)->toBe('A gentle reminder from Delight');
});

it('localizes reading reminder web push copy from the recipient without changing delivery identifiers', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $notification = new ReadingReminderPushNotification('daily_reading', '2026-11-13', '/logs/create', 42);
    $pushMessage = $notification->toWebPush($user, $notification);
    $message = $pushMessage->toArray();

    expect($message['title'])->toBe('C’est l’heure de votre lecture du jour')
        ->and($message['body'])->toBe('Ouvrez Delight et ajoutez un chapitre quand vous le souhaitez.')
        ->and($message['tag'])->toBe('delight-daily_reading-2026-11-13')
        ->and($message['data'])->toBe(['url' => '/logs/create', 'deliveryId' => 42, 'reminderType' => 'daily_reading', 'reminderDate' => '2026-11-13'])
        ->and($pushMessage->getOptions())->toBe(['TTL' => 3600]);
});

it('selects current Web screenshots in the page language', function (string $locale) {
    foreach (['dashboard-web', 'guide-reading-form', 'guide-reading-calendar'] as $image) {
        $this->assertFileExists(public_path("images/screenshots/{$image}-{$locale}.jpg"));
    }
    $this->withHeader('Accept-Language', $locale)->get('/')
        ->assertOk()->assertSee("images/screenshots/dashboard-web-{$locale}.jpg", false);
    $this->get('/guides/track-bible-reading-with-a-paper-bible')
        ->assertOk()->assertSee("images/screenshots/guide-reading-form-{$locale}.jpg", false)
        ->assertSee("images/screenshots/guide-reading-calendar-{$locale}.jpg", false);
})->with(['en', 'fr']);
