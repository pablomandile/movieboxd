<?php

namespace App\Http\Controllers;

use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use App\Services\Tmdb\Dto\TitleCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistController extends Controller
{
    public function index(Request $request): Response
    {
        $titles = $request->user()
            ->watchlist()
            ->orderByDesc('watchlist_items.created_at')
            ->paginate(48);

        return Inertia::render('Watchlist/Index', [
            'titles' => $titles->through(fn (Title $title) => TitleCard::fromModel($title)),
        ]);
    }

    public function toggle(Request $request, Title $title): RedirectResponse
    {
        $keys = [
            'user_id' => $request->user()->id,
            'title_id' => $title->id,
        ];

        $existing = WatchlistItem::where($keys)->first();

        if ($existing !== null) {
            $existing->delete();

            return back();
        }

        // La watchlist es "para ver más tarde": un título ya visto no entra
        if (WatchedTitle::where($keys)->exists()) {
            return back()->withErrors(['watchlist' => __('app.watchlist_watched')]);
        }

        WatchlistItem::create($keys);

        return back();
    }
}
