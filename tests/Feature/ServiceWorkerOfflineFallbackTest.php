<?php

function serviceWorkerSource(): string
{
    $source = file_get_contents(public_path('sw.js'));

    expect($source)->not->toBeFalse();

    return $source;
}

test('service worker includes the scoped offline fallback behavior', function () {
    $serviceWorker = serviceWorkerSource();

    $requiredSnippets = [
        'request.mode === \'navigate\'',
        'fetch(event.request).catch',
        'src="/images/logo-64.png"',
        'alt=""',
        'Delight is offline',
        'Delight needs a connection to load readings and save new logs.',
        'Try again',
        'offlineDocumentResponse(OFFLINE_FALLBACK_HTML)',
        'offlineFallbackResponse(body, 503)',
        'request.headers.get(\'HX-Request\') === \'true\'',
        'request.headers.get(\'HX-Target\') === \'page-container\'',
        'OFFLINE_FALLBACK_FRAGMENT',
        'offlineHtmxResponse(OFFLINE_FALLBACK_FRAGMENT)',
        'offlineFallbackResponse(body, 200)',
        'STATIC_CACHE_URLS.includes(requestUrl.pathname)',
        'caches.match(event.request, { ignoreSearch: true })',
    ];

    foreach ($requiredSnippets as $snippet) {
        expect($serviceWorker)->toContain($snippet);
    }
});

test('service worker retry and layout guards stay simple', function () {
    $serviceWorker = serviceWorkerSource();

    $requiredSnippets = [
        'type="button"',
        'data-offline-retry',
        "history.scrollRestoration = 'manual'",
        'window.scrollTo(0, 0)',
        'window.location.replace(window.location.href)',
        'html {',
        'height: 100%;',
        'position: fixed;',
        'inset: 0;',
        'width: 100%;',
        'height: 100dvh;',
        'overflow: hidden;',
        'overflow-y: auto;',
        'max-height: calc(100dvh - 2rem);',
        'color-scheme: light dark;',
        'background: #f5f7fa;',
        'background: #ffffff;',
        '@media (prefers-color-scheme: dark)',
        'background: #111827;',
        'background: rgba(31, 41, 55, 0.94);',
        'text-primary-600 dark:text-primary-400',
        'bg-primary-600',
        'hover:bg-primary-700',
    ];

    foreach ($requiredSnippets as $snippet) {
        expect($serviceWorker)->toContain($snippet);
    }

    expect($serviceWorker)
        ->not->toContain('onclick=')
        ->not->toContain('href=""')
        ->not->toContain('navigator.onLine')
        ->not->toContain('requestAnimationFrame')
        ->not->toContain('setTimeout')
        ->not->toContain('color-scheme: dark;');
});
