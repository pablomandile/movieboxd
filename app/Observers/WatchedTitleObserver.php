<?php

namespace App\Observers;

use App\Models\WatchedTitle;
use App\Models\WatchlistItem;

class WatchedTitleObserver
{
    public function created(WatchedTitle $watched): void
    {
        // Regla 5: al marcar como vista, sale de la watchlist automáticamente.
        // Borrado vía modelo para que dispare el observer del contador.
        WatchlistItem::where('user_id', $watched->user_id)
            ->where('title_id', $watched->title_id)
            ->first()?->delete();

        $this->recalculate($watched);
    }

    public function deleted(WatchedTitle $watched): void
    {
        $this->recalculate($watched);
    }

    protected function recalculate(WatchedTitle $watched): void
    {
        $watched->title?->forceFill([
            'watched_count' => WatchedTitle::where('title_id', $watched->title_id)->count(),
        ])->saveQuietly();
    }
}
