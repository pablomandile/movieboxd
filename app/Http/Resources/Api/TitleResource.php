<?php

namespace App\Http\Resources\Api;

use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un título del catálogo tal como lo ve la API, con el estado del usuario del token.
 *
 * @mixin Title
 */
class TitleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $keys = ['user_id' => $request->user()->id, 'title_id' => $this->id];

        return [
            'tmdb_id' => $this->tmdb_id,
            'type' => $this->type->value,
            'title' => $this->localized_title,
            'original_title' => $this->original_title,
            'year' => $this->year,
            'poster_url' => $this->poster_path ? "https://image.tmdb.org/t/p/w185{$this->poster_path}" : null,
            'url' => route($this->isMovie() ? 'films.show' : 'shows.show', $this->resource),
            'in_watchlist' => WatchlistItem::where($keys)->exists(),
            'watched' => WatchedTitle::where($keys)->exists(),
        ];
    }
}
