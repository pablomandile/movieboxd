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
        try {
            $trending = collect($tmdb->trending('week', app()->getLocale()))
                ->map(fn (array $item) => TitleCard::fromTmdb($item))
                ->filter()
                ->take(18)
                ->values();
        } catch (Throwable) {
            // Sin API key o TMDB caído: la home no puede romperse
            $trending = collect();
        }

        return Inertia::render('Home', [
            'trending' => $trending,
            'feed' => $request->user() ? $feedService->for($request->user()) : [],
        ]);
    }
}
