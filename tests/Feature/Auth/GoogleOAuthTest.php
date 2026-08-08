<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    /** Simula la respuesta de Google */
    protected function fakeGoogleUser(array $overrides = []): void
    {
        $user = new SocialiteUser;
        $user->map(array_merge([
            'id' => '1234567890',
            'name' => 'Pablo Mandile',
            'nickname' => null,
            'email' => 'nuevo@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/foto.jpg',
        ], $overrides));

        // El payload crudo lleva el flag de email verificado
        $user->user = array_merge(['email_verified' => true], $overrides['raw'] ?? []);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_requires_credentials_to_be_configured()
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/auth/google/redirect')->assertNotFound();

        // Y el botón no se ofrece en el login
        $this->get('/login')->assertInertia(fn ($page) => $page->where('oauth.google', false));
    }

    public function test_the_login_page_offers_google_when_configured()
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('oauth.google', true));
    }

    public function test_a_new_user_is_created_from_the_google_account()
    {
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'nuevo@gmail.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('1234567890', $user->google_id);
        $this->assertSame('Pablo Mandile', $user->name);
        $this->assertSame('nuevo', $user->username);          // derivado del email
        $this->assertNull($user->password);                    // entra solo por Google
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(Role::User, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_collisions_get_a_numeric_suffix()
    {
        User::factory()->create(['username' => 'nuevo']);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@gmail.com', 'username' => 'nuevo2']);
    }

    public function test_an_existing_account_is_linked_by_email_without_touching_its_password()
    {
        $existing = User::factory()->create([
            'email' => 'nuevo@gmail.com',
            'username' => 'pablo',
            'password' => 'secreto-original',
        ]);
        $originalHash = $existing->fresh()->password;

        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $existing->refresh();
        $this->assertSame('1234567890', $existing->google_id);
        $this->assertSame('pablo', $existing->username);        // no se pisa el username
        $this->assertSame($originalHash, $existing->password);  // ni la contraseña
        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($existing);
    }

    public function test_an_unverified_google_email_cannot_take_over_an_existing_account()
    {
        User::factory()->create(['email' => 'nuevo@gmail.com']);

        $this->fakeGoogleUser(['raw' => ['email_verified' => false]]);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['google_id' => '1234567890']);
    }

    public function test_signup_is_blocked_when_registration_is_disabled()
    {
        Setting::put('features.registration', false);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_existing_users_can_still_sign_in_with_registration_disabled()
    {
        $user = User::factory()->create(['email' => 'nuevo@gmail.com', 'google_id' => '1234567890']);
        Setting::put('features.registration', false);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_banned_users_are_rejected()
    {
        User::factory()->create([
            'email' => 'nuevo@gmail.com',
            'google_id' => '1234567890',
            'banned_at' => now(),
        ]);

        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_google_user_can_set_a_password_without_the_current_one()
    {
        $user = User::factory()->create(['password' => null, 'google_id' => '999']);

        $this->actingAs($user)
            ->get('/settings/password')
            ->assertInertia(fn ($page) => $page->where('hasPassword', false));

        $this->actingAs($user)->put('/settings/password', [
            'password' => 'contrasena-nueva-1',
            'password_confirmation' => 'contrasena-nueva-1',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->password);
    }

    public function test_a_google_user_deletes_the_account_by_typing_the_username()
    {
        $user = User::factory()->create(['password' => null, 'google_id' => '999', 'username' => 'pablo']);

        $this->actingAs($user)
            ->delete('/settings/profile', ['confirm_username' => 'otro'])
            ->assertSessionHasErrors('confirm_username');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->actingAs($user)
            ->delete('/settings/profile', ['confirm_username' => 'pablo'])
            ->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_users_with_a_password_still_need_it_to_delete_the_account()
    {
        $user = User::factory()->create(['password' => 'mi-password-123']);

        $this->actingAs($user)
            ->delete('/settings/profile', ['confirm_username' => $user->username])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
