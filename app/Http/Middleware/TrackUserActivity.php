<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota cuándo usó la app cada usuario logueado: users.last_seen_at y una fila
 * por día en user_activity_days. Escribe como mucho una vez cada 10 minutos por
 * sesión, así que no agrega una escritura a cada pedido.
 */
class TrackUserActivity
{
    private const EVERY_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $request->hasSession()) {
            // Por usuario: si en la misma sesión entra otra cuenta, se anota igual
            $key = "activity_tracked_at.{$user->id}";
            $last = (int) $request->session()->get($key, 0);

            if (now()->getTimestamp() - $last >= self::EVERY_SECONDS) {
                DB::table('user_activity_days')->insertOrIgnore(['user_id' => $user->id, 'date' => now()->toDateString()]);
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
                $request->session()->put($key, now()->getTimestamp());
            }
        }

        return $next($request);
    }
}
