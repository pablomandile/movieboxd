<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * El store "database" de Laravel nunca borra las filas vencidas: solo las pisa
 * si alguien vuelve a pedir esa clave. Sin esta limpieza la tabla crece sin
 * límite (cada búsqueda distinta deja su fila para siempre).
 */
class PruneExpiredCache extends Command
{
    protected $signature = 'movieboxd:prune-cache';

    protected $description = 'Borra las entradas vencidas de la tabla de caché';

    public function handle(): int
    {
        $store = config('cache.stores.'.config('cache.default'));

        if (($store['driver'] ?? null) !== 'database') {
            $this->info('El store de caché no es "database": nada que limpiar.');

            return self::SUCCESS;
        }

        $table = DB::connection($store['connection'] ?? null)->table($store['table'] ?? 'cache');
        $now = now()->getTimestamp();
        $deleted = 0;

        // En tandas para no clavar un DELETE gigante en el hosting compartido
        do {
            $keys = (clone $table)->where('expiration', '<=', $now)->limit(1000)->pluck('key');

            if ($keys->isNotEmpty()) {
                $deleted += (clone $table)->whereIn('key', $keys)->delete();
            }
        } while ($keys->count() === 1000);

        $this->info("Entradas vencidas borradas: {$deleted}.");

        return self::SUCCESS;
    }
}
