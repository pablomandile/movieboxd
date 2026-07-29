<?php

namespace App\Http\Controllers;

use App\Models\EpisodeWatch;
use App\Models\Rating;
use App\Models\Title;
use App\Services\Tmdb\TmdbImportService;
use App\Support\PageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpisodeController extends Controller
{
    public function show(Request $request, Title $title, int $seasonNumber, int $episodeNumber, TmdbImportService $importer): Response
    {
        abort_unless($title->isTv(), 404);

        $season = $title->seasons()->where('season_number', $seasonNumber)->first() ?? abort(404);

        if ($season->needsEpisodeSync()) {
            $season = $importer->importSeason($title, $seasonNumber);
        }

        $episode = $season->episodes()->where('episode_number', $episodeNumber)->first() ?? abort(404);

        $user = $request->user();

        $viewer = $user ? [
            'watched' => EpisodeWatch::where('user_id', $user->id)->where('episode_id', $episode->id)->exists(),
            'rating' => Rating::where('user_id', $user->id)
                ->where('rateable_type', 'episode')
                ->where('rateable_id', $episode->id)
                ->value('value'),
            'hasLogged' => $episode->diaryEntries()->where('user_id', $user->id)->exists(),
        ] : null;

        return Inertia::render('titles/Episode', [
            'viewer' => $viewer,
            'reviews' => Inertia::defer(fn () => ReviewController::popularFor($episode, $user?->id)),
            'meta' => PageMeta::make(
                "{$title->localized_title} {$episode->code}: {$episode->localized_title}",
                $episode->localized_overview,
                $episode->still_path ?? $title->poster_path,
                'video.episode'
            ),
            'show' => SeasonController::showProps($title),
            'season' => SeasonController::seasonProps($season),
            'episode' => [
                'id' => $episode->id,
                'number' => $episode->episode_number,
                'code' => $episode->code,
                'name' => $episode->localized_title,
                'overview' => $episode->localized_overview,
                'stillPath' => $episode->still_path,
                'airDate' => $episode->air_date?->toDateString(),
                'runtime' => $episode->runtime,
                'ratings' => [
                    'count' => $episode->ratings_count,
                    'average' => $episode->ratings_count > 0
                        ? round($episode->ratings_sum / $episode->ratings_count / 2, 1)
                        : null,
                ],
            ],
        ]);
    }
}
