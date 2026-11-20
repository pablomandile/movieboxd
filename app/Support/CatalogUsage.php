<?php

namespace App\Support;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qué parte del catálogo de TMDB está en uso por los usuarios. Todo lo demás es
 * un snapshot que se puede volver a importar cuando alguien lo pida.
 */
class CatalogUsage
{
    /** Tablas que apuntan directo a un título. */
    private const DIRECT = ['watched_titles', 'watchlist_items', 'favorites', 'list_items', 'episode_watches', 'letterboxd_import_items'];

    /** Tablas polimórficas que pueden apuntar a un título, una temporada o un episodio. */
    private const MORPHS = ['ratings' => 'rateable', 'reviews' => 'reviewable', 'likes' => 'likeable', 'diary_entries' => 'loggable'];

    /**
     * IDs de los títulos con algún dato de usuario, propio o de sus temporadas y
     * episodios, más los que necesita la demo para resembrarse.
     *
     * @return Collection<int, int>
     */
    public static function protectedTitleIds(): Collection
    {
        $ids = collect();

        foreach (self::DIRECT as $table) {
            $ids = $ids->merge(DB::table($table)->whereNotNull('title_id')->distinct()->pluck('title_id'));
        }

        foreach (self::MORPHS as $table => $morph) {
            $ids = $ids
                ->merge(DB::table($table)->where("{$morph}_type", 'title')->distinct()->pluck("{$morph}_id"))
                ->merge(DB::table('seasons')->whereIn('id', DB::table($table)->where("{$morph}_type", 'season')->select("{$morph}_id"))->pluck('title_id'))
                ->merge(DB::table('episodes')->whereIn('id', DB::table($table)->where("{$morph}_type", 'episode')->select("{$morph}_id"))->pluck('title_id'));
        }

        // La demo se resiembra cada noche solo con títulos que ya están en la base
        if (config('demo.enabled')) {
            foreach (DemoSeeder::catalogo() as $type => $tmdbIds) {
                $ids = $ids->merge(DB::table('titles')->where('type', $type)->whereIn('tmdb_id', $tmdbIds)->pluck('id'));
            }
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }
}
