// Service worker de Movieboxd.
// Al cambiar íconos o manifest hay que subir este nombre: activate borra las demás cachés.
const CACHE = 'movieboxd-v1';

const PRECACHE = ['/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo GET del propio origen: nada de POST ni de pósters de TMDB
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // cache-first SOLO para assets con hash de contenido en el nombre:
    // su URL cambia en cada build, así que nunca sirven algo viejo.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ??
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Todo lo demás (HTML, íconos, manifest): network-first con la caché de
    // respaldo offline. Una URL fija en cache-first quedaría congelada.
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && request.mode !== 'navigate') {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })
            .catch(() => caches.match(request)),
    );
});
