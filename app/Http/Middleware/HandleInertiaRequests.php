<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\GoogleController;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Una misma URL contesta HTML o JSON según el header X-Inertia, y lo único
     * que se lo dice a una caché es el Vary. El CDN de Hostinger lo reescribe, y
     * cuando comprime con brotli —lo que pide cualquier navegador real— lo borra
     * entero: las dos respuestas quedan bajo la misma clave. Si además se pueden
     * guardar, al restaurar una pestaña descartada el navegador reusa la entrada
     * JSON sin revalidar y el usuario ve el JSON crudo en pantalla.
     *
     * Va acá y no en un middleware aparte: el de Inertia setea el Vary y puede
     * reemplazar la respuesta entera en onVersionChange(), y cualquier middleware
     * posterior del grupo web corre su salida antes y quedaría pisado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // El CDN lo borra, pero se declara igual: es lo correcto y lo respeta
        // cualquier otro intermediario.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        // no-store, no no-cache: no-cache permite guardar y solo obliga a
        // revalidar, y la navegación de historial saltea la revalidación.
        //
        // Y solo sobre la respuesta XHR, nunca sobre el HTML: no-store en el
        // documento principal desactiva el back/forward cache de Chrome y
        // convierte cada "atrás" en una ida completa a la red.
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'features' => fn () => [
                'comments' => Setting::enabled('features.comments'),
                'lists' => Setting::enabled('features.lists'),
                'reviews' => Setting::enabled('features.reviews'),
                'registration' => Setting::enabled('features.registration'),
            ],
            'auth' => [
                'user' => fn () => $request->user()?->only(
                    'id', 'name', 'username', 'email', 'avatar_path', 'role', 'locale', 'email_verified_at'
                ),
            ],
            // El botón de Google solo aparece si hay credenciales cargadas
            'oauth' => fn () => [
                'google' => GoogleController::configured(),
            ],
            'flash' => fn () => [
                'status' => $request->session()->get('status'),
                // Enlace de invitación: se entrega una sola vez, al pedirlo
                'inviteUrl' => $request->session()->get('inviteUrl'),
            ],
        ]);
    }
}
