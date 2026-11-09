<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TitleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TitleResource;
use App\Models\Title;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use App\Services\Tmdb\TmdbImportService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agregar y sacar películas de la watchlist por tmdb_id, para apps externas.
 * Mismas reglas que la web (WatchlistController::toggle), pero sin toggle: cada
 * verbo hace una sola cosa y repetirlo no cambia nada.
 */
class WatchlistController extends Controller
{
    /** POST /api/v1/watchlist {tmdb_id} */
    public function store(Request $request, TmdbImportService $importer): JsonResponse
    {
        $tmdbId = (int) $request->validate(['tmdb_id' => ['required', 'integer', 'min:1']])['tmdb_id'];

        try {
            $title = Title::where('type', TitleType::Movie)->where('tmdb_id', $tmdbId)->first()
                ?? $importer->importTitle(TitleType::Movie, $tmdbId);
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return response()->json(['status' => 'not_found', 'error' => 'No existe esa película en TMDB.'], 404);
            }
            throw $e;
        }

        $keys = ['user_id' => $request->user()->id, 'title_id' => $title->id];

        // La watchlist es "para ver más tarde": una película ya vista no entra.
        if (WatchedTitle::where($keys)->exists()) {
            return $this->respond('already_watched', $title, $request, 409);
        }

        if (WatchlistItem::where($keys)->exists()) {
            return $this->respond('already_in_watchlist', $title, $request, 200);
        }

        WatchlistItem::create($keys);

        return $this->respond('added', $title, $request, 201);
    }

    /** DELETE /api/v1/watchlist/{tmdbId} */
    public function destroy(Request $request, int $tmdbId): JsonResponse
    {
        $title = Title::where('type', TitleType::Movie)->where('tmdb_id', $tmdbId)->first();

        // Borrado vía modelo, para que el observer actualice el contador del título.
        $item = $title
            ? WatchlistItem::where(['user_id' => $request->user()->id, 'title_id' => $title->id])->first()
            : null;

        if ($item === null) {
            return response()->json(['status' => 'not_in_watchlist'], 404);
        }

        $item->delete();

        return $this->respond('removed', $title, $request, 200);
    }

    /**
     * GET /api/v1/watchlist/status?tmdb_ids=1,2,3
     *
     * Estado de muchas películas en un solo pedido, para que la app externa
     * pinte sus botones al cargar. Solo vienen las que están en el catálogo;
     * las que faltan no están ni en la watchlist ni vistas.
     */
    public function status(Request $request): JsonResponse
    {
        $request->merge(['tmdb_ids' => array_values(array_filter(explode(',', (string) $request->query('tmdb_ids', '')), 'strlen'))]);
        $ids = $request->validate([
            'tmdb_ids' => ['required', 'array', 'max:500'],
            'tmdb_ids.*' => ['integer', 'min:1'],
        ])['tmdb_ids'];

        $titles = Title::where('type', TitleType::Movie)->whereIn('tmdb_id', $ids)->get();
        $userId = $request->user()->id;
        $enWatchlist = WatchlistItem::where('user_id', $userId)->whereIn('title_id', $titles->modelKeys())->pluck('title_id')->flip();
        $vistas = WatchedTitle::where('user_id', $userId)->whereIn('title_id', $titles->modelKeys())->pluck('title_id')->flip();

        return response()->json(['titles' => $titles->mapWithKeys(fn (Title $title) => [$title->tmdb_id => [
            'tmdb_id' => $title->tmdb_id,
            'title' => $title->localized_title,
            'year' => $title->year,
            'url' => route('films.show', $title),
            'in_watchlist' => $enWatchlist->has($title->id),
            'watched' => $vistas->has($title->id),
        ]])]);
    }

    private function respond(string $status, Title $title, Request $request, int $code): JsonResponse
    {
        return response()->json(['status' => $status, 'title' => (new TitleResource($title))->toArray($request)], $code);
    }
}
