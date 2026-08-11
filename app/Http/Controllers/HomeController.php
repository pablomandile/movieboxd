<?php

namespace App\Http\Controllers;

use App\Services\ActivityFeedService;
use App\Services\Tmdb\Dto\TitleCard;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HomeController extends Controller
{
    public function index(Request $request, TmdbClient $tmdb, ActivityFeedService $feedService): Response
    {
        $locale = app()->getLocale();

        // 4 filas completas en la grilla de 6 columnas de escritorio
        $cards = fn (array $items) => collect($items)
            ->map(fn (array $item) => TitleCard::fromTmdb($item))
            ->filter()
            ->unique(fn (array $card) => $card['type'].$card['tmdbId'])
            ->take(24)
            ->values();

        try {
            $trending = $cards($tmdb->trending('week', $locale));
            $trendingMovies = $cards($tmdb->trendingType('movie', 'week', $locale));
            $trendingShows = $cards($tmdb->trendingType('tv', 'week', $locale));
        } catch (Throwable) {
            // Sin API key o TMDB caído: la home no puede romperse
            $trending = $trendingMovies = $trendingShows = collect();
        }

        return Inertia::render('Home', [
            'trending' => $trending,
            'trendingMovies' => $trendingMovies,
            'trendingShows' => $trendingShows,
            'feed' => $request->user() ? $feedService->for($request->user()) : [],
        ]);
    }
}
