const CACHE_NAME = 'hg-pwa-static-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/assets/css/hg-mobile.css',
  '/assets/js/hg-mobile.js',
  '/assets/js/hg-pwa.js',
  '/img/favicon/android-chrome-192x192.webp',
  '/img/favicon/android-chrome-512x512.webp',
  '/img/favicon/apple-touch-icon.webp',
  '/img/favicon/favicon-32x32.webp'
];

const STATIC_PREFIXES = [
  '/assets/',
  '/img/favicon/'
];

const STATIC_EXACT = new Set([
  '/img/ui/branding/infinidice.ico',
  '/manifest.webmanifest'
]);

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(key => key.startsWith('hg-pwa-') && key !== CACHE_NAME)
          .map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

function isSafeStaticRequest(requestUrl) {
  if (requestUrl.origin !== self.location.origin) return false;
  if (STATIC_EXACT.has(requestUrl.pathname)) return true;
  return STATIC_PREFIXES.some(prefix => requestUrl.pathname.startsWith(prefix));
}

async function networkFirstStatic(request) {
  const cache = await caches.open(CACHE_NAME);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) return cached;

    const withoutSearch = new Request(new URL(request.url).origin + new URL(request.url).pathname, {
      method: 'GET',
      headers: request.headers,
      mode: request.mode,
      credentials: request.credentials,
      cache: 'default',
      redirect: request.redirect,
      referrer: request.referrer,
      referrerPolicy: request.referrerPolicy,
      integrity: request.integrity
    });
    const fallback = await cache.match(withoutSearch);
    if (fallback) return fallback;
    throw error;
  }
}

self.addEventListener('fetch', event => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        const cache = await caches.open(CACHE_NAME);
        return cache.match(OFFLINE_URL);
      })
    );
    return;
  }

  const url = new URL(request.url);
  if (isSafeStaticRequest(url)) {
    event.respondWith(networkFirstStatic(request));
  }
});
