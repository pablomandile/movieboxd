<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Rating;
use App\Models\Title;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RatingController extends Controller
{
    /**
     * Upsert del rating vigente. value=null elimina la calificación.
     */
    public function upsert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rateable_type' => ['required', Rule::in(['title', 'season', 'episode'])],
            'rateable_id' => ['required', 'integer'],
            'value' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $class = Relation::getMorphedModel($data['rateable_type']);
        $rateable = $class::findOrFail($data['rateable_id']);

        $keys = [
            'user_id' => $request->user()->id,
            'rateable_type' => $data['rateable_type'],
            'rateable_id' => $rateable->id,
        ];

        if (($data['value'] ?? null) === null) {
            // Quitar la calificación no des-marca la vista ni borra el diario
            Rating::where($keys)->first()?->delete();

            return back();
        }

        $existing = Rating::where($keys)->exists();

        Rating::updateOrCreate($keys, ['value' => $data['value']]);

        // Regla de producto: calificar una película o serie implica haberla
        // visto. La PRIMERA calificación genera el registro en el diario (el
        // observer marca la vista y la saca de la watchlist); ajustar las
        // estrellas después no duplica nada.
        if (! $existing && $rateable instanceof Title) {
            $alreadyLogged = DiaryEntry::where('user_id', $request->user()->id)
                ->where('loggable_type', 'title')
                ->where('loggable_id', $rateable->id)
                ->exists();

            if (! $alreadyLogged) {
                DiaryEntry::create([
                    'user_id' => $request->user()->id,
                    'loggable_type' => 'title',
                    'loggable_id' => $rateable->id,
                    'watched_on' => now()->toDateString(),
                    'rating' => $data['value'],
                ]);
            }
        }

        return back();
    }
}
