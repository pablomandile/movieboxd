<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_switch_locale_via_session()
    {
        $this->from('/')->put('/settings/locale', ['locale' => 'en'])->assertRedirect('/');

        $this->get('/')->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_users_locale_is_persisted_and_applied()
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->put('/settings/locale', ['locale' => 'en']);

        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user)->get('/diary');
        $this->assertSame('en', app()->getLocale());
    }

    public function test_invalid_locales_are_rejected()
    {
        $this->put('/settings/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_default_locale_is_spanish()
    {
        $this->get('/');
        $this->assertSame('es', app()->getLocale());
    }
}
