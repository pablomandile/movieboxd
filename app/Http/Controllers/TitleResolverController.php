<?php

namespace App\Http\Controllers;

use App\Enums\TitleType;
use App\Models\Title;
use App\Services\Tmdb\TmdbImportService;
use App\Support\CatalogImportGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TitleResolverController extends Controller
{
    /**
     * Import on demand: la búsqueda no persiste nada; este endpoint
     * importa el título la primera vez y redirige a su URL canónica.
     */
    public function __invoke(Request $request, string $type, int $tmdbId, TmdbImportService $importer): RedirectResponse
    {
        $titleType = TitleType::tryFrom($type) ?? abort(404);

        $title = Title::where('type', $titleType)->where('tmdb_id', $tmdbId)->first();

        if ($title === null) {
            CatalogImportGate::authorize($request);
            $title = $importer->importTitle($titleType, $tmdbId);
        }

        return redirect()->route(
            $title->isMovie() ? 'films.show' : 'shows.show',
            $title,
            301
        );
    }
}
