<?php

namespace App\Console\Commands;

use App\Support\CatalogUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Borra los snapshots de TMDB que nadie usa: títulos sin datos de usuarios (con
 * sus temporadas y episodios, en cascada) y personas, que no se visitaron en los
 * últimos N días. Si alguien los vuelve a pedir, se importan de nuevo.
 *
 * Después compacta las tablas: InnoDB no achica el archivo con un DELETE, y lo
 * que Hostinger mide para el límite de la base es lo que ocupa en disco.
 */
class PruneCatalog extends Command
{
    protected $signature = 'movieboxd:prune-catalog
        {--days=30 : Días sin visitas para considerar que algo no se usa}
        {--dry-run : Solo cuenta, no borra}';

    protected $description = 'Borra del catálogo los títulos y personas de TMDB que nadie usa';

    /** Chico a propósito: cada título arrastra sus episodios y el usuario de la base tiene MAX_STATEMENT_TIME 120. */
    private const BATCH = 200;

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $protected = CatalogUsage::protectedTitleIds()->flip();

        $titles = $this->prune('titles', $cutoff, $dryRun, fn (int $id) => ! $protected->has($id));
        $people = $this->prune('people', $cutoff, $dryRun);

        $verb = $dryRun ? 'Se borrarían' : 'Borrados';
        $this->info("{$verb}: {$titles} títulos y {$people} personas.");

        if (! $dryRun && $titles + $people > 0) {
            $this->optimize($titles > 0 ? ['titles', 'seasons', 'episodes', 'people'] : ['people']);
        }

        return self::SUCCESS;
    }

    private function prune(string $table, $cutoff, bool $dryRun, ?callable $filter = null): int
    {
        $total = 0;

        DB::table($table)
            ->select('id')
            ->where(fn ($q) => $q
                ->where('viewed_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('viewed_at')->where('created_at', '<', $cutoff)))
            ->chunkById(self::BATCH, function ($rows) use ($table, $dryRun, $filter, &$total) {
                $ids = $rows->pluck('id')->map(fn ($id) => (int) $id);

                if ($filter) {
                    $ids = $ids->filter($filter);
                }

                if ($ids->isNotEmpty() && ! $dryRun) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }

                $total += $ids->count();
            });

        return $total;
    }

    /**
     * Reconstruye las tablas para devolver el espacio al disco. Si falla (otro
     * motor, falta de permisos, timeout) no es grave: el espacio libre se reusa
     * igual en los próximos INSERT.
     */
    private function optimize(array $tables): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($tables as $table) {
            try {
                // Los errores (p. ej. sin permiso de INSERT) vuelven como filas, no como excepción
                $error = collect(DB::select("OPTIMIZE TABLE `{$table}`"))->firstWhere('Msg_type', 'error');

                if ($error) {
                    $this->warn("No se pudo compactar {$table}: {$error->Msg_text}");
                }
            } catch (Throwable $e) {
                $this->warn("No se pudo compactar {$table}: {$e->getMessage()}");
            }
        }
    }
}
