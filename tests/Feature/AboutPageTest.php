<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_about_page_is_public()
    {
        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('About')->has('meta'));
    }

    public function test_it_ships_open_graph_metadata_for_crawlers()
    {
        // El <Head> de Inertia no lo ven los crawlers: va server-side en el blade
        $this->get('/about')
            ->assertSee('og:description', escape: false)
            ->assertSee('Pablo Mandile', escape: false);
    }
}
