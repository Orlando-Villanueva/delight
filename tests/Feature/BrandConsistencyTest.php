<?php

use App\Models\User;

test('app name displays consistently', function () {
    expect(config('app.name'))->not->toBeEmpty();
});

test('welcome page shows brand name', function () {
    $response = $this->get('/');

    $response->assertSee(config('app.name'));
});

test('login page shows brand name', function () {
    $response = $this->get('/login');

    $response->assertSee(config('app.name'));
});

test('register page shows brand name', function () {
    $response = $this->get('/register');

    $response->assertSee(config('app.name'));
});

test('page titles include brand name', function () {
    $response = $this->get('/login');

    $response->assertSee('<title>'.config('app.name'), false);
});

test('landing page uses consistent public brand name', function () {
    $publicBrandName = 'Delight - Bible Reading Tracker';

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('<title>'.$publicBrandName.'</title>', false);
    $response->assertSee('<meta property="og:title" content="'.$publicBrandName.'">', false);
    $response->assertSee('<meta name="twitter:title" content="'.$publicBrandName.'">', false);
    $response->assertSee('"name": "'.$publicBrandName.'"', false);
});

test('landing page uses versioned brand assets', function () {
    $assetVersion = config('app.asset_version');

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('href="'.asset('favicon-app.ico').'?v='.$assetVersion.'"', false);
    $response->assertSee('href="'.asset('images/app-icon-v2-192.png').'?v='.$assetVersion.'"', false);
    $response->assertSee('src="'.asset('images/logo-64.png').'?v='.$assetVersion.'"', false);
    $response->assertSee(asset('images/logo-64-2x.png').'?v='.$assetVersion, false);
});

test('landing page links versioned pwa manifest', function () {
    $assetVersion = config('app.asset_version');

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('href="'.route('pwa.manifest', ['v' => $assetVersion]).'"', false);
});

test('landing page footer links app social profiles', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText('Follow');
    $response->assertSee('href="https://x.com/TheDelightApp"', false);
    $response->assertSee('href="https://www.instagram.com/thedelightapp/"', false);
    $response->assertSee('aria-label="Follow Delight on X (opens in a new tab)"', false);
    $response->assertSee('aria-label="Follow Delight on Instagram (opens in a new tab)"', false);
    $response->assertSee('target="_blank" rel="noopener noreferrer"', false);
});

test('app layouts link versioned pwa manifest route', function () {
    $assetVersion = config('app.asset_version');
    $manifestHref = 'href="'.route('pwa.manifest', ['v' => $assetVersion]).'"';

    $this->get('/login')
        ->assertSuccessful()
        ->assertSee($manifestHref, false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee($manifestHref, false);
});

test('service worker matches versioned static asset requests', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('const requestUrl = new URL(event.request.url);')
        ->toContain('STATIC_CACHE_URLS.includes(requestUrl.pathname)')
        ->toContain('caches.match(event.request, { ignoreSearch: true })');
});

test('service worker does not cache pwa manifest', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->not->toMatch('/[\'\"]\\/site\\.webmanifest[\'\"]/')
        ->not->toMatch('/[\'\"]\\/pwa\\.webmanifest[\'\"]/');
});
