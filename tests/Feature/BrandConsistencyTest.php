<?php

use App\Models\User;

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
});

test('landing page does not claim an unsupported aggregate rating', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertDontSee('"aggregateRating"', false);
});

test('landing page explains the Bible reading tracker workflow and product boundaries', function () {
    $response = $this->get('/');
    $content = $response->getContent();

    $response->assertSuccessful()
        ->assertSee(sprintf('href="%s"', route('announcements.index')), false)
        ->assertSee('Keep your Bible reading going, wherever you read')
        ->assertSee('Your reading, clearly recorded')
        ->assertSee('Read where you prefer')
        ->assertSee('Log your chapters')
        ->assertSee('See your progress')
        ->assertSeeText('66-book canon by default')
        ->assertSeeText('optional Catholic 73-book deuterocanonical support')
        ->assertSeeTextInOrder([
            'Keep your Bible reading going, wherever you read',
            'Your reading, clearly recorded',
            'Tools for your reading routine',
            'Book Completion Grid',
            'optional Catholic 73-book deuterocanonical support',
            'Achievements',
            'Reading Reminders',
        ])
        ->assertSee('Record what you read, with or without a plan.')
        ->assertSee('Get Delight for Android')
        ->assertSee('src="'.asset('images/screenshots/android-home-v1.png'), false)
        ->assertSee('Delight is currently free to use.')
        ->assertSee('Frequently asked questions')
        ->assertSee('Android users can also')
        ->assertSee('href="'.route('android.download').'"', false)
        ->assertSee('download the native app')
        ->assertSee('Create an account to save your reading history.')
        ->assertSee('Delight needs a connection to load your readings and save new logs.')
        ->assertSee('<div class="mt-12 overflow-hidden rounded-xl border border-gray-200 bg-white">', false)
        ->assertSee('content="Track Bible chapters, see your progress, and choose optional native reading reminders in Delight for Android. Browser reminders are available on supported web devices."', false);

    expect(strpos($content, 'id="steps-heading"'))->toBeLessThan(strpos($content, 'id="features-heading"'));
});

test('landing page presents optional Android and web reminders accurately', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('Reading Reminders')
        ->assertSee('No reading logged today')
        ->assertSee('Active streak at risk')
        ->assertSee('Opt into native notifications in Delight for Android.')
        ->assertSee('available in beta on supported devices.')
        ->assertSee('On iPhone and iPad, web notifications require adding Delight to the Home Screen from Safari.')
        ->assertDontSee('id="reminders-heading"', false);
});

test('landing page publishes valid web and Android structured data', function () {
    $response = $this->get('/');

    expect(preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches))
        ->toBe(1);

    $structuredData = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    $desktopScreenshot = asset('images/screenshots/desktop-v3.png').'?v='.filemtime(public_path('images/screenshots/desktop-v3.png'));
    $androidScreenshot = asset('images/screenshots/android-home-v1.png').'?v='.filemtime(public_path('images/screenshots/android-home-v1.png'));

    expect($structuredData['name'])->toBe('Delight - Bible Reading Tracker')
        ->and($structuredData['operatingSystem'])->toBe('Web Browser, Android 7.0+')
        ->and($structuredData['featureList'])->toContain('Optional Native Android and Browser Reading Reminders')
        ->and($structuredData['screenshot'])->toBe([
            $desktopScreenshot,
            $androidScreenshot,
        ]);

    $response->assertSee('content="A free Bible reading tracker with progress, streaks, and optional native Android reminders. Browser reminders are available on supported devices."', false);
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

test('public primary calls to action use the Delight blue palette', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee('bg-primary-500 text-white py-3', false);

    $this->get(route('android.download'))
        ->assertSuccessful()
        ->assertSee('bg-primary-500 px-4 py-2', false)
        ->assertSee('bg-primary-500 px-6 py-3', false)
        ->assertDontSee('bg-blue-600', false);

    $this->get(route('guides.paper-bible'))
        ->assertSuccessful()
        ->assertSee('bg-primary-500 px-6 py-3', false)
        ->assertDontSee('bg-blue-700', false);

    $this->get(route('account-deletion.create'))
        ->assertSuccessful()
        ->assertSee('bg-primary-500 px-5 py-2.5', false);
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

test('service worker does not cache pwa manifest', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->not->toMatch('/[\'\"]\\/site\\.webmanifest[\'\"]/')
        ->not->toMatch('/[\'\"]\\/pwa\\.webmanifest[\'\"]/');
});
