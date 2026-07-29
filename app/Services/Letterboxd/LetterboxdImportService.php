<?php

namespace App\Services\Letterboxd;

use App\Enums\TitleType;
use App\Models\DiaryEntry;
use App\Models\LetterboxdImport;
use App\Models\LetterboxdImportItem;
use App\Models\Like;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbImportService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Matchea cada película del export contra TMDB y aplica el payload del
 * usuario de forma IDEMPOTENTE: nunca pisa datos existentes de Movieboxd
 * (política acordada), así re-ejecutar un import es seguro.
 */
class LetterboxdImportService
{
    public function __construct(
        protected TmdbClient $tmdb,
        protected TmdbImportService $importer,
        protected LetterboxdExportParser $parser,
    ) {}

    /**
     * Procesa un ítem: match + aplicación. Devuelve los conteos de lo
     * efectivamente creado (vacío si no hubo match).
     *
     * @return array<string, int>
     */
    public function processItem(LetterboxdImportItem $item): array
    {
        try {
            $title = $this->match($item->name, $item->year);
        } catch (Throwable $exception) {
            $item->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 240),
            ]);

            return [];
        }

        if ($title === null) {
            $item->update(['status' => 'unmatched', 'error' => 'Sin resultados en TMDB']);

            return [];
        }

        $counts = $this->apply($item->import->user, $title, $item->payload);

        $item->update(['status' => 'matched', 'title_id' => $title->id]);

        return $counts;
    }

    /**
     * Estrategia de matching: película con año → película sin año (con
     * tolerancia ±1) → multi-búsqueda aceptando TV (miniseries que
     * Letterboxd trata como films).
     */
    public function match(string $name, ?int $year): ?Title
    {
        if ($year !== null) {
            $results = $this->tmdb->searchMovie($name, $year)['results'] ?? [];

            if ($results !== []) {
                return $this->resolveResult($results[0], TitleType::Movie);
            }
        }

        $results = $this->tmdb->searchMovie($name)['results'] ?? [];

        foreach ($results as $result) {
            $resultYear = isset($result['release_date']) && $result['release_date'] !== ''
                ? (int) substr($result['release_date'], 0, 4)
                : null;

            if ($year === null || $resultYear === null || abs($resultYear - $year) <= 1) {
                return $this->resolveResult($result, TitleType::Movie);
            }
        }

        foreach ($this->tmdb->searchMulti($name, 'en')['results'] ?? [] as $result) {
            $mediaType = $result['media_type'] ?? null;

            if ($mediaType === 'movie') {
                return $this->resolveResult($result, TitleType::Movie);
            }

            if ($mediaType === 'tv') {
                return $this->resolveResult($result, TitleType::Tv);
            }
        }

        return null;
    }

    protected function resolveResult(array $result, TitleType $type): Title
    {
        return Title::where('type', $type)->where('tmdb_id', $result['id'])->first()
            ?? $this->importer->importTitle($type, $result['id']);
    }

    /**
     * Aplica el payload conservando lo existente en Movieboxd.
     *
     * @return array<string, int>
     */
    protected function apply(User $user, Title $title, array $payload): array
    {
        $counts = ['watched' => 0, 'ratings' => 0, 'likes' => 0, 'watchlist' => 0, 'diary' => 0, 'reviews' => 0];

        if (! empty($payload['watched_at'])) {
            $watched = WatchedTitle::firstOrCreate(
                ['user_id' => $user->id, 'title_id' => $title->id],
                ['created_at' => $payload['watched_at'], 'updated_at' => $payload['watched_at']]
            );

            $counts['watched'] += (int) $watched->wasRecentlyCreated;
        }

        if (! empty($payload['rating'])) {
            $rating = Rating::firstOrCreate(
                ['user_id' => $user->id, 'rateable_type' => 'title', 'rateable_id' => $title->id],
                ['value' => min(10, max(1, (int) $payload['rating']))]
            );

            $counts['ratings'] += (int) $rating->wasRecentlyCreated;
        }

        if (! empty($payload['liked_at'])) {
            $like = Like::firstOrCreate([
                'user_id' => $user->id,
                'likeable_type' => 'title',
                'likeable_id' => $title->id,
            ]);

            $counts['likes'] += (int) $like->wasRecentlyCreated;
        }

        foreach ($payload['diary'] ?? [] as $record) {
            $counts = $this->applyDiaryRecord($user, $title, $record, $counts);
        }

        // La watchlist va al final: si el título quedó visto (por watched o
        // por diario), no se agrega (regla del producto)
        if (! empty($payload['watchlist_at'])) {
            $isWatched = WatchedTitle::where('user_id', $user->id)->where('title_id', $title->id)->exists();

            if (! $isWatched) {
                $item = WatchlistItem::firstOrCreate(
                    ['user_id' => $user->id, 'title_id' => $title->id],
                    ['created_at' => $payload['watchlist_at'], 'updated_at' => $payload['watchlist_at']]
                );

                $counts['watchlist'] += (int) $item->wasRecentlyCreated;
            }
        }

        return $counts;
    }

    protected function applyDiaryRecord(User $user, Title $title, array $record, array $counts): array
    {
        $exists = DiaryEntry::where('user_id', $user->id)
            ->where('loggable_type', 'title')
            ->where('loggable_id', $title->id)
            ->whereDate('watched_on', $record['watched_on'])
            ->exists();

        if ($exists) {
            return $counts;
        }

        $entry = DiaryEntry::create([
            'user_id' => $user->id,
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => $record['watched_on'],
            'rating' => $record['rating'] ?? null,
            'is_rewatch' => (bool) ($record['rewatch'] ?? false),
            'tags' => $record['tags'] ?? [],
        ]);

        $counts['diary']++;

        if (! empty($record['review'])) {
            $duplicate = Review::where('user_id', $user->id)
                ->where('reviewable_type', 'title')
                ->where('reviewable_id', $title->id)
                ->where('body', $record['review'])
                ->exists();

            if (! $duplicate) {
                Review::create([
                    'user_id' => $user->id,
                    'reviewable_type' => 'title',
                    'reviewable_id' => $title->id,
                    'diary_entry_id' => $entry->id,
                    'body' => $record['review'],
                    'contains_spoilers' => false,
                ]);

                $counts['reviews']++;
            }
        }

        return $counts;
    }

    /**
     * Crea las listas del export con los ítems que lograron match.
     *
     * @return array{lists: int, listItems: int}
     */
    public function createLists(LetterboxdImport $import): array
    {
        $created = ['lists' => 0, 'listItems' => 0];

        // title_id de cada film matcheado, indexado por la clave name|year
        $titleIds = [];

        foreach ($import->items()->where('status', 'matched')->get(['name', 'year', 'title_id']) as $item) {
            $titleIds[$this->parser->filmKey($item->name, $item->year)] = $item->title_id;
        }

        foreach ($import->lists_meta ?? [] as $definition) {
            $slug = Str::slug($definition['name']) ?: 'lista';

            $list = ListModel::firstOrCreate(
                ['user_id' => $import->user_id, 'slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'is_ranked' => false,
                    'is_public' => true,
                ]
            );

            $created['lists'] += (int) $list->wasRecentlyCreated;

            foreach ($definition['items'] ?? [] as $listItem) {
                $titleId = $titleIds[$this->parser->filmKey($listItem['name'], $listItem['year'])] ?? null;

                if ($titleId === null) {
                    continue;
                }

                $item = ListItem::firstOrCreate(
                    ['list_id' => $list->id, 'title_id' => $titleId],
                    ['position' => (int) $listItem['position']]
                );

                $created['listItems'] += (int) $item->wasRecentlyCreated;
            }
        }

        return $created;
    }
}
