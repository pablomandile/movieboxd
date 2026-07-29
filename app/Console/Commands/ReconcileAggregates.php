<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Like;
use App\Models\Season;
use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Illuminate\Console\Command;

class ReconcileAggregates extends Command
{
    protected $signature = 'movieboxd:reconcile-aggregates';

    protected $description = 'Recalcula todos los contadores y agregados cacheados desde las tablas fuente';

    public function handle(): int
    {
        Title::query()->chunkById(200, function ($titles) {
            foreach ($titles as $title) {
                $title->recalculateRatingAggregates();

                $title->forceFill([
                    'watched_count' => WatchedTitle::where('title_id', $title->id)->count(),
                    'watchlist_count' => WatchlistItem::where('title_id', $title->id)->count(),
                    'likes_count' => Like::where('likeable_type', 'title')->where('likeable_id', $title->id)->count(),
                ])->saveQuietly();
            }
        });

        Season::query()->chunkById(500, fn ($seasons) => $seasons->each->recalculateRatingAggregates());
        Episode::query()->chunkById(500, fn ($episodes) => $episodes->each->recalculateRatingAggregates());

        $this->info('Agregados reconciliados.');

        return self::SUCCESS;
    }
}
