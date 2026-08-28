<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** Sin la versión del asset, Inertia contesta 409 en vez de la página. */
    private function versionDeInertia(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_it_forbids_storing_the_inertia_xhr_response()
    {
        $response = $this->get('/login', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->versionDeInertia(),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        // no-store, no no-cache: no-cache permite guardar y solo obliga a
        // revalidar, y restaurar una pestaña descartada saltea la revalidación.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_it_keeps_the_html_document_cacheable_so_the_bfcache_survives()
    {
        $response = $this->get('/login');

        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        // no-store en el documento principal desactiva el back/forward cache de
        // Chrome, y cada "atrás" pasa a ser una ida completa a la red.
        $this->assertStringNotContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_it_declares_the_header_that_separates_the_two_responses()
    {
        $this->get('/login')->assertHeader('Vary', 'X-Inertia, Accept-Encoding');
    }
}
