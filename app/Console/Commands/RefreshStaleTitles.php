<?php

namespace App\Console\Commands;

use App\Enums\TitleType;
use App\Jobs\RefreshTitle;
use App\Models\Title;
use App\Support\CatalogUsage;
use Illuminate\Console\Command;

class RefreshStaleTitles extends Command
{
    protected $signature = 'movieboxd:refresh-stale';

    protected $description = 'Encola el refresco de títulos en uso con snapshot viejo de TMDB';

    /**
     * Solo los títulos con datos de usuarios (más los de la demo): esos aparecen en
     * perfiles, listas y el feed sin que nadie entre a su ficha. El resto se refresca
     * al visitarlo (TitleController::refreshIfStale), así que recorrer todo el
     * catálogo era pedirle a TMDB miles de títulos por día que nadie miraba.
     */
    public function handle(): int
    {
        $airing = ['Returning Series', 'In Production', 'Planned'];

        $query = Title::query()
            ->whereIn('id', CatalogUsage::protectedTitleIds())
            ->where(function ($q) use ($airing) {
                $q->whereNull('synced_at')
                    // Películas y series terminadas: 30 días
                    ->orWhere(function ($q) use ($airing) {
                        $q->where('synced_at', '<', now()->subDays(30))
                            ->where(function ($q) use ($airing) {
                                $q->where('type', TitleType::Movie)
                                    ->orWhereNotIn('tv_status', $airing);
                            });
                    })
                    // Series en emisión: 24 horas
                    ->orWhere(function ($q) use ($airing) {
                        $q->where('type', TitleType::Tv)
                            ->whereIn('tv_status', $airing)
                            ->where('synced_at', '<', now()->subDay());
                    })
                    // Dónde verlo: los catálogos rotan todos los meses, así que
                    // se refresca aunque la metadata siga vigente. El whereNull
                    // no es redundante: en SQL `NULL < fecha` no es verdadero,
                    // así que sin él los títulos anteriores a esta función
                    // (que lo tienen en NULL) no se refrescarían nunca.
                    ->orWhere(function ($q) {
                        $q->whereNull('watch_providers_synced_at')
                            ->orWhere('watch_providers_synced_at', '<', now()->subDays(7));
                    });
            });

        $count = 0;

        $query->chunkById(200, function ($titles) use (&$count) {
            foreach ($titles as $title) {
                RefreshTitle::dispatch($title);
                $count++;
            }
        });

        $this->info("Encolados {$count} títulos para refresco.");

        return self::SUCCESS;
    }
}
