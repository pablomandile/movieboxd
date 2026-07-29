<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        $flags = [];

        foreach (array_keys(Setting::FEATURE_FLAGS) as $flag) {
            $flags[$flag] = Setting::enabled($flag);
        }

        return Inertia::render('admin/Settings', [
            'features' => $flags,
            // Nunca se devuelve la key: solo si hay una configurada
            'tmdb' => [
                'hasKey' => (bool) Setting::get('tmdb.api_key'),
                'usingEnvFallback' => ! Setting::get('tmdb.api_key') && (bool) config('services.tmdb.key'),
            ],
            'ratingPrior' => (int) (Setting::get('rating.prior') ?? config('movieboxd.rating_prior', 30)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tmdb_api_key' => ['nullable', 'string', 'max:255'],
            'clear_tmdb_key' => ['boolean'],
            'rating_prior' => ['required', 'integer', 'min:0', 'max:10000'],
            'features' => ['array'],
            'features.*' => ['boolean'],
        ]);

        if ($request->boolean('clear_tmdb_key')) {
            Setting::put('tmdb.api_key', null);
        } elseif (! empty($data['tmdb_api_key'])) {
            Setting::put('tmdb.api_key', $data['tmdb_api_key']);
        }

        Setting::put('rating.prior', (string) $data['rating_prior']);

        foreach ($data['features'] ?? [] as $flag => $enabled) {
            if (array_key_exists($flag, Setting::FEATURE_FLAGS)) {
                Setting::put($flag, $enabled ? '1' : '0');
            }
        }

        return back();
    }
}
