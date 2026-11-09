<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_blocks_registration_google_and_password_recovery(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'X', 'username' => 'xx', 'email' => 'x@x.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
        ])->assertRedirect('/login');
        $this->get('/auth/google/redirect')->assertRedirect('/login');
        $this->get('/forgot-password')->assertRedirect('/login');
        $this->post('/forgot-password', ['email' => 'demo@pablomandile.com.ar'])->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    public function test_demo_user_cannot_change_or_delete_the_account_nor_import(): void
    {
        config(['demo.enabled' => true]);
        $user = User::factory()->create(['password' => 'demo1234']);

        $this->actingAs($user)->from('/settings/password')
            ->put('/settings/password', ['current_password' => 'demo1234', 'password' => 'otraclave1', 'password_confirmation' => 'otraclave1'])
            ->assertRedirect('/settings/password')
            ->assertSessionHas('error');
        $this->actingAs($user)->from('/settings/profile')
            ->patch('/settings/profile', ['name' => 'Otro', 'email' => 'otro@x.com'])
            ->assertSessionHas('error');
        $this->actingAs($user)->from('/settings/profile')
            ->delete('/settings/profile', ['password' => 'demo1234'])
            ->assertSessionHas('error');
        $this->actingAs($user)->from('/settings/import')
            ->post('/settings/import', [])
            ->assertSessionHas('error');

        $user->refresh();
        $this->assertTrue(Hash::check('demo1234', $user->password));
        $this->assertNotSame('otro@x.com', $user->email);
        $this->assertModelExists($user);
    }

    public function test_login_shares_the_demo_credentials_and_hides_google_only_in_demo(): void
    {
        config(['demo.enabled' => true, 'services.google.client_id' => 'id', 'services.google.client_secret' => 'secreto']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('demo.email', 'demo@pablomandile.com.ar')
            ->where('demo.password', 'demo1234')
            ->where('oauth.google', false));

        config(['demo.enabled' => false]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('demo', null)
            ->where('oauth.google', true));
        $this->get('/register')->assertOk();
    }

    public function test_reset_refuses_to_run_outside_the_demo(): void
    {
        config(['demo.enabled' => false]);
        $user = User::factory()->create();

        $this->artisan('demo:reset')->assertFailed();
        $this->assertModelExists($user);
    }
}
