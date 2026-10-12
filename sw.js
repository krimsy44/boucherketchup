const CACHE_NAME = 'chez-ketchup-v23';
const ASSETS = [
  './',
  'index.php',
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/img/chez-ketchup-logo.jpeg',
  'assets/img/hero-chez-ketchup.jpeg',
  'assets/img/social-whatsapp.svg',
  'assets/img/social-instagram.svg',
  'manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Never cache API responses
  if (url.pathname.endsWith('/admin.php') || url.searchParams.get('page') === 'admin' || url.pathname.includes('/api/') || url.searchParams.has('resource')) {
    return;
  }

  // Stale-while-revalidate for static assets
  event.respondWith(
    caches.open(CACHE_NAME).then((cache) =>
      cache.match(request).then((cached) => {
        const fetched = fetch(request).then((response) => {
          if (response.ok) {
            cache.put(request, response.clone());
          }
          return response;
        });
        return cached || fetched;
      })
    ).catch(() => caches.match('./'))
  );
});
