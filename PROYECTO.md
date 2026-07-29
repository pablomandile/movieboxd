# Movieboxd — Estado del proyecto

> Clon de Letterboxd (red social de trackeo, calificación y reseñas de películas) **con soporte de series de TV**: trackeo y reseñas a nivel serie, temporada y episodio.
>
> **Última actualización:** 30 de julio de 2026 · **Estado:** ✅ Las 8 fases del plan completas + importador de Letterboxd · **Tests:** 98 pasando (506 assertions), build de producción OK

---

## 1. Cómo levantar el proyecto

```powershell
cd c:\laragon\www\movieboxd

# Dependencias (ya instaladas; solo si se clona de cero)
composer install
npm install

# Base de datos (ya creada y migrada)
php artisan migrate

# Desarrollo
npm run dev          # Vite (en una terminal)
php artisan serve    # o usar el vhost de Laragon: http://movieboxd.test
php artisan queue:work   # necesario para los jobs de refresco de TMDB

# Tests
php artisan test
```

**IMPORTANTE — pendiente del usuario:** falta poner la **API key de TMDB**. Sin ella, la búsqueda y el catálogo no traen datos (la app no rompe: la home muestra un mensaje).

1. Crear cuenta gratis en https://www.themoviedb.org/signup → Settings → API → generar key (v3, aprobación inmediata).
2. Ponerla en `.env` como `TMDB_API_KEY=...`, **o** cargarla desde el panel de admin (`/admin/settings`), donde queda guardada encriptada en la base y tiene prioridad sobre la del `.env`.

**Usuario admin de prueba** (creado por el seeder): `admin@movieboxd.test` / `password`. Para recrear: `php artisan db:seed`.

---

## 2. Stack y decisiones tomadas

| Área | Decisión |
|---|---|
| Backend | **Laravel 12.64** (PHP 8.4) |
| Frontend | **Inertia.js 2 + Vue 3 + TypeScript**, starter kit oficial `laravel/vue-starter-kit` |
| Estilos | **Tailwind CSS 3** con tokens propios (`lb-*`) extraídos del CSS real de Letterboxd |
| Base de datos | **MySQL** (`movieboxd`, Laragon, usuario `root` sin contraseña) |
| Cola y caché | driver `database` (no hay Redis en Laragon/Windows) |
| Datos de películas y series | **TMDB API v3** exclusivamente (gratuita, requiere atribución) |
| Roles | Columna enum `role` (`user` \| `admin`) + middleware. **Sin** spatie/laravel-permission |
| Panel admin | Mismo stack Vue/Inertia (**no** Filament), rutas bajo `/admin` |
| Idiomas | i18n es/en con **vue-i18n** (frontend) + `laravel-lang` (validaciones y mails) |
| Dependencias extra | Solo `vue-i18n` y `vuedraggable` en npm; `laravel-lang/common` en dev |

### Por qué TMDB y no otra API
Es la única API gratuita que cubre bien **películas Y series con temporadas y episodios** (`/tv/{id}/season/{n}` devuelve todos los episodios con sinopsis, still, fecha y duración). Es la misma fuente que usa Letterboxd. Soporta español (`language=es-ES`), rate limit holgado (~40 req/s), CDN de imágenes gratis, y su licencia no comercial solo pide atribución (ya está en el footer). Descartadas: TVMaze (sin películas, licencia ShareAlike, solo inglés), OMDb (1.000 req/día, episodios sin sinopsis ni imágenes), Trakt (sin metadata rica), TheTVDB (licencia atada a facturación), Watchmode/JustWatch (sin tier gratuito útil).

### Reglas de producto de Letterboxd que el clon respeta
1. **Watched, rating, like, review y entrada de diario son estados independientes y combinables.** El diario es una colección de eventos fechados, no un flag.
2. Rating de **0.5 a 5 estrellas en medios pasos** (guardado como entero 1–10). Like (corazón) **independiente** del rating.
3. **Rewatch = nueva entrada de diario**, autodetectada si ya existe un log previo; cada entrada tiene su propio rating y review.
4. Reviews con **flag de spoilers** (ocultas por defecto), likes y comentarios. Una review atada a un log genera **un solo ítem** en el feed.
5. **Watchlist única** por usuario; al marcar como visto, el título sale automáticamente de la watchlist.
6. **Follow asimétrico** sin aprobación. El feed muestra **solo** entradas de diario y reviews — nunca watched/ratings sueltos.
7. **Promedio ponderado** (los títulos con pocos votos se amortiguan hacia la media global) con histograma de 10 barras.
8. Perfil con **4 favoritos destacados** y tabs.

---

## 3. Fases completadas

### ✅ Fase 1 — Setup, auth, roles, i18n y estética
- Proyecto Laravel 12 + starter kit Vue, `.env` apuntando a MySQL, base `movieboxd` creada.
- `users` extendida: `username` (único, para URLs `/u/{username}`), `role`, `avatar_path`, `bio`, `locale`, `banned_at`.
- Middleware: `EnsureUserIsAdmin` (alias `admin`), `EnsureUserIsNotBanned` (cierra sesión al vuelo), `SetLocale` (usuario → sesión → `es`).
- Registro con campo username; seeder de admin.
- **Theme con los tokens reales de Letterboxd**: fondo `#14181C`, superficies `#2C3440`/`#283038`, texto `#9AB`, metadatos `#678`; acentos verde `#00E054` (watched), naranja `#FF8000` (like), azul `#40BCF4` (watchlist). Fuentes Inter (UI) + Lora (prosa de reseñas). Headings de sección en mayúsculas con `letter-spacing: .075em`.
- `AppLayout` (header con logo de 3 puntos, búsqueda, menú de usuario; footer con **atribución obligatoria a TMDB**) y `AdminLayout`.
- i18n es/en: `resources/js/i18n/{es,en}.ts` + `lang/{es,en}/` para validaciones. Switch de idioma en el header (`PUT /settings/locale`).

### ✅ Fase 2 — Integración TMDB, catálogo y búsqueda
- `app/Services/Tmdb/TmdbClient.php`: cliente HTTP con caché de respuestas (búsquedas 1 h, detalles 1 h, trending 6 h). **La API key nunca sale del backend.**
- `app/Services/Tmdb/TmdbImportService.php`: **import on demand + snapshot local**. La búsqueda no persiste nada; los resultados apuntan a `/resolve/{type}/{tmdbId}`, que importa el título la primera vez y redirige a su URL canónica. Los episodios se importan **lazy** al visitar la temporada (1 llamada trae todos).
- Tablas del catálogo: `titles` (unificada, `type` movie/tv), `seasons`, `episodes` — con `translations` JSON (es+en en una sola llamada a TMDB) y `synced_at` para el staleness.
- Refresco: `RefreshTitle` (job con rate limit de 30 req/s) + comando `movieboxd:refresh-stale` programado a las 04:00. Umbrales: películas 30 días, series en emisión 24 h, series terminadas 30 días.
- Páginas: Home (trending), búsqueda, película, serie, temporada, episodio.

### ✅ Fase 3 — Trackeo core
- Tablas: `watched_titles`, `episode_watches`, `diary_entries` (morph a title/season/episode), `ratings`, `likes`, `watchlist_items`.
- Agregados cacheados en columnas (`ratings_count`, `ratings_sum`, `ratings_histogram`, `watched_count`, `likes_count`, `watchlist_count`, `reviews_count`) mantenidos por observers; comando `movieboxd:reconcile-aggregates` (04:30) recalcula todo desde las tablas fuente.
- Promedio ponderado en el trait `HasRatingAggregates` (fórmula estilo IMDb, `m` configurable desde admin).
- **Extensión propia para series**: marcar episodios individuales, marcar temporada completa (bulk idempotente), barra de progreso por serie y temporada.
- Componentes: `RatingStars` (medios pasos según posición del mouse), `LogModal` (fecha, estrellas, corazón, review, spoilers, tags, rewatch autodetectado — un solo POST atómico), `ActionsPanel` (ojo/corazón/reloj + estrellas + favorito + agregar a lista), `RatingHistogram`, `ProgressBar`, `PosterCard`.
- Páginas propias: Diario (agrupado por mes) y Watchlist.

### ✅ Fase 4 — Reviews y comentarios
- Tablas: `reviews` (morph a los 3 niveles + FK opcional a `diary_entries`), `comments`, `reports`.
- Reviews desde el LogModal (atadas al log) o standalone. Spoilers ocultos por defecto con botón para revelar.
- Likes y comentarios en reviews; el dueño del contenido puede borrar comentarios ajenos.
- Reviews populares en las páginas de título/temporada/episodio usando **deferred props** de Inertia 2.
- Botón de reportar (spoiler/spam/abuso/otro), un reporte pendiente por usuario y contenido.

### ✅ Fase 5 — Social
- `follows` (asimétrico) y `favorites` (máximo 4, con posición).
- `ActivityFeedService`: feed derivado con `UNION ALL` sobre `diary_entries` + reviews standalone de los seguidos. Cumple la regla: watched/ratings sueltos no aparecen; un log con review es un solo ítem.
- Perfil público `/u/{username}` con 7 tabs (Vistas, Diario, Reseñas, Watchlist, Listas, Me gusta, Red), 4 favoritos destacados, contadores y botón de seguir.

### ✅ Fase 6 — Listas
- `lists` + `list_items` con slug por usuario, descripción, toggle ranqueada, público/privada, nota por ítem.
- Drag & drop para reordenar (vuedraggable) con persistencia validada (rechaza sets de ítems que no coincidan); al quitar un ítem se resecuencian las posiciones.
- Likes y comentarios en listas; indicador "viste X de Y"; listas privadas invisibles para terceros (403).
- Agregar a lista desde el panel de acciones de cualquier título.

### ✅ Fase 7 — Panel de administración
- `/admin` — Resumen: contadores de usuarios, películas, series, registros, reseñas, comentarios, listas, baneados; aviso de reportes pendientes; usuarios recientes.
- `/admin/users` — Tabla con búsqueda por nombre/usuario/email, cambio de rol y ban/desban. **El ban borra las sesiones activas** (efecto inmediato). Un admin no puede degradarse ni banearse a sí mismo.
- `/admin/reports` — Cola de moderación filtrable (pendientes/resueltos/descartados/todos), con vista previa del contenido reportado, y acciones «Descartar» o «Borrar contenido» (el borrado ajusta los contadores vía observers).
- `/admin/settings` — API key de TMDB (**guardada encriptada**, nunca se devuelve al navegador), votos a priori `m` del promedio ponderado, y **feature flags** (registro, comentarios, listas, reseñas) que se aplican con el middleware `feature:` y se comparten al frontend.

---

### ✅ Fase 8 — Estadísticas y pulido
- **`app/Services/ProfileStatsService.php`** — todas las estadísticas del perfil: horas vistas (suma de `runtime` de películas + episodios, estimando 40′ cuando TMDB no lo trae), conteos de películas/series/episodios/registros/revisionados, promedio propio, actividad por año, distribución de las calificaciones propias en los 10 buckets, géneros más vistos, directores y creadores más vistos, décadas con el promedio propio, y títulos más revisionados. Los agregados que SQL resuelve bien van en SQL; los que dependen de columnas JSON (géneros, créditos) se cuentan en PHP sobre el set acotado de títulos vistos.
- **Tab `stats` en el perfil** (`/u/{username}/stats`), cargado con deferred prop de Inertia y skeleton con la misma geometría que el contenido final.
- **Componentes de gráfico** (`resources/js/components/charts/`): `BarChart.vue` (barras verticales con tooltip por marca, etiquetado selectivo del máximo) y `RankedBars.vue` (barras horizontales para etiquetas largas), más `StatTile.vue`. Color validado: una sola serie por gráfico → un único hue, el verde `#00E054` (contraste ≥3:1 contra `#283038` verificado con el validador de paletas); el gris `#445566` quedó solo como pista de fondo (1.74:1, insuficiente para datos). Sin leyendas ni dobles ejes.
- **Diario navegable por año** (`?year=YYYY`) en el tab del perfil, con test del filtro.
- **`EmptyState.vue`** aplicado en todas las páginas con listados: stats, diario (propio y de perfil), watchlist (propia y de perfil), vistas, me gusta, reseñas, listas (índice y perfil) y búsqueda. Skeleton en las reviews diferidas de `ReviewsSection.vue`.
- **SEO / Open Graph**: prop `meta` renderizada **server-side en `app.blade.php`** (sin SSR, el `<Head>` de Inertia no lo ven los crawlers) — `og:title`, `og:description`, `og:image` (póster TMDB w500), `og:type` y `twitter:card` en las páginas de película, serie, temporada, episodio, review y lista. Helper: `app/Support/PageMeta.php`.
- **Performance**: eliminados dos N+1 reales — (a) `likedByViewer` hacía un `exists()` por review al listar (ahora `ReviewController::likedReviewIds()` trae los likes del viewer en una sola query, usado en `popularFor` y en el tab de reseñas del perfil); (b) la `CommentPolicy` lazy-loadeaba `commentable` por cada comentario al calcular `canDelete` (ahora se setea la relación de antemano con `setRelation`, en review y lista).
- 7 tests nuevos de estadísticas y filtro por año.

### Notas de portabilidad

**1. SQLite (tests) vs MySQL (producción).** `YEAR()` no existe en SQLite: usar `SUBSTR(fecha, 1, 4)` en `selectRaw`/`groupBy` (`whereYear` de Laravel sí es portable). Aplicado en `ProfileController::diary()` y `ProfileStatsService::perYear()`.

**2. Case-sensitivity: Windows (local) vs Linux (GitHub Actions).** El default de `inertia-laravel` para ubicar los componentes en testing es `resource_path('js/Pages')` con **P mayúscula**, pero este proyecto usa `resources/js/pages` en minúscula. En Windows resuelve igual (filesystem case-insensitive); en Linux **fallan todos los tests con `assertInertia`** con `Inertia page component file [X] does not exist`. Solución: `config/inertia.php` publicado en el repo con el case real fijado en `page_paths` y `testing.page_paths`. Detalle del diagnóstico en la skill `.claude/skills/SKILL.md`.

### ✅ Módulo extra — Importador de Letterboxd (`/settings/import`)

Importa la exportación oficial de Letterboxd (ZIP, formato v7) al perfil del usuario.

- **Flujo**: subida del ZIP con casillas por sección (vistas+calificaciones, diario+reseñas, watchlist, me gusta, listas) → `PrepareLetterboxdImport` parsea y agrega por película única → `ProcessLetterboxdImport` procesa en tandas de 20 **auto-encadenándose** (secuencial, resumible, auto-limitado contra TMDB) → al final crea las listas, corre `movieboxd:reconcile-aggregates` y borra el ZIP. Progreso en vivo con `usePoll` de Inertia.
- **Matching**: `TmdbClient::searchMovie(nombre, año)` → sin año con tolerancia ±1 → `searchMulti` aceptando TV (miniseries). Sin match → reporte de no-matcheadas visible en la página.
- **Política de conflictos**: conserva siempre lo existente en Movieboxd (`firstOrCreate` en todo); re-ejecutar el import es idempotente. La watchlist no se aplica sobre títulos ya vistos. `diary.csv` y `reviews.csv` se deduplican (reviews es el subconjunto con texto); las reseñas multilínea se parsean con `fgetcsv`.
- **Archivos**: `app/Services/Letterboxd/{LetterboxdExportParser,LetterboxdImportService}.php`, `app/Jobs/{Prepare,Process}LetterboxdImport.php`, `app/Http/Controllers/Settings/ImportController.php`, `app/Models/LetterboxdImport{,Item}.php`, `resources/js/pages/settings/Import.vue`, migración `2026_07_30_000001`. Tests: `tests/Feature/Import/LetterboxdImportTest.php` (fixture ZIP real construido en el test).
- **No importable** (referencia contenido de otros usuarios de Letterboxd): `comments.csv`, `likes/reviews.csv`, `likes/lists.csv`; se ignoran `deleted/` y `orphaned/`.
- **Requiere** el worker de cola corriendo (`composer run dev` ya lo incluye, o `php artisan queue:work`).

## 4. Pendiente del usuario para el repaso final

El único punto del plan que no se puede cerrar sin vos: el **repaso funcional del happy path con datos reales** (buscar una serie, importarla, trackear episodios, etc.) necesita la **API key de TMDB** cargada (ver sección 1). Con la key puesta: registrarse, buscar "Breaking Bad", entrar a la serie, marcar episodios, loguear con reseña, y verificar el flujo en español e inglés.

### Ideas para después de la Fase 8 (no planificadas aún)
- Página de persona (actor/director) con filmografía — hoy los créditos se guardan como JSON en `titles.credits`, habría que normalizar a tablas `people`.
- Filtros y ordenamiento en `/films` y `/shows` (por década, género, popularidad, duración).
- Notificaciones (nuevos seguidores, comentarios en tus reseñas).
- Import/export CSV (Letterboxd tiene importador desde IMDb).
- Watch providers (`/watch/providers` de TMDB, datos de JustWatch — requiere atribución adicional).
- Calendario "qué se emite hoy" para series (TVMaze `/schedule` es más preciso que TMDB para esto).

---

## 5. Mapa de archivos clave

```
routes/web.php                              Mapa completo: público / auth / admin
app/Services/Tmdb/TmdbClient.php            Cliente HTTP + caché (la API key vive acá)
app/Services/Tmdb/TmdbImportService.php     Import on demand → snapshot en MySQL
app/Services/ActivityFeedService.php        Feed derivado por UNION
app/Models/Title.php                        Modelo unificado (movie/tv), traducciones, staleness
app/Models/Concerns/HasRatingAggregates.php Promedio ponderado + histograma
app/Models/Setting.php                      Config de admin (valores sensibles encriptados)
app/Http/Controllers/DiaryEntryController.php  Endpoint atómico del LogModal
app/Http/Controllers/Admin/                 Los 4 controllers del panel
app/Observers/                              Mantenimiento de contadores cacheados
resources/js/components/LogModal.vue        Componente central de UX (title/season/episode)
resources/js/components/ActionsPanel.vue    Ojo / corazón / reloj / estrellas / lista
resources/js/i18n/{es,en}.ts                Traducciones del frontend
resources/css/app.css                       Tokens de color de Letterboxd
tailwind.config.js                          Paleta lb-* y fuentes
tests/Feature/                              86 tests: catálogo, trackeo, reviews, social, listas, admin
```

### Comandos propios
```powershell
php artisan movieboxd:refresh-stale          # Encola refresco de títulos viejos (cron 04:00)
php artisan movieboxd:reconcile-aggregates   # Recalcula contadores desde las tablas fuente (cron 04:30)
```

---

## 6. Notas para retomar

- **Los tests nunca golpean la API real**: todos usan `Http::fake()`. Mantener esa regla.
- **El plan maestro original** (con el detalle completo de la investigación de Letterboxd, la comparativa de APIs y el esquema de base de datos) está en `C:\Users\pghm\.claude\plans\movieboxd-es-un-proyecto-peaceful-church.md`.
- La app es **dark-only** por diseño (como Letterboxd): `:root` y `.dark` comparten la misma paleta.
- Los morphs usan alias cortos (`title`, `season`, `episode`, `review`, `comment`, `list`, `user`) vía `Relation::enforceMorphMap()` — nunca se serializan FQCNs en la base.
- `role` y `banned_at` **no son mass-assignable** a propósito: solo se modifican desde el controller de admin.
