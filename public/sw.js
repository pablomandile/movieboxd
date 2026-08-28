// Service worker de Movieboxd.
// Al cambiar íconos o manifest hay que subir este nombre: activate borra las demás cachés.
const CACHE = 'movieboxd-v2';

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
                // Red de seguridad contra el JSON crudo en pantalla. Cuando el
                // bug ocurre la app nunca arranca, así que ningún script de la
                // página puede repararlo: el service worker es el único que
                // intercepta la navegación. Se mira el header X-Inertia de la
                // respuesta y no el content-type, porque una descarga puede
                // contestar JSON a una navegación de verdad y no hay que
                // pedirla dos veces.
                if (request.mode === 'navigate' && response.headers.get('x-inertia')) {
                    return fetch(request.url, {
                        cache: 'reload',
                        headers: { Accept: 'text/html' },
                    }).then((recuperada) =>
                        // Con la sesión vencida la URL redirige al login, y una
                        // respuesta ya redirigida no se le puede entregar a una
                        // navegación: se le pasa el redirect y lo sigue el
                        // navegador.
                        recuperada.redirected ? Response.redirect(recuperada.url, 302) : recuperada,
                    );
                }

                // El JSON de Inertia no se guarda: queda bajo la URL de la
                // página y en una navegación offline volvería a la pantalla.
                const esJsonDeInertia = response.headers.get('x-inertia');

                if (response.ok && request.mode !== 'navigate' && !esJsonDeInertia) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })
            .catch(() => caches.match(request)),
    );
});
