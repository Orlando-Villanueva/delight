// Service Worker for Delight PWA
// Version 1.0 - Basic PWA installation support

const CACHE_NAME = 'delight-v4';
const STATIC_CACHE_URLS = [
  '/favicon-app.ico',
  '/images/logo-64.png',
  '/images/logo-192.png',
  '/images/app-icon-v2-64.png',
  '/images/app-icon-v2-192.png',
  '/images/app-icon-v2-512.png'
];

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
  }
  // For all other requests, go to network (your HTMX app needs fresh data)
});
