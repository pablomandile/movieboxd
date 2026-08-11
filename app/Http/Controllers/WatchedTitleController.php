<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Title;
use App\Models\WatchedTitle;
use App\Services\Tmdb\Dto\TitleCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WatchedTitleController extends Controller
{
    /**
     * "Lo que vi": todo lo que el usuario marcó como visto (incluido lo que
     * quedó visto por haberlo calificado, ver RatingController::upsert).
     */
    public function index(Request $request): Response
    {
        $type = in_array($request->query('type'), ['movie', 'tv'], true)
            ? $request->query('type')
            : null;

        $titles = $request->user()
            ->watchedTitles()
            ->when($type, fn ($query) => $query->where('titles.type', $type))
            ->orderByDesc('watched_titles.created_at')
            ->paginate(48)
            ->withQueryString();

        // Las calificaciones propias de la página, en una sola query
        $ratings = Rating::where('user_id', $request->user()->id)
            ->where('rateable_type', 'title')
            ->whereIn('rateable_id', $titles->getCollection()->pluck('id'))
            ->pluck('value', 'rateable_id');

        return Inertia::render('Watched/Index', [
            'titles' => $titles->through(fn (Title $title) => TitleCard::fromModel($title) + [
                'rating' => $ratings[$title->id] ?? null,
            ]),
            'type' => $type,
            'counts' => [
                'all' => $request->user()->watchedTitles()->count(),
                'movie' => $request->user()->watchedTitles()->where('titles.type', 'movie')->count(),
                'tv' => $request->user()->watchedTitles()->where('titles.type', 'tv')->count(),
            ],
        ]);
    }

    public function toggle(Request $request, Title $title): RedirectResponse
    {
        $user = $request->user();

        $existing = WatchedTitle::where('user_id', $user->id)
            ->where('title_id', $title->id)
            ->first();

        if ($existing === null) {
            WatchedTitle::create(['user_id' => $user->id, 'title_id' => $title->id]);

            return back();
        }

        // Como en Letterboxd: no se puede des-marcar mientras existan logs del título
        if ($title->diaryEntries()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['watched' => __('app.unwatch_blocked')]);
        }

        $existing->delete();

        return back();
    }
}
