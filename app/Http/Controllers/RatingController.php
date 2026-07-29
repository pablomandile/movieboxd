<?php

namespace App\Http\Controllers;

use App\Models\Rating;
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
            Rating::where($keys)->first()?->delete();
        } else {
            Rating::updateOrCreate($keys, ['value' => $data['value']]);
        }

        return back();
    }
}
