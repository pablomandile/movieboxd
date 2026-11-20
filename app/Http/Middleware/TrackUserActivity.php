<?php

namespace App\Http\Middleware;

use App\Support\CatalogImportGate;
use App\Support\Metrics;
use App\Support\Usage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota el uso de la app para las estadísticas del admin:
 *
 * - Visitas: la primera página que ve cada sesión en el día (sin bots ni admins).
 * - Páginas vistas: cada página que se muestra entera o por navegación de Inertia
 *   (no las recargas parciales, ni los prefetch, ni las redirecciones).
 * - Usuarios logueados: users.last_seen_at y una fila por día en user_activity_days,
 *   como mucho una vez cada 10 minutos por sesión.
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

        $response = $next($request);

        if ($this->isPageView($request, $response) && Usage::counts($request->user())) {
            Metrics::increment(Metrics::PAGEVIEWS);

            $today = now()->toDateString();
            if ($request->hasSession() && $request->session()->get('visit_day') !== $today) {
                Metrics::increment(Metrics::VISITS);
                $request->session()->put('visit_day', $today);
            }
        }

        return $response;
    }

    private function isPageView(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || CatalogImportGate::isCrawler($request)) {
            return false;
        }

        if ($request->header('X-Inertia-Partial-Data') !== null || $request->header('Purpose') === 'prefetch') {
            return false;
        }

        return $request->header('X-Inertia') !== null
            || str_contains((string) $request->header('Accept'), 'text/html');
    }
}
