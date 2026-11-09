<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En la demo (APP_DEMO=true) hay una cuenta compartida: nadie se registra, entra
 * con Google ni recupera la clave, la cuenta no se puede cambiar ni borrar, y no se
 * importan exportaciones de Letterboxd (dispararían miles de pedidos a TMDB) ni
 * se crean tokens de la API. El resto sí —loguear, reseñar, armar listas—: es lo
 * que se quiere mostrar, y se resiembra de noche.
 */
class ProtegerDemo
{
    // Pantallas que no existen en la demo: van al login.
    private const PANTALLAS = [
        'register', 'oauth.google.redirect', 'oauth.google.callback', 'password.request', 'password.reset',
    ];

    // Cambios de la cuenta: vuelven atrás con un aviso.
    private const ACCIONES = [
        'password.email', 'password.store', 'password.update', 'profile.update', 'profile.destroy', 'import.store', 'api-tokens.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $ruta = $request->route()?->getName();

        // El POST de /register no tiene nombre de ruta.
        if (in_array($ruta, self::PANTALLAS, true) || $request->is('register')) {
            return redirect()->route('login');
        }

        if (in_array($ruta, self::ACCIONES, true)) {
            return back()->with('error', 'En la demo no se puede cambiar la cuenta.');
        }

        return $next($request);
    }
}
