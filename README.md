# Movieboxd

Red social de trackeo, calificación y reseñas de **películas y series de TV**, inspirada en [Letterboxd](https://letterboxd.com). A diferencia del original, soporta series con trackeo y reseñas a nivel **serie, temporada y episodio**.

> Estado: las 8 fases del plan completas + importador de Letterboxd · 98 tests (506 assertions) · build de producción OK.
> Para el detalle de decisiones técnicas, ver [ARCHITECTURE.md](ARCHITECTURE.md). Para el estado e historial del proyecto, ver [PROYECTO.md](PROYECTO.md).

## Características

- **Catálogo con datos de TMDB**: búsqueda de películas y series, tendencias de la semana, fichas con sinopsis, elenco, géneros y pósters. Los títulos se importan bajo demanda y quedan como snapshot local que se refresca automáticamente.
- **Trackeo**: marcar como visto, me gusta, watchlist y calificación de 0.5 a 5 estrellas (medios pasos). Para series, además: marcar episodios individuales o temporadas completas, con barra de progreso.
- **Diario**: cada visionado es una entrada fechada, con rewatch autodetectado, etiquetas y reseña opcional — todo en un solo modal de registro.
- **Reseñas**: con flag de spoilers (ocultas por defecto), likes y comentarios. Se pueden reportar para moderación.
- **Listas**: ranqueadas o no, públicas o privadas, con notas por ítem y reordenamiento por drag & drop.
- **Social**: seguir usuarios (asimétrico), feed de actividad de los seguidos, perfil público con 4 favoritos destacados y 8 tabs (vistas, diario, reseñas, watchlist, listas, me gusta, red, estadísticas).
- **Estadísticas de perfil**: horas vistas, actividad por año, distribución de calificaciones, géneros y directores más vistos, décadas, títulos más revisionados.
- **Panel de administración** (`/admin`): resumen general, gestión de usuarios (roles, ban con cierre de sesiones), cola de moderación de reportes, y configuración (API key de TMDB encriptada, parámetro del promedio ponderado, feature flags).
- **Importador de Letterboxd** (`/settings/import`): sube el ZIP de tu exportación oficial y trae vistas, calificaciones, diario, reseñas, watchlist, likes y listas. Idempotente: nunca pisa datos existentes.
- **Bilingüe**: interfaz completa en español e inglés, con metadata de TMDB en ambos idiomas.

## Stack

| Área | Tecnología |
|---|---|
| Backend | Laravel 12 (PHP 8.2+, desarrollado con 8.4) |
| Frontend | Inertia.js 2 + Vue 3 + TypeScript ([laravel/vue-starter-kit](https://github.com/laravel/vue-starter-kit)) |
| Estilos | Tailwind CSS 3 con la paleta real de Letterboxd (tokens `lb-*`) — app dark-only |
| Base de datos | MySQL (SQLite en memoria para tests) |
| Cola y caché | Driver `database` (sin Redis) |
| Datos de cine/TV | [TMDB API v3](https://developer.themoviedb.org/docs) |
| i18n | vue-i18n (frontend) + laravel-lang (validaciones y mails) |

## Requisitos

- PHP ≥ 8.2 con extensiones habituales de Laravel + `zip` (para el importador de Letterboxd)
- Composer
- Node.js ≥ 20 y npm
- MySQL 8 (o MariaDB)
- Una API key de TMDB (gratuita, ver abajo)

## Instalación

```bash
git clone <repo> movieboxd
cd movieboxd

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configurá la base de datos en `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=movieboxd
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
```

Creá la base `movieboxd` y corré las migraciones con el seeder:

```bash
php artisan migrate --seed
```

El seeder crea un usuario administrador de prueba: `admin@movieboxd.test` / `password`.

### API key de TMDB

Sin la key, la búsqueda y el catálogo no traen datos (la app no rompe: la home muestra un aviso).

1. Creá una cuenta gratis en [themoviedb.org](https://www.themoviedb.org/signup) → Settings → API → generá la key **v3** («Clave de la API», no el token de lectura). La aprobación es inmediata.
2. Cargala de una de estas dos formas:
   - En `.env`: `TMDB_API_KEY=tu_key`
   - Desde el panel de admin (`/admin/settings`): queda guardada **encriptada** en la base y tiene prioridad sobre la del `.env`.

La key nunca se envía al navegador: todas las llamadas a TMDB pasan por el backend.

## Desarrollo

```bash
composer run dev
```

Ese único comando levanta en paralelo el servidor HTTP, el **worker de cola** (necesario para los refrescos de TMDB y el importador de Letterboxd), los logs en vivo (Pail) y Vite.

Alternativamente, cada proceso por separado:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

### Tareas programadas

Para los refrescos automáticos en un entorno persistente, el scheduler debe estar corriendo (`php artisan schedule:work` en desarrollo, cron en producción):

| Comando | Horario | Qué hace |
|---|---|---|
| `php artisan movieboxd:refresh-stale` | 04:00 | Encola el refresco de títulos con datos viejos (películas 30 días, series en emisión 24 h) |
| `php artisan movieboxd:reconcile-aggregates` | 04:30 | Recalcula los contadores cacheados desde las tablas fuente |

Ambos también se pueden ejecutar a mano.

## Tests

```bash
php artisan test
```

La suite corre sobre SQLite en memoria y **nunca golpea la API real de TMDB** (todo usa `Http::fake()`). Formato de código: `vendor/bin/pint` (PHP) y `npm run lint` / `npm run format` (frontend).

## Importar tus datos de Letterboxd

1. En Letterboxd: Settings → Data → *Export your data* — descargá el ZIP.
2. En Movieboxd, con el worker de cola corriendo: **Configuración → Letterboxd** (`/settings/import`).
3. Subí el ZIP tal cual (si lo descomprimiste, volvé a comprimir la carpeta), elegí qué secciones importar y confirmá.

El progreso se ve en vivo y podés cerrar la página: la importación sigue en segundo plano. Cada película se matchea contra TMDB por título y año; las que no tienen match quedan en un reporte visible para cargarlas a mano. El import **nunca pisa datos existentes** y re-ejecutarlo es seguro.

## Estructura del proyecto

```
app/
├── Console/Commands/       refresh-stale, reconcile-aggregates
├── Http/Controllers/       Público, trackeo, Settings/ y Admin/
├── Jobs/                   RefreshTitle, Prepare/ProcessLetterboxdImport
├── Models/                 Title, Season, Episode, DiaryEntry, Review, ListModel…
├── Observers/              Mantienen los contadores cacheados
└── Services/
    ├── Tmdb/               TmdbClient (proxy + caché) y TmdbImportService
    ├── Letterboxd/         Parser del export y servicio de import
    ├── ActivityFeedService.php
    └── ProfileStatsService.php
resources/js/
├── pages/                  Vistas Inertia (Home, títulos, perfil, admin…)
├── components/             LogModal, ActionsPanel, RatingStars, charts/…
├── layouts/                AppLayout, AdminLayout, settings/
└── i18n/                   es.ts / en.ts
routes/                     web.php (público/auth/admin), settings.php, auth.php
tests/Feature/              98 tests: catálogo, trackeo, reviews, social, listas, admin, import
```

## Atribución

Este producto usa la API de TMDB pero no está avalado ni certificado por TMDB. Los datos e imágenes de películas y series provienen de [The Movie Database](https://www.themoviedb.org/). La atribución es un requisito de la licencia gratuita de TMDB y ya está incluida en el footer de la aplicación.

Movieboxd es un proyecto personal educativo inspirado en Letterboxd, sin afiliación alguna con Letterboxd Limited.
