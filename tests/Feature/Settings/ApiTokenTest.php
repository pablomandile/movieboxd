<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_token_shown_once_with_the_watchlist_ability(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings/api')->post('/settings/api', ['name' => 'Hoy Trasnoche'])
            ->assertRedirect('/settings/api')
            ->assertSessionHas('newToken');

        $token = $user->tokens()->sole();
        $this->assertSame('Hoy Trasnoche', $token->name);
        $this->assertSame(['watchlist'], $token->abilities);

        $this->actingAs($user)->get('/settings/api')->assertInertia(fn (Assert $page) => $page
            ->component('settings/Api')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Hoy Trasnoche')
            ->where('newToken', fn ($valor) => str_starts_with((string) $valor, $token->id.'|')));

        // Una sola vez: en la visita siguiente ya no está.
        $this->actingAs($user)->get('/settings/api')->assertInertia(fn (Assert $page) => $page->where('newToken', null));
    }

    public function test_revokes_only_own_tokens(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();
        $mio = $user->createToken('mio', ['watchlist'])->accessToken;
        $ajeno = $otro->createToken('ajeno', ['watchlist'])->accessToken;

        $this->actingAs($user)->delete("/settings/api/{$ajeno->id}")->assertRedirect();
        $this->assertSame(1, $otro->tokens()->count());

        $this->actingAs($user)->delete("/settings/api/{$mio->id}")->assertRedirect();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_tokens_cannot_be_created_in_the_demo(): void
    {
        config(['demo.enabled' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings/api')->post('/settings/api', ['name' => 'x'])->assertSessionHas('error');
        $this->assertSame(0, $user->tokens()->count());
    }
}
