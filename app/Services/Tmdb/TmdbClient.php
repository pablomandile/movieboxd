<?php

namespace App\Services\Tmdb;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP crudo contra la API v3 de TMDB.
 *
 * La API key vive solo en el backend (config/services.php ← .env).
 * Las respuestas crudas se cachean en la Cache de Laravel para absorber
 * ráfagas; la persistencia real es el snapshot en MySQL (TmdbImportService).
 */
class TmdbClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        // La key del panel de admin tiene prioridad sobre la del .env
        $this->apiKey ??= Setting::get('tmdb.api_key') ?: config('services.tmdb.key');
        $this->baseUrl ??= config('services.tmdb.base_url');
    }

    public function searchMulti(string $query, string $locale = 'es', int $page = 1): array
    {
        $key = 'tmdb:search:'.$locale.':'.md5($query).':'.$page;

        return Cache::remember($key, now()->addHour(), fn () => $this->get('/search/multi', [
            'query' => $query,
            'language' => $this->language($locale),
            'page' => $page,
            'include_adult' => 'false',
        ]));
    }

    /**
     * Búsqueda de películas por título (y año opcional). Usada por el
     * importador de Letterboxd: los nombres del export vienen en inglés.
     */
    public function searchMovie(string $query, ?int $year = null, int $page = 1): array
    {
        $key = 'tmdb:search-movie:'.md5($query).':'.($year ?? 'any').':'.$page;

        return Cache::remember($key, now()->addDay(), fn () => $this->get('/search/movie', array_filter([
            'query' => $query,
            'primary_release_year' => $year,
            'page' => $page,
            'include_adult' => 'false',
            'language' => 'en-US',
        ], fn ($value) => $value !== null)));
    }

    public function movie(int $tmdbId): array
    {
        return Cache::remember("tmdb:movie:{$tmdbId}", now()->addHour(), fn () => $this->get("/movie/{$tmdbId}", [
            'append_to_response' => 'credits,translations,watch/providers',
        ]));
    }

    public function tv(int $tmdbId): array
    {
        return Cache::remember("tmdb:tv:{$tmdbId}", now()->addHour(), fn () => $this->get("/tv/{$tmdbId}", [
            'append_to_response' => 'aggregate_credits,translations,watch/providers',
        ]));
    }

    /**
     * Persona con su filmografía completa. combined_credits mezcla cine y TV,
     * que es justo lo que muestra la ficha.
     */
    public function person(int $tmdbId): array
    {
        return Cache::remember("tmdb:person:{$tmdbId}", now()->addHour(), fn () => $this->get("/person/{$tmdbId}", [
            'append_to_response' => 'combined_credits,translations',
        ]));
    }

    public function tvSeason(int $tvTmdbId, int $seasonNumber): array
    {
        return Cache::remember(
            "tmdb:tv:{$tvTmdbId}:season:{$seasonNumber}",
            now()->addHour(),
            fn () => $this->get("/tv/{$tvTmdbId}/season/{$seasonNumber}", [
                'append_to_response' => 'translations',
            ])
        );
    }

    /**
     * Trending de películas y series combinadas (descarta personas).
     */
    public function trending(string $window = 'week', string $locale = 'es'): array
    {
        // v2: se cachean 2 páginas ya fusionadas (una sola no llena 4 filas de 6)
        $results = Cache::remember(
            "tmdb:trending:all:v2:{$window}:{$locale}",
            now()->addHours(6),
            fn () => $this->trendingPages('all', $window, $locale)
        );

        return array_values(array_filter(
            $results,
            fn (array $item) => in_array($item['media_type'] ?? null, ['movie', 'tv'], true)
        ));
    }

    /**
     * Tendencias de un solo tipo (movie|tv), para las solapas de la home.
     */
    public function trendingType(string $type, string $window = 'week', string $locale = 'es'): array
    {
        $results = Cache::remember(
            "tmdb:trending:{$type}:{$window}:{$locale}",
            now()->addHours(6),
            fn () => $this->trendingPages($type, $window, $locale)
        );

        // El union no pisa la clave si ya viene en el payload
        return array_map(fn (array $item) => $item + ['media_type' => $type], $results);
    }

    protected function trendingPages(string $type, string $window, string $locale, int $pages = 2): array
    {
        $results = [];

        for ($page = 1; $page <= $pages; $page++) {
            $payload = $this->get("/trending/{$type}/{$window}", [
                'language' => $this->language($locale),
                'page' => $page,
            ]);

            $results = array_merge($results, $payload['results'] ?? []);
        }

        return $results;
    }

    protected function get(string $path, array $query = []): array
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout(10)
            ->retry(3, 200)
            ->get($path, array_merge(['api_key' => $this->apiKey], $query))
            ->throw()
            ->json();
    }

    protected function language(string $locale): string
    {
        return $locale === 'es' ? 'es-ES' : 'en-US';
    }
}
