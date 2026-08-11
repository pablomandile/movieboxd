<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshPerson;
use App\Models\Person;
use App\Services\Tmdb\TmdbImportService;
use App\Support\PageMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /**
     * Import on demand, igual que con los títulos: la primera visita a una
     * persona la trae de TMDB y redirige a su URL canónica.
     */
    public function resolve(int $tmdbId, TmdbImportService $importer): RedirectResponse
    {
        $person = Person::where('tmdb_id', $tmdbId)->first() ?? $importer->importPerson($tmdbId);

        return redirect()->route('people.show', $person, 301);
    }

    public function show(Person $person): Response
    {
        // Refresco en segundo plano: la página se sirve del snapshot y no espera
        if ($person->isStale()) {
            RefreshPerson::dispatch($person);
        }

        return Inertia::render('Person', [
            'person' => [
                'id' => $person->id,
                'tmdbId' => $person->tmdb_id,
                'name' => $person->name,
                'profilePath' => $person->profile_path,
                'biography' => $person->localized_biography,
                'birthday' => $person->birthday?->toDateString(),
                'deathday' => $person->deathday?->toDateString(),
                'placeOfBirth' => $person->place_of_birth,
                'knownFor' => $person->known_for_department,
                'credits' => $person->credits ?? [],
            ],
            'meta' => PageMeta::make(
                $person->name,
                $person->localized_biography,
                $person->profile_path,
                'profile'
            ),
        ]);
    }
}
