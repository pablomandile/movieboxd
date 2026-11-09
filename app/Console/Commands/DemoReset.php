<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Like;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deja la demo como nueva: borra los usuarios (y con ellos, en cascada, todo lo que
 * cargaron) y resiembra el usuario demo con sus amigos. Corre todas las noches en
 * la copia de pablomandile (ver routes/console.php).
 *
 * A diferencia de otras demos NO hace migrate:fresh: el catálogo de TMDB (títulos,
 * temporadas, episodios, personas) pesa más de un GB y es lo que la demo muestra.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Borra los usuarios y sus datos y resiembra la demo (solo con APP_DEMO=true)';

    public function handle(): int
    {
        // Sin el flag no se toca nada: este comando borra todos los usuarios.
        if (! config('demo.enabled')) {
            $this->error('La demo no está activa (APP_DEMO). No se borró nada.');

            return self::FAILURE;
        }

        // El borrado en cascada no dispara los observers que mantienen los contadores
        // de cada título: se anota a quién tocaba para recalcularlo después.
        $afectados = $this->sujetosConDatosDeUsuarios();

        DB::transaction(function () {
            DB::table('users')->delete();
            foreach (['sessions', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs'] as $tabla) {
                DB::table($tabla)->delete();
            }
        });
        Cache::flush();

        // Exportaciones de Letterboxd subidas por usuarios reales antes del corte.
        Storage::disk('local')->deleteDirectory('letterboxd');

        $this->recalcular($afectados);

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Demo reiniciada.');

        return self::SUCCESS;
    }

    /**
     * @return array{title: Collection<int, int>, season: Collection<int, int>, episode: Collection<int, int>}
     */
    private function sujetosConDatosDeUsuarios(): array
    {
        $ids = fn (string $tabla, string $tipoCol, string $idCol, string $tipo) => DB::table($tabla)
            ->where($tipoCol, $tipo)->distinct()->pluck($idCol);

        return [
            'title' => DB::table('watched_titles')->distinct()->pluck('title_id')
                ->merge(DB::table('watchlist_items')->distinct()->pluck('title_id'))
                ->merge($ids('ratings', 'rateable_type', 'rateable_id', 'title'))
                ->merge($ids('reviews', 'reviewable_type', 'reviewable_id', 'title'))
                ->merge($ids('likes', 'likeable_type', 'likeable_id', 'title'))
                ->unique()->values(),
            'season' => $ids('ratings', 'rateable_type', 'rateable_id', 'season')
                ->merge($ids('reviews', 'reviewable_type', 'reviewable_id', 'season'))
                ->unique()->values(),
            'episode' => $ids('ratings', 'rateable_type', 'rateable_id', 'episode')
                ->merge($ids('reviews', 'reviewable_type', 'reviewable_id', 'episode'))
                ->unique()->values(),
        ];
    }

    /**
     * Lo mismo que movieboxd:reconcile-aggregates, pero solo sobre lo afectado.
     *
     * @param  array{title: Collection<int, int>, season: Collection<int, int>, episode: Collection<int, int>}  $afectados
     */
    private function recalcular(array $afectados): void
    {
        $reviews = fn (string $tipo, int $id) => Review::where('reviewable_type', $tipo)->where('reviewable_id', $id)->count();

        foreach ($afectados['title']->chunk(500) as $ids) {
            Title::whereIn('id', $ids)->get()->each(function (Title $title) use ($reviews) {
                $title->recalculateRatingAggregates();
                $title->forceFill([
                    'watched_count' => WatchedTitle::where('title_id', $title->id)->count(),
                    'watchlist_count' => WatchlistItem::where('title_id', $title->id)->count(),
                    'likes_count' => Like::where('likeable_type', 'title')->where('likeable_id', $title->id)->count(),
                    'reviews_count' => $reviews('title', $title->id),
                ])->saveQuietly();
            });
        }

        foreach (['season' => Season::class, 'episode' => Episode::class] as $tipo => $clase) {
            foreach ($afectados[$tipo]->chunk(500) as $ids) {
                $clase::whereIn('id', $ids)->get()->each(function ($modelo) use ($tipo, $reviews) {
                    $modelo->recalculateRatingAggregates();
                    $modelo->forceFill(['reviews_count' => $reviews($tipo, $modelo->id)])->saveQuietly();
                });
            }
        }
    }
}
