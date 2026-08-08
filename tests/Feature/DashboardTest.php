<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/diary')->assertRedirect('/login');
    }

    /**
     * /dashboard era la pantalla de andamiaje del starter kit (placeholders vacíos).
     * Ahora manda a la home, que es la pantalla con contenido real.
     */
    public function test_the_old_dashboard_url_redirects_to_the_home()
    {
        $this->get('/dashboard')->assertRedirect('/');

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect('/');
    }

    public function test_authenticated_users_land_on_the_home_with_the_feed()
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home')->has('feed')->has('trending'));
    }
}
