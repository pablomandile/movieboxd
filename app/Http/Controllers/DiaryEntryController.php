<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogEntryRequest;
use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\Like;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiaryEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = $request->user()
            ->diaryEntries()
            ->inDiary()
            ->with(['review', 'loggable' => function ($morphTo) {
                $morphTo->morphWith([
                    Season::class => ['title'],
                    Episode::class => ['title'],
                ]);
            }])
            ->orderByDesc('watched_on')
            ->orderByDesc('id')
            ->paginate(50);

        return Inertia::render('Diary/Index', [
            'entries' => $entries->through(fn (DiaryEntry $entry) => self::entryProps($entry)),
        ]);
    }

    public function store(LogEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $class = Relation::getMorphedModel($data['loggable_type']);
        $loggable = $class::findOrFail($data['loggable_id']);

        // Rewatch autodetectado: ya existe un log previo del mismo sujeto (regla 3)
        $isRewatch = $request->boolean('is_rewatch') || DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('loggable_type', $data['loggable_type'])
            ->where('loggable_id', $loggable->id)
            ->exists();

        DB::transaction(function () use ($data, $user, $loggable, $isRewatch, $request) {
            $entry = DiaryEntry::create([
                'user_id' => $user->id,
                'loggable_type' => $data['loggable_type'],
                'loggable_id' => $loggable->id,
                'watched_on' => $data['watched_on'],
                'rating' => $data['rating'] ?? null,
                'is_rewatch' => $isRewatch,
                'tags' => array_values(array_filter($data['tags'] ?? [])),
            ]);

            // Review atada al log → un solo ítem en el feed (regla 4)
            if (! empty($data['review'])) {
                Review::create([
                    'user_id' => $user->id,
                    'reviewable_type' => $data['loggable_type'],
                    'reviewable_id' => $loggable->id,
                    'diary_entry_id' => $entry->id,
                    'body' => $data['review'],
                    'contains_spoilers' => $request->boolean('contains_spoilers'),
                ]);
            }

            // El rating del log actualiza el rating vigente (el diario guarda el snapshot)
            if (! empty($data['rating'])) {
                Rating::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'rateable_type' => $data['loggable_type'],
                        'rateable_id' => $loggable->id,
                    ],
                    ['value' => $data['rating']]
                );
            }

            if ($request->boolean('liked') && $loggable instanceof Title) {
                Like::firstOrCreate([
                    'user_id' => $user->id,
                    'likeable_type' => 'title',
                    'likeable_id' => $loggable->id,
                ]);
            }
        });

        return back();
    }

    public function update(Request $request, DiaryEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $entry);

        $data = $request->validate([
            'watched_on' => ['required', 'date', 'before_or_equal:today'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_rewatch' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ]);

        $entry->update([
            'watched_on' => $data['watched_on'],
            'rating' => $data['rating'] ?? null,
            'is_rewatch' => $request->boolean('is_rewatch', $entry->is_rewatch),
            'tags' => array_values(array_filter($data['tags'] ?? [])),
        ]);

        return back();
    }

    public function destroy(DiaryEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return back();
    }

    public static function entryProps(DiaryEntry $entry): array
    {
        $loggable = $entry->loggable;

        [$name, $url, $posterPath, $context] = match (true) {
            $loggable instanceof Title => [
                $loggable->localized_title,
                route($loggable->isMovie() ? 'films.show' : 'shows.show', $loggable),
                $loggable->poster_path,
                null,
            ],
            $loggable instanceof Season => [
                $loggable->localized_title,
                route('seasons.show', ['title' => $loggable->title->slug, 'seasonNumber' => $loggable->season_number]),
                $loggable->poster_path ?? $loggable->title->poster_path,
                $loggable->title->localized_title,
            ],
            $loggable instanceof Episode => [
                "{$loggable->code} {$loggable->localized_title}",
                route('episodes.show', [
                    'title' => $loggable->title->slug,
                    'seasonNumber' => $loggable->season_number,
                    'episodeNumber' => $loggable->episode_number,
                ]),
                $loggable->still_path ?? $loggable->title->poster_path,
                $loggable->title->localized_title,
            ],
            default => [null, null, null, null],
        };

        return [
            'id' => $entry->id,
            'watchedOn' => $entry->watched_on->toDateString(),
            'rating' => $entry->rating,
            'isRewatch' => $entry->is_rewatch,
            'tags' => $entry->tags ?? [],
            'type' => $entry->loggable_type,
            'name' => $name,
            'context' => $context,
            'url' => $url,
            'posterPath' => $posterPath,
            // El Diario lista reseñas y revisionados: hay que poder distinguirlos
            'reviewUrl' => $entry->review !== null ? route('reviews.show', $entry->review) : null,
        ];
    }
}
