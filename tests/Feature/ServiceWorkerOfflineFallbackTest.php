<?php

test('service worker returns branded offline fallback for failed navigations', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('request.mode === \'navigate\'')
        ->toContain('fetch(event.request).catch')
        ->toContain('src="/images/logo-64.png"')
        ->toContain('alt=""')
        ->toContain('Delight is offline')
        ->toContain('Delight needs a connection to load readings and save new logs.')
        ->toContain('Try again')
        ->toContain('offlineDocumentResponse(OFFLINE_FALLBACK_HTML)')
        ->toContain('offlineFallbackResponse(body, 503)');
});

test('service worker returns offline fallback fragment for page container htmx navigations', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('request.headers.get(\'HX-Request\') === \'true\'')
        ->toContain('request.headers.get(\'HX-Target\') === \'page-container\'')
        ->toContain('OFFLINE_FALLBACK_FRAGMENT')
        ->toContain('offlineHtmxResponse(OFFLINE_FALLBACK_FRAGMENT)')
        ->toContain('offlineFallbackResponse(body, 200)');
});

test('service worker retry actions reset scroll before reloading the current page', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('type="button"')
        ->toContain('data-offline-retry')
        ->toContain("history.scrollRestoration = 'manual'")
        ->toContain('window.scrollTo(0, 0)')
        ->toContain('navigator.onLine === false')
        ->toContain('window.location.replace(window.location.href)')
        ->not->toContain('onclick=')
        ->not->toContain('href=""')
        ->not->toContain('requestAnimationFrame')
        ->not->toContain('setTimeout');
});

test('service worker full page offline fallback prevents document scrolling', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('height: 100dvh;')
        ->toContain('overflow: hidden;')
        ->toContain('overflow-y: auto;')
        ->toContain('max-height: calc(100dvh - 2rem);');
});

test('service worker full page offline fallback respects light and dark color schemes', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('color-scheme: light dark;')
        ->toContain('background: #f5f7fa;')
        ->toContain('background: #ffffff;')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('background: #111827;')
        ->toContain('background: rgba(31, 41, 55, 0.94);')
        ->not->toContain('color-scheme: dark;');
});

test('service worker keeps static asset cache first behavior', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('STATIC_CACHE_URLS.includes(requestUrl.pathname)')
        ->toContain('caches.match(event.request, { ignoreSearch: true })');
});
