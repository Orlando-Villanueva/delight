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
        ->toContain('window.location.reload()');
});

test('service worker returns offline fallback fragment for page container htmx navigations', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('request.headers.get(\'HX-Request\') === \'true\'')
        ->toContain('request.headers.get(\'HX-Target\') === \'page-container\'')
        ->toContain('OFFLINE_FALLBACK_FRAGMENT')
        ->toContain('headers: offlineFallbackHeaders()');
});

test('service worker keeps static asset cache first behavior', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain('STATIC_CACHE_URLS.includes(requestUrl.pathname)')
        ->toContain('caches.match(event.request, { ignoreSearch: true })');
});
