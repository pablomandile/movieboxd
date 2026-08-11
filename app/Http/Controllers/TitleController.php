<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshTitle;
use App\Models\EpisodeWatch;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Rating;
use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use App\Support\PageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TitleController extends Controller
{
    public function film(Request $request, Title $title): Response
    {
        abort_unless($title->isMovie(), 404);

        $this->refreshIfStale($title);

        return Inertia::render('titles/Film', [
            'title' => $this->titleProps($title),
            'viewer' => $this->viewerProps($request, $title),
            'reviews' => Inertia::defer(fn () => ReviewController::popularFor($title, $request->user()?->id)),
            'meta' => PageMeta::make(
                $title->localized_title.($title->year ? " ({$title->year})" : ''),
                $title->localized_overview,
                $title->poster_path,
                'video.movie'
            ),
        ]);
    }

    public function show(Request $request, Title $title): Response
    {
        abort_unless($title->isTv(), 404);

        $this->refreshIfStale($title);

        $watchedPerSeason = $request->user()
            ? EpisodeWatch::where('user_id', $request->user()->id)
                ->where('title_id', $title->id)
                ->selectRaw('season_id, COUNT(*) as total')
                ->groupBy('season_id')
                ->pluck('total', 'season_id')
            : collect();

        return Inertia::render('titles/Show', [
            'title' => $this->titleProps($title),
            'viewer' => $this->viewerProps($request, $title),
            'reviews' => Inertia::defer(fn () => ReviewController::popularFor($title, $request->user()?->id)),
            'meta' => PageMeta::make(
                $title->localized_title.($title->year ? " ({$title->year})" : ''),
                $title->localized_overview,
                $title->poster_path,
                'video.tv_show'
            ),
            'seasons' => $title->seasons->map(fn ($season) => [
                'id' => $season->id,
                'number' => $season->season_number,
                'name' => $season->localized_title,
                'posterPath' => $season->poster_path,
                'airDate' => $season->air_date?->toDateString(),
                'episodesCount' => $season->episodes_count,
                'watchedCount' => (int) ($watchedPerSeason[$season->id] ?? 0),
            ]),
        ]);
    }

    protected function refreshIfStale(Title $title): void
    {
        if ($title->isStale()) {
            // Se muestra el snapshot y el refresco corre en cola sin bloquear
            RefreshTitle::dispatch($title);
        }
    }

    protected function viewerProps(Request $request, Title $title): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'lists' => ListModel::where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ListModel $list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'hasTitle' => ListItem::where('list_id', $list->id)->where('title_id', $title->id)->exists(),
                ]),
            'isFavorite' => Favorite::where('user_id', $user->id)->where('title_id', $title->id)->exists(),
            'watched' => WatchedTitle::where('user_id', $user->id)->where('title_id', $title->id)->exists(),
            'liked' => Like::where('user_id', $user->id)
                ->where('likeable_type', 'title')
                ->where('likeable_id', $title->id)
                ->exists(),
            'inWatchlist' => WatchlistItem::where('user_id', $user->id)->where('title_id', $title->id)->exists(),
            'rating' => Rating::where('user_id', $user->id)
                ->where('rateable_type', 'title')
                ->where('rateable_id', $title->id)
                ->value('value'),
            'hasLogged' => $title->diaryEntries()->where('user_id', $user->id)->exists(),
        ];
    }

    protected function titleProps(Title $title): array
    {
        return [
            'id' => $title->id,
            'type' => $title->type->value,
            'slug' => $title->slug,
            'tmdbId' => $title->tmdb_id,
            'title' => $title->localized_title,
            'originalTitle' => $title->original_title,
            'tagline' => $title->localized('tagline'),
            'overview' => $title->localized_overview,
            'posterPath' => $title->poster_path,
            'backdropPath' => $title->backdrop_path,
            'year' => $title->year,
            'releaseDate' => $title->release_date?->toDateString(),
            'runtime' => $title->runtime,
            'genres' => $title->genres ?? [],
            'credits' => $title->credits ?? [],
            'watchProviders' => $title->watch_providers,
            'originalLanguage' => $title->original_language,
            'tvStatus' => $title->tv_status,
            'seasonsCount' => $title->seasons_count,
            'episodesCount' => $title->episodes_count,
            'counts' => [
                'watched' => $title->watched_count,
                'likes' => $title->likes_count,
                'watchlist' => $title->watchlist_count,
                'reviews' => $title->reviews_count,
            ],
            'ratings' => [
                'count' => $title->ratings_count,
                'average' => $title->weighted_rating,
                'histogram' => $title->ratings_histogram,
            ],
        ];
    }
}
