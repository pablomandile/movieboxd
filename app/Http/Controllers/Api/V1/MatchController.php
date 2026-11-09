<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TitleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TitleResource;
use App\Models\Title;
use App\Services\Tmdb\MovieCandidates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/match?title=&alt=&year=
 *
 * Busca una película por nombre sin importar nada. Responde `matched` con una
 * sola película cuando la coincidencia es exacta, `ambiguous` con candidatos
 * para elegir, o `not_found`. Lo que ya está en el catálogo viene con su URL y
 * con el estado del usuario (en la watchlist / vista).
 */
class MatchController extends Controller
{
    public function __invoke(Request $request, MovieCandidates $candidates): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'alt' => ['nullable', 'string', 'max:200'],
            'year' => ['nullable', 'integer', 'min:1874', 'max:2100'],
        ]);

        $found = $candidates->find($data['title'], $data['alt'] ?? null, isset($data['year']) ? (int) $data['year'] : null);

        $catalog = Title::where('type', TitleType::Movie)
            ->whereIn('tmdb_id', array_column($found['results'], 'tmdb_id'))
            ->get()->keyBy('tmdb_id');

        $results = array_map(function (array $result) use ($catalog, $request) {
            $title = $catalog->get($result['tmdb_id']);

            return $result + ($title
                ? array_intersect_key((new TitleResource($title))->toArray($request), array_flip(['url', 'in_watchlist', 'watched']))
                : ['url' => null, 'in_watchlist' => false, 'watched' => false]);
        }, $found['results']);

        return response()->json($found['status'] === 'matched'
            ? ['status' => 'matched', 'title' => $results[0]]
            : ['status' => $found['status'], 'candidates' => $results]);
    }
}
