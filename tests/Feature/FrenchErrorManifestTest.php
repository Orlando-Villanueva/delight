<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const LOCALE_ERROR_PATH = '/test-locale-error';
const FRENCH_NOT_FOUND = 'Page introuvable';
const LOCALE_MANIFEST_PATH = '/pwa.webmanifest';

it('localizes error responses with their original status and does not leak the locale', function (int $status, string $french, string $english) {
    config(['app.debug' => false]);
    Route::middleware('web')->get(LOCALE_ERROR_PATH, function () use ($status) {
        abort($status);
    });
    $fr = User::factory()->create(['preferred_locale' => 'fr']);
    $en = User::factory()->create(['preferred_locale' => 'en']);
    $this->actingAs($fr)->withHeader('Accept-Language', 'en')->get(LOCALE_ERROR_PATH)->assertStatus($status)->assertSee($french)->assertHeader('Content-Language', 'fr');
    $this->actingAs($en)->withHeader('Accept-Language', 'fr')->get(LOCALE_ERROR_PATH)->assertStatus($status)->assertSee($english)->assertHeader('Content-Language', 'en');
    expect(app()->getLocale())->toBe('en');
})->with([[403, 'Accès interdit', 'Access Forbidden'], [404, FRENCH_NOT_FOUND, 'Page Not Found'], [419, 'Page expirée', 'Page Expired'], [429, 'Trop de demandes', 'Too Many Requests'], [500, 'Erreur du serveur', 'Server Error'], [503, 'Service indisponible', 'Service Unavailable']]);

it('localizes an unmatched visitor error from browser language', function () {
    $this->withHeader('Accept-Language', 'fr')->get('/page-that-does-not-exist')->assertNotFound()->assertSee(FRENCH_NOT_FOUND);
});

it('localizes manifest without caching or changing install identifiers', function () {
    $en = $this->withHeader('Accept-Language', 'en')->get(LOCALE_MANIFEST_PATH)->assertOk()->json();
    $fr = $this->withHeader('Accept-Language', 'fr')->get(LOCALE_MANIFEST_PATH)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($fr->json('lang'))->toBe('fr')->and($fr->json('shortcuts.0.name'))->toBe('Ajouter une lecture')
        ->and($fr->json('screenshots.0.label'))->toBe('Tableau de bord sur mobile')
        ->and($fr->json('icons'))->toBe($en['icons'])->and($fr->json('start_url'))->toBe($en['start_url']);
    $user = User::factory()->create(['preferred_locale' => 'en']);
    $this->actingAs($user)->withCookie('delight_locale', 'fr')->get(LOCALE_MANIFEST_PATH)->assertJsonPath('lang', 'en-US');
});

it('localizes duplicate email and password number validation', function () {
    Http::fake();
    User::factory()->create(['email' => 'reader@example.test']);
    $this->withHeader('Accept-Language', 'fr')->postJson('/register', ['name' => 'Reader', 'email' => 'READER@example.test', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Cette adresse courriel est déjà utilisée.')
        ->assertJsonPath('errors.password.0', 'Le champ mot de passe doit contenir au moins un chiffre.');
});

it('uses saved account and visitor cookie languages on unmatched pages without initializing accounts', function () {
    $fr = User::factory()->create(['preferred_locale' => 'fr']);
    $this->actingAs($fr)->withHeader('Accept-Language', 'en')->get('/unmatched-account')->assertNotFound()->assertSee(FRENCH_NOT_FOUND);
    auth()->logout();
    $this->withCookie('delight_locale', 'fr')->withHeader('Accept-Language', 'en')->get('/unmatched-visitor')->assertNotFound()->assertSee(FRENCH_NOT_FOUND);
    $user = User::factory()->create(['preferred_locale' => null]);
    $this->actingAs($user)->withHeader('Accept-Language', 'fr')->get('/unmatched-unset')->assertNotFound();
    expect($user->fresh()->preferred_locale)->toBeNull();
    $this->getJson('/api/unknown-path')->assertNotFound()->assertJsonStructure(['message']);
});

it('localizes compromised password validation', function () {
    $password = 'Compromised123';
    // Fixed digest for the known test password, matching the breached-password range API.
    $hash = '042CBE131449EB3558B59F74676FB2EA12230FE5';
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':5')]);
    $this->withHeader('Accept-Language', 'fr')->postJson('/register', [
        'name' => 'Reader', 'email' => 'new@example.test', 'password' => $password, 'password_confirmation' => $password,
    ])->assertUnprocessable()->assertJsonPath('errors.password.0', 'Ce mot de passe a été compromis dans une fuite de données. Veuillez en choisir un autre.');
});

it('preserves a configured manifest and varies the private response by visitor language', function () {
    $path = tempnam(sys_get_temp_dir(), 'delight-manifest-');
    file_put_contents($path, json_encode(['name' => 'Custom', 'lang' => 'en-US', 'start_url' => '/custom', 'description' => 'Custom description'], JSON_THROW_ON_ERROR));
    config(['app.pwa_manifest_path' => $path]);
    try {
        $response = $this->withCookie('delight_locale', 'fr')->get(LOCALE_MANIFEST_PATH)->assertOk();
        expect($response->json())->toMatchArray(['name' => 'Custom', 'lang' => 'fr', 'start_url' => '/custom', 'description' => 'Custom description'])
            ->and($response->getVary())->toContain('Cookie')->toContain('Accept-Language')
            ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    } finally {
        unlink($path);
    }
});

it('reads the account language from a cold persisted session on an unmatched page', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']);
    $login = $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    $cookieName = config('session.cookie');
    $sessionId = $login->getCookie($cookieName)->getValue();
    auth()->forgetGuards();

    $this->withCookie($cookieName, $sessionId)->withCookie('delight_locale', 'en')->withHeader('Accept-Language', 'en')
        ->get('/unmatched-cold-session')->assertNotFound()->assertSee(FRENCH_NOT_FOUND)->assertHeader('Content-Language', 'fr');
    expect($user->fresh()->preferred_locale)->toBe('fr');
});

it('does not initialize a language from a matched failed page request', function () {
    Route::middleware('web')->get('/failed-locale-initialization', fn () => abort(429, '', ['Retry-After' => '23']));
    $user = User::factory()->create(['preferred_locale' => null]);
    $this->actingAs($user)->withHeader('Accept-Language', 'fr')->get('/failed-locale-initialization')
        ->assertStatus(429)->assertHeader('Retry-After', '23')->assertHeader('Content-Language', 'fr');
    expect($user->fresh()->preferred_locale)->toBeNull();
});
