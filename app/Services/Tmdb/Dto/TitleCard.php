<?php

namespace App\Services\Tmdb\Dto;

use App\Models\Title;

/**
 * Forma unificada de "tarjeta con póster" para grillas del frontend,
 * tanto para títulos ya importados como para resultados crudos de TMDB.
 */
class TitleCard
{
    /**
     * @return array{type: string, tmdbId: int, title: string, posterPath: ?string, year: ?int, url: string}|null
     */
    public static function fromTmdb(array $item): ?array
    {
        $type = $item['media_type'] ?? null;

        if (! in_array($type, ['movie', 'tv'], true)) {
            return null;
        }

        $date = $type === 'movie' ? ($item['release_date'] ?? null) : ($item['first_air_date'] ?? null);

        return [
            'type' => $type,
            'tmdbId' => $item['id'],
            'title' => $type === 'movie' ? ($item['title'] ?? '') : ($item['name'] ?? ''),
            'posterPath' => $item['poster_path'] ?? null,
            'year' => $date ? (int) substr($date, 0, 4) : null,
            'url' => route('titles.resolve', ['type' => $type, 'tmdbId' => $item['id']]),
        ];
    }

    public static function fromModel(Title $title): array
    {
        return [
            'type' => $title->type->value,
            'tmdbId' => $title->tmdb_id,
            'title' => $title->localized_title,
            'posterPath' => $title->poster_path,
            'year' => $title->year,
            'url' => $title->isMovie()
                ? route('films.show', $title)
                : route('shows.show', $title),
        ];
    }
}
