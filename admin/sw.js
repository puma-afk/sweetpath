const CACHE_NAME = 'esencia-admin-v1';

self.addEventListener('install', event => {
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
  // Para el admin usamos Network First: siempre intentamos traer de la red
  // Esto asegura que siempre tengamos los datos más recientes.
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Opcionalmente podemos guardar en caché la respuesta para modo offline,
        // pero solo si es GET y exitosa
        if (event.request.method === 'GET' && response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // En caso de no haber red, intentamos devolver de la caché
        return caches.match(event.request);
      })
  );
});
