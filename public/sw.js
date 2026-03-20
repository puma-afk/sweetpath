const CACHE_NAME = 'esencia-pwa-v1';
const ASSETS_TO_CACHE = [
  './index.html',
  './css/app.css',
  './css/tienda.css',
  './img/logo_white.png',
  './img/icon-192.png',
  './img/icon-512.png',
  './manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) return caches.delete(key);
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Bypass para /api/ y /admin/ (no se cachean)
  if (url.pathname.includes('/api/') || url.pathname.includes('/admin/')) {
    return; // deja que el navegador maneje la petición por defecto
  }

  // Network falling back to cache
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Guarda en caché solo si la respuesta es exitosa
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }
        
        // Si no es un método GET, no lo guardes en caché
        if (event.request.method !== 'GET') {
          return response;
        }

        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseToCache);
        });
        return response;
      })
      .catch(() => {
        // En modo offline, devuelve lo que haya en la caché
        return caches.match(event.request);
      })
  );
});
