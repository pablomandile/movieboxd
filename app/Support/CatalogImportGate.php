<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Freno a la importación on demand desde TMDB.
 *
 * Cada ficha enlaza a otras (elenco → persona → filmografía → título…) y cada
 * enlace a algo que no está en la base lo importa. Un crawler que sigue esos
 * enlaces termina copiando TMDB entero: en noviembre de 2026 eso llevó la base a
 * 3 GB y Hostinger le quitó los permisos de escritura.
 *
 * - Los bots no importan nada (además robots.txt les cierra /resolve/).
 * - Los visitantes sin cuenta comparten un cupo diario global.
 * - Los usuarios logueados solo tienen el throttle por IP de la ruta.
 */
class CatalogImportGate
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|scrap|fetch|preview|python|curl|wget|httpclient|headless|facebookexternalhit|embedly|bingpreview/i';

    public static function isCrawler(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    /**
     * Corta el pedido si no le corresponde disparar una importación.
     */
    public static function authorize(Request $request): void
    {
        abort_if(self::isCrawler($request), 404);

        if ($request->user() !== null) {
            return;
        }

        $key = 'tmdb-imports:guests:'.now()->toDateString();
        $limit = (int) config('services.tmdb.guest_imports_per_day', 500);

        abort_if(RateLimiter::tooManyAttempts($key, $limit), 429);

        RateLimiter::hit($key, 86400);
    }
}
