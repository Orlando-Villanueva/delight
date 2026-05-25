// Service Worker for Delight PWA
// Version 1.0 - Basic PWA installation support

const CACHE_NAME = 'delight-v4';
const STATIC_CACHE_URLS = [
  '/favicon-app.ico',
  '/images/logo-64.png',
  '/images/logo-192.png',
  '/images/app-icon-v2-64.png',
  '/images/app-icon-v2-192.png',
  '/images/app-icon-v2-512.png',
];
const OFFLINE_FALLBACK_HTML = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delight is offline</title>
  <style>
    :root {
      color-scheme: dark;
      font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #111827;
      color: #f9fafb;
    }

    * {
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      margin: 0;
      display: grid;
      place-items: center;
      padding: 2rem;
      background: #111827;
    }

    main {
      width: min(100%, 32rem);
      padding: 2rem;
      border: 1px solid rgba(75, 85, 99, 0.9);
      border-radius: 0.75rem;
      background: rgba(31, 41, 55, 0.94);
      box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, 0.35);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #93c5fd;
    }

    .logo {
      width: 2rem;
      height: 2rem;
      border-radius: 0.75rem;
    }

    h1 {
      margin: 0;
      font-size: clamp(2rem, 8vw, 3.5rem);
      line-height: 1;
    }

    p {
      margin: 1rem 0 0;
      color: #d1d5db;
      font-size: 1rem;
      line-height: 1.65;
    }

    button {
      margin-top: 1.75rem;
      min-height: 2.75rem;
      border: 0;
      border-radius: 0.5rem;
      padding: 0.75rem 1.1rem;
      background: #2563eb;
      color: #ffffff;
      font: inherit;
      font-weight: 700;
    }

    button:focus-visible {
      outline: 3px solid #93c5fd;
      outline-offset: 3px;
    }
  </style>
</head>
<body>
  <main>
    <div class="brand">
      <img class="logo" src="/images/logo-64.png" alt="" width="32" height="32">
      <span>Delight</span>
    </div>
    <h1>Delight is offline</h1>
    <p>Delight needs a connection to load readings and save new logs. Reconnect, then retry this page.</p>
    <button type="button" onclick="window.location.reload()">Try again</button>
  </main>
</body>
</html>`;
const OFFLINE_FALLBACK_FRAGMENT = `
<section class="mx-auto max-w-xl rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
  <div class="flex items-center justify-center gap-3">
    <img src="/images/logo-64.png" alt="" width="32" height="32" class="h-8 w-8 rounded-xl">
    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Delight</p>
  </div>
  <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">Delight is offline</h1>
  <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">Delight needs a connection to load readings and save new logs. Reconnect, then retry this page.</p>
  <button type="button" onclick="window.location.reload()" class="mt-5 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800">Try again</button>
</section>`;

function offlineFallbackHeaders() {
  return {
    'Content-Type': 'text/html; charset=utf-8',
    'Cache-Control': 'no-store',
  };
}

function offlineFallbackResponse(body) {
  return new Response(body, {
    status: 503,
    statusText: 'Service Unavailable',
    headers: offlineFallbackHeaders(),
  });
}

function isPageContainerHtmxRequest(request) {
  return request.headers.get('HX-Request') === 'true'
    && request.headers.get('HX-Target') === 'page-container';
}

// Install event - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_CACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve cached assets when possible
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);

  // For static assets, try cache first
  if (STATIC_CACHE_URLS.includes(requestUrl.pathname)) {
    event.respondWith(
      caches.match(event.request, { ignoreSearch: true })
        .then((response) => {
          return response || fetch(event.request);
        })
    );

    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return offlineFallbackResponse(OFFLINE_FALLBACK_HTML);
      })
    );

    return;
  }

  if (isPageContainerHtmxRequest(event.request)) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return offlineFallbackResponse(OFFLINE_FALLBACK_FRAGMENT);
      })
    );

    return;
  }

  // For all other requests, go to network (your HTMX app needs fresh data)
});
