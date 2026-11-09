<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tokens personales para la API (/api/v1): los usa una app externa, como Hoy
 * Trasnoche, para mandar películas a la watchlist. El token en claro se muestra
 * una sola vez, al crearlo; en la base queda solo su hash.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/Api', [
            'tokens' => $request->user()->tokens()->latest()->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'createdAt' => $token->created_at->toIso8601String(),
            ]),
            // Recién creado: llega una sola vez, por la sesión flash.
            'newToken' => $request->session()->get('newToken'),
            'apiUrl' => url('/api/v1'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $token = $request->user()->createToken($data['name'], ['watchlist']);

        return back()->with('newToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back();
    }
}
