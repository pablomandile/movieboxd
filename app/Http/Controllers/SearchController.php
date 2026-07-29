<?php

namespace App\Http\Controllers;

use App\Services\Tmdb\Dto\TitleCard;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SearchController extends Controller
{
    public function __invoke(Request $request, TmdbClient $tmdb): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:500',
        ]);

        $query = trim($validated['q'] ?? '');
        $page = (int) ($validated['page'] ?? 1);

        $results = collect();
        $totalPages = 0;

        if ($query !== '') {
            try {
                $payload = $tmdb->searchMulti($query, app()->getLocale(), $page);
                $totalPages = min($payload['total_pages'] ?? 0, 500);

                $results = collect($payload['results'] ?? [])
                    ->map(fn (array $item) => TitleCard::fromTmdb($item))
                    ->filter()
                    ->values();
            } catch (Throwable) {
                $results = collect();
            }
        }

        return Inertia::render('Search/Index', [
            'query' => $query,
            'results' => $results,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
