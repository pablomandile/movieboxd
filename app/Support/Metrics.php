<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Contadores diarios para el panel de estadísticas del admin (tabla daily_metrics).
 */
class Metrics
{
    public const TMDB_REQUESTS = 'tmdb.requests';

    public const IMPORTED_TITLES = 'imports.titles';

    public const IMPORTED_PEOPLE = 'imports.people';

    public const IMPORTED_SEASONS = 'imports.seasons';

    public const BLOCKED_CRAWLERS = 'blocked.crawlers';

    public const BLOCKED_QUOTA = 'blocked.quota';

    public const PRUNED_TITLES = 'pruned.titles';

    public const PRUNED_PEOPLE = 'pruned.people';

    public const VISITS = 'visits';

    public const PAGEVIEWS = 'pageviews';

    public const LOGINS = 'logins';

    /** Seguido del nombre de la acción (ver App\Support\Usage::ACTIONS). */
    public const ACTIONS_PREFIX = 'actions.';

    public static function increment(string $metric, int $by = 1): void
    {
        if ($by <= 0) {
            return;
        }

        // Una estadística que no se pudo anotar nunca tiene que romper el pedido
        try {
            DB::table('daily_metrics')->upsert(
                [['date' => now()->toDateString(), 'metric' => $metric, 'total' => $by]],
                ['date', 'metric'],
                ['total' => DB::raw("total + {$by}")]
            );
        } catch (Throwable $e) {
            Log::warning("No se pudo anotar la métrica {$metric}: {$e->getMessage()}");
        }
    }
}
