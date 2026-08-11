<?php

namespace App\Services\Tmdb;

use App\Enums\TitleType;
use App\Models\Person;
use App\Models\Season;
use App\Models\Setting;
use App\Models\Title;
use Illuminate\Support\Str;

/**
 * Convierte payloads de TMDB en snapshots locales (upsert de Eloquent).
 *
 * Import on demand: los títulos se importan la primera vez que alguien
 * los visita; los episodios de una temporada, al visitar la temporada.
 */
class TmdbImportService
{
    public function __construct(protected TmdbClient $client) {}

    public function importTitle(TitleType $type, int $tmdbId): Title
    {
        return $type === TitleType::Movie
            ? $this->importMovie($tmdbId)
            : $this->importTv($tmdbId);
    }

    public function importMovie(int $tmdbId): Title
    {
        $data = $this->client->movie($tmdbId);

        return $this->upsertTitle(TitleType::Movie, $tmdbId, $data['title'] ?? (string) $tmdbId, [
            'title' => $data['title'],
            'original_title' => $data['original_title'] ?? null,
            'translations' => $this->extractTranslations($data),
            'overview' => $data['overview'] ?: null,
            'tagline' => $data['tagline'] ?: null,
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'release_date' => $data['release_date'] ?: null,
            'runtime' => $data['runtime'] ?? null,
            'genres' => $data['genres'] ?? [],
            'credits' => $this->extractMovieCredits($data['credits'] ?? []),
            'watch_providers' => $this->extractWatchProviders($data),
            'watch_providers_synced_at' => now(),
            'original_language' => $data['original_language'] ?? null,
            'popularity' => $data['popularity'] ?? 0,
            'synced_at' => now(),
        ]);
    }

    public function importTv(int $tmdbId): Title
    {
        $data = $this->client->tv($tmdbId);

        $title = $this->upsertTitle(TitleType::Tv, $tmdbId, $data['name'] ?? (string) $tmdbId, [
            'title' => $data['name'],
            'original_title' => $data['original_name'] ?? null,
            'translations' => $this->extractTranslations($data),
            'overview' => $data['overview'] ?: null,
            'tagline' => $data['tagline'] ?: null,
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'release_date' => $data['first_air_date'] ?: null,
            'genres' => $data['genres'] ?? [],
            'credits' => $this->extractTvCredits($data),
            'watch_providers' => $this->extractWatchProviders($data),
            'watch_providers_synced_at' => now(),
            'original_language' => $data['original_language'] ?? null,
            'popularity' => $data['popularity'] ?? 0,
            'tv_status' => $data['status'] ?? null,
            'last_air_date' => $data['last_air_date'] ?: null,
            'seasons_count' => $data['number_of_seasons'] ?? null,
            'episodes_count' => $data['number_of_episodes'] ?? null,
            'synced_at' => now(),
        ]);

        // Stubs de temporadas: los episodios llegan lazy vía importSeason()
        foreach ($data['seasons'] ?? [] as $seasonData) {
            $title->seasons()->updateOrCreate(
                ['season_number' => $seasonData['season_number']],
                [
                    'tmdb_id' => $seasonData['id'],
                    'name' => $seasonData['name'] ?? "Season {$seasonData['season_number']}",
                    'overview' => $seasonData['overview'] ?: null,
                    'poster_path' => $seasonData['poster_path'] ?? null,
                    'air_date' => $seasonData['air_date'] ?: null,
                    'episodes_count' => $seasonData['episode_count'] ?? 0,
                ]
            );
        }

        return $title;
    }

    public function importSeason(Title $title, int $seasonNumber): Season
    {
        $data = $this->client->tvSeason($title->tmdb_id, $seasonNumber);

        $season = $title->seasons()->updateOrCreate(
            ['season_number' => $seasonNumber],
            [
                'tmdb_id' => $data['id'],
                'name' => $data['name'] ?? "Season {$seasonNumber}",
                'overview' => $data['overview'] ?: null,
                'translations' => $this->extractTranslations($data, 'name'),
                'poster_path' => $data['poster_path'] ?? null,
                'air_date' => $data['air_date'] ?: null,
                'episodes_count' => count($data['episodes'] ?? []),
                'synced_at' => now(),
            ]
        );

        foreach ($data['episodes'] ?? [] as $episodeData) {
            $season->episodes()->updateOrCreate(
                ['episode_number' => $episodeData['episode_number']],
                [
                    'title_id' => $title->id,
                    'tmdb_id' => $episodeData['id'],
                    'season_number' => $seasonNumber,
                    'name' => $episodeData['name'] ?? "Episode {$episodeData['episode_number']}",
                    'overview' => $episodeData['overview'] ?: null,
                    'still_path' => $episodeData['still_path'] ?? null,
                    'air_date' => $episodeData['air_date'] ?: null,
                    'runtime' => $episodeData['runtime'] ?? null,
                ]
            );
        }

        return $season->fresh(['episodes']);
    }

    /**
     * Re-importa un título existente (refresco de snapshot viejo).
     */
    public function refresh(Title $title): Title
    {
        return $this->importTitle($title->type, $title->tmdb_id);
    }

    protected function upsertTitle(TitleType $type, int $tmdbId, string $displayTitle, array $attributes): Title
    {
        $existing = Title::where('type', $type)->where('tmdb_id', $tmdbId)->first();

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        // El slug se genera una sola vez: las URLs son estables
        return Title::create($attributes + [
            'type' => $type,
            'tmdb_id' => $tmdbId,
            'slug' => $this->makeSlug($displayTitle, $tmdbId),
        ]);
    }

    protected function makeSlug(string $name, int $tmdbId): string
    {
        $slug = Str::slug($name) ?: (string) $tmdbId;

        return Title::where('slug', $slug)->exists() ? "{$slug}-{$tmdbId}" : $slug;
    }

    /**
     * Importa (o refresca) una persona con su filmografía.
     */
    public function importPerson(int $tmdbId): Person
    {
        $data = $this->client->person($tmdbId);
        $name = $data['name'] ?? (string) $tmdbId;

        $person = Person::firstOrNew(['tmdb_id' => $tmdbId]);

        $person->fill([
            'name' => $name,
            'profile_path' => $data['profile_path'] ?? null,
            'biography' => $data['biography'] ?: null,
            'translations' => $this->extractPersonTranslations($data),
            'birthday' => $data['birthday'] ?: null,
            'deathday' => $data['deathday'] ?: null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'known_for_department' => $data['known_for_department'] ?? null,
            'credits' => $this->extractFilmography($data['combined_credits'] ?? []),
            'popularity' => $data['popularity'] ?? 0,
            'synced_at' => now(),
        ]);

        // El slug se fija en el primer import y no se toca después: es su URL
        $person->slug ??= $this->uniquePersonSlug($name, $tmdbId);
        $person->save();

        return $person;
    }

    /**
     * Agrupa la filmografía por el rol que cumplió: actuación por un lado y
     * los trabajos de equipo (dirección, producción, guion) por otro, que es
     * como se muestra en la ficha.
     */
    protected function extractFilmography(array $credits): array
    {
        $map = fn (array $entry, ?string $role) => [
            'tmdbId' => $entry['id'],
            'type' => ($entry['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie',
            'title' => $entry['title'] ?? $entry['name'] ?? '',
            'posterPath' => $entry['poster_path'] ?? null,
            'year' => ($entry['release_date'] ?? $entry['first_air_date'] ?? null)
                ? (int) substr($entry['release_date'] ?? $entry['first_air_date'], 0, 4)
                : null,
            'role' => $role ?: null,
            'popularity' => $entry['popularity'] ?? 0,
        ];

        // Lo más reciente primero; sin fecha al final
        $sort = fn ($items) => collect($items)
            ->sortByDesc(fn (array $item) => $item['year'] ?? 0)
            ->values()
            ->all();

        $acting = collect($credits['cast'] ?? [])
            ->map(fn (array $entry) => $map($entry, $entry['character'] ?? null))
            ->all();

        // Un mismo título puede aparecer varias veces (varios episodios o cargos):
        // se deja una sola entrada por título y rol.
        $crewBy = function (callable $filter) use ($credits, $map) {
            return collect($credits['crew'] ?? [])
                ->filter($filter)
                ->map(fn (array $entry) => $map($entry, $entry['job'] ?? null))
                ->unique(fn (array $item) => $item['type'].$item['tmdbId'].$item['role'])
                ->all();
        };

        $groups = [
            'directing' => $crewBy(fn (array $c) => ($c['job'] ?? null) === 'Director'),
            'acting' => $acting,
            'producing' => $crewBy(fn (array $c) => ($c['department'] ?? null) === 'Production' && ($c['job'] ?? null) !== 'Director'),
            'writing' => $crewBy(fn (array $c) => ($c['department'] ?? null) === 'Writing'),
        ];

        return collect($groups)
            ->map($sort)
            ->filter(fn (array $items) => $items !== [])
            ->all();
    }

    protected function extractPersonTranslations(array $data): array
    {
        $out = [];

        foreach ($data['translations']['translations'] ?? [] as $translation) {
            $locale = $translation['iso_639_1'] ?? null;

            if (! in_array($locale, ['es', 'en'], true)) {
                continue;
            }

            $biography = $translation['data']['biography'] ?? null;

            if ($biography) {
                $out[$locale] = ['biography' => $biography];
            }
        }

        return $out;
    }

    protected function uniquePersonSlug(string $name, int $tmdbId): string
    {
        $slug = Str::slug($name) ?: (string) $tmdbId;

        return Person::where('slug', $slug)->exists() ? "{$slug}-{$tmdbId}" : $slug;
    }

    /**
     * Dónde se puede ver, para la región configurada. Los datos son de JustWatch
     * (vía TMDB) y su uso obliga a atribuirlos y a enlazar la página que viene
     * en `link`; por eso el link se guarda junto con los proveedores.
     *
     * Se guarda solo la región elegida: el payload trae ~70 países y almacenarlos
     * todos multiplicaría el tamaño del snapshot sin que se usen.
     */
    protected function extractWatchProviders(array $data): ?array
    {
        $region = strtoupper(Setting::get('watch.region', config('services.tmdb.watch_region', 'AR')));
        $entry = $data['watch/providers']['results'][$region] ?? null;

        if (! $entry) {
            return null;
        }

        $map = fn (array $providers) => collect($providers)
            ->sortBy('display_priority')
            ->map(fn (array $provider) => [
                'id' => $provider['provider_id'],
                'name' => $provider['provider_name'],
                'logo' => $provider['logo_path'] ?? null,
            ])
            ->values()
            ->all();

        // flatrate = incluido en la suscripción; ads/free = gratis con o sin avisos
        $offers = collect(['flatrate', 'free', 'ads', 'rent', 'buy'])
            ->mapWithKeys(fn (string $type) => [$type => $map($entry[$type] ?? [])])
            ->filter(fn (array $providers) => $providers !== [])
            ->all();

        if ($offers === []) {
            return null;
        }

        return [
            'region' => $region,
            'link' => $entry['link'] ?? null,
            'offers' => $offers,
        ];
    }

    /**
     * Extrae es/en del append translations (una sola llamada trae todo).
     * Movies usan data.title, series y temporadas usan data.name.
     */
    protected function extractTranslations(array $data, string $nameKey = 'title'): array
    {
        $out = [];

        foreach ($data['translations']['translations'] ?? [] as $translation) {
            $lang = $translation['iso_639_1'] ?? null;

            if (! in_array($lang, ['es', 'en'], true)) {
                continue;
            }

            $entry = array_filter([
                'title' => $translation['data'][$nameKey] ?? $translation['data']['name'] ?? null,
                'overview' => $translation['data']['overview'] ?? null,
                'tagline' => $translation['data']['tagline'] ?? null,
            ]);

            if ($entry === []) {
                continue;
            }

            // Ante varias variantes del mismo idioma (es-ES/es-MX), gana la más completa
            if (! isset($out[$lang]) || count($entry) > count($out[$lang])) {
                $out[$lang] = $entry;
            }
        }

        return $out;
    }

    protected function extractMovieCredits(array $credits): array
    {
        return [
            'cast' => collect($credits['cast'] ?? [])->take(20)->map(fn (array $person) => [
                'tmdb_id' => $person['id'],
                'name' => $person['name'],
                'character' => $person['character'] ?? null,
                'profile_path' => $person['profile_path'] ?? null,
            ])->values()->all(),
            'directors' => collect($credits['crew'] ?? [])
                ->filter(fn (array $person) => ($person['job'] ?? null) === 'Director')
                ->map(fn (array $person) => [
                    'tmdb_id' => $person['id'],
                    'name' => $person['name'],
                    'profile_path' => $person['profile_path'] ?? null,
                ])->values()->all(),
        ];
    }

    protected function extractTvCredits(array $data): array
    {
        return [
            'cast' => collect($data['aggregate_credits']['cast'] ?? [])->take(20)->map(fn (array $person) => [
                'tmdb_id' => $person['id'],
                'name' => $person['name'],
                'character' => $person['roles'][0]['character'] ?? null,
                'profile_path' => $person['profile_path'] ?? null,
            ])->values()->all(),
            'creators' => collect($data['created_by'] ?? [])->map(fn (array $person) => [
                'tmdb_id' => $person['id'],
                'name' => $person['name'],
                'profile_path' => $person['profile_path'] ?? null,
            ])->values()->all(),
        ];
    }
}
