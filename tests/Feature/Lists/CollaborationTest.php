<?php

namespace Tests\Feature\Lists;

use App\Models\ListModel;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $guest;

    protected ListModel $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->guest = User::factory()->create();
        $this->list = ListModel::factory()->create(['user_id' => $this->owner->id, 'name' => 'Para ver juntos']);
    }

    /** Genera el enlace y devuelve su token. */
    protected function inviteUrl(): string
    {
        $url = $this->actingAs($this->owner)
            ->post(route('lists.invite', $this->list))
            ->assertSessionHas('inviteUrl')
            ->getSession()
            ->get('inviteUrl');

        return $url;
    }

    public function test_only_the_owner_can_generate_the_invite_link()
    {
        $this->actingAs($this->guest)
            ->post(route('lists.invite', $this->list))
            ->assertForbidden();

        $this->assertNull($this->list->fresh()->invite_token);

        $this->actingAs($this->owner)
            ->post(route('lists.invite', $this->list))
            ->assertSessionHas('inviteUrl');

        $this->assertNotNull($this->list->fresh()->invite_token);
    }

    public function test_the_token_is_never_exposed_to_the_frontend()
    {
        $this->inviteUrl();

        $this->actingAs($this->owner)
            ->get(route('lists.show', [$this->owner->username, $this->list->id]))
            ->assertInertia(fn ($page) => $page->missing('list.invite_token')->missing('list.inviteToken'));
    }

    public function test_opening_the_link_grants_editing_and_lets_the_user_change_items()
    {
        $url = $this->inviteUrl();
        $title = Title::factory()->create();

        // Antes de aceptar no puede tocar nada
        $this->actingAs($this->guest)
            ->post(route('lists.items.store', $this->list), ['title_id' => $title->id])
            ->assertForbidden();

        // El destino tiene que ser la lista en serio: lists.show resuelve por id
        $this->actingAs($this->guest)->get($url)
            ->assertRedirect(route('lists.show', [$this->owner->username, $this->list->id]));

        $this->assertDatabaseHas('list_collaborators', [
            'list_id' => $this->list->id,
            'user_id' => $this->guest->id,
        ]);

        $this->actingAs($this->guest)
            ->get(route('lists.show', [$this->owner->username, $this->list->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('list.canEditItems', true)->where('list.isOwn', false));

        $this->actingAs($this->guest)
            ->post(route('lists.items.store', $this->list), ['title_id' => $title->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('list_items', ['list_id' => $this->list->id, 'title_id' => $title->id]);
    }

    public function test_a_collaborator_cannot_rename_or_delete_the_list()
    {
        $url = $this->inviteUrl();
        $this->actingAs($this->guest)->get($url);

        $this->actingAs($this->guest)
            ->put(route('lists.update', $this->list), ['name' => 'Secuestrada', 'is_public' => true, 'is_ranked' => false])
            ->assertForbidden();

        $this->actingAs($this->guest)
            ->delete(route('lists.destroy', $this->list))
            ->assertForbidden();

        $this->assertSame('Para ver juntos', $this->list->fresh()->name);
    }

    public function test_a_collaborator_cannot_invite_others_nor_revoke()
    {
        $other = User::factory()->create();
        $url = $this->inviteUrl();
        $this->actingAs($this->guest)->get($url);

        $this->actingAs($this->guest)
            ->post(route('lists.invite', $this->list))
            ->assertForbidden();

        $this->actingAs($this->guest)
            ->delete(route('lists.collaborators.revoke', [$this->list, $other]))
            ->assertForbidden();
    }

    public function test_the_owner_revokes_editing_and_it_takes_effect_immediately()
    {
        $url = $this->inviteUrl();
        $this->actingAs($this->guest)->get($url);
        $title = Title::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('lists.collaborators.revoke', [$this->list, $this->guest]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('list_collaborators', [
            'list_id' => $this->list->id,
            'user_id' => $this->guest->id,
        ]);

        $this->actingAs($this->guest)
            ->post(route('lists.items.store', $this->list), ['title_id' => $title->id])
            ->assertForbidden();
    }

    public function test_regenerating_the_link_invalidates_the_previous_one()
    {
        $oldUrl = $this->inviteUrl();

        $this->actingAs($this->owner)
            ->post(route('lists.invite.regenerate', $this->list))
            ->assertSessionHas('inviteUrl');

        $this->actingAs($this->guest)->get($oldUrl)->assertNotFound();
        $this->assertDatabaseMissing('list_collaborators', ['list_id' => $this->list->id, 'user_id' => $this->guest->id]);
    }

    public function test_regenerating_does_not_expel_current_collaborators()
    {
        $url = $this->inviteUrl();
        $this->actingAs($this->guest)->get($url);

        $this->actingAs($this->owner)->post(route('lists.invite.regenerate', $this->list));

        $this->assertDatabaseHas('list_collaborators', ['list_id' => $this->list->id, 'user_id' => $this->guest->id]);
    }

    public function test_an_invalid_token_is_a_404()
    {
        $this->actingAs($this->guest)
            ->get(route('lists.invite.accept', 'token-que-no-existe'))
            ->assertNotFound();
    }

    public function test_guests_are_sent_to_login_and_return_to_the_invite()
    {
        // El token se siembra a mano: usar el endpoint dejaría la sesión del
        // dueño abierta y el "invitado" no sería tal.
        $this->list->forceFill(['invite_token' => 'token-de-prueba'])->save();

        // El permiso se otorga a una cuenta, no al navegador que tenga el link
        $this->get(route('lists.invite.accept', 'token-de-prueba'))->assertRedirect(route('login'));

        $this->assertSame(0, $this->list->collaborators()->count());
    }

    public function test_a_collaborator_can_see_a_private_list()
    {
        $this->list->update(['is_public' => false]);
        $url = $this->inviteUrl();

        $showRoute = route('lists.show', [$this->owner->username, $this->list->id]);

        $this->actingAs($this->guest)->get($showRoute)->assertForbidden();

        $this->actingAs($this->guest)->get($url);

        $this->actingAs($this->guest)->get($showRoute)->assertOk();
    }

    public function test_the_owner_opening_their_own_link_is_not_added_as_collaborator()
    {
        $url = $this->inviteUrl();

        $this->actingAs($this->owner)->get($url)->assertRedirect();

        $this->assertSame(0, $this->list->collaborators()->count());
    }
}
