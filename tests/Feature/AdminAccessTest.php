<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_regular_users_get_403()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admins_can_access_the_admin_panel()
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_banned_users_are_logged_out()
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($user)->get('/diary')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
