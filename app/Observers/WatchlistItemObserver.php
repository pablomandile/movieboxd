<?php

namespace App\Observers;

use App\Models\WatchlistItem;

class WatchlistItemObserver
{
    public function created(WatchlistItem $item): void
    {
        $this->recalculate($item);
    }

    public function deleted(WatchlistItem $item): void
    {
        $this->recalculate($item);
    }

    protected function recalculate(WatchlistItem $item): void
    {
        $item->title?->forceFill([
            'watchlist_count' => WatchlistItem::where('title_id', $item->title_id)->count(),
        ])->saveQuietly();
    }
}
