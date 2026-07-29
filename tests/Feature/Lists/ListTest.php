<?php

namespace Tests\Feature\Lists;

use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_create_a_ranked_list_and_add_titles()
    {
        $user = User::factory()->create();
        $titles = Title::factory()->count(3)->create();

        $this->actingAs($user)->post('/lists', [
            'name' => 'Mis favoritas de 2026',
            'description' => 'Lo mejor del año.',
            'is_ranked' => true,
            'is_public' => true,
        ]);

        $list = ListModel::first();

        $this->assertNotNull($list);
        $this->assertSame('mis-favoritas-de-2026', $list->slug);
        $this->assertTrue($list->is_ranked);

        foreach ($titles as $title) {
            $this->actingAs($user)->post("/lists/{$list->id}/items", ['title_id' => $title->id]);
        }

        $this->assertSame([1, 2, 3], $list->items()->pluck('position')->all());

        // Agregar dos veces el mismo título no duplica
        $this->actingAs($user)->post("/lists/{$list->id}/items", ['title_id' => $titles->first()->id]);
        $this->assertSame(3, $list->items()->count());
    }

    public function test_slug_collisions_get_a_suffix()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lists', ['name' => 'Mi lista']);
        $this->actingAs($user)->post('/lists', ['name' => 'Mi lista']);

        $this->assertSame(['mi-lista', 'mi-lista-2'], ListModel::orderBy('id')->pluck('slug')->all());
    }

    public function test_reorder_persists_new_positions()
    {
        $user = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $user->id, 'is_ranked' => true]);
        $titles = Title::factory()->count(3)->create();

        foreach ($titles as $title) {
            $this->actingAs($user)->post("/lists/{$list->id}/items", ['title_id' => $title->id]);
        }

        $ids = $list->items()->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($user)->post("/lists/{$list->id}/reorder", ['item_ids' => $reversed]);

        $this->assertSame($reversed, $list->items()->orderBy('position')->pluck('id')->all());
    }

    public function test_reorder_rejects_mismatched_item_sets()
    {
        $user = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $user->id]);
        $title = Title::factory()->create();
        $this->actingAs($user)->post("/lists/{$list->id}/items", ['title_id' => $title->id]);

        $this->actingAs($user)
            ->post("/lists/{$list->id}/reorder", ['item_ids' => [999]])
            ->assertStatus(422);
    }

    public function test_removing_an_item_resequences_positions()
    {
        $user = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $user->id]);
        $titles = Title::factory()->count(3)->create();

        foreach ($titles as $title) {
            $this->actingAs($user)->post("/lists/{$list->id}/items", ['title_id' => $title->id]);
        }

        $first = $list->items()->orderBy('position')->first();
        $this->actingAs($user)->delete("/lists/{$list->id}/items/{$first->id}");

        $this->assertSame([1, 2], $list->items()->orderBy('position')->pluck('position')->all());
    }

    public function test_private_lists_are_hidden_from_others()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

        $url = "/list/{$owner->username}/{$list->id}";

        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->get($url)->assertForbidden();
    }

    public function test_only_the_owner_can_edit_a_list()
    {
        $list = ListModel::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/lists/{$list->id}/edit")->assertForbidden();
        $this->actingAs($intruder)->put("/lists/{$list->id}", ['name' => 'robada'])->assertForbidden();
        $this->actingAs($intruder)->delete("/lists/{$list->id}")->assertForbidden();
    }

    public function test_list_page_shows_notes_and_watched_progress()
    {
        $owner = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $owner->id]);
        $titles = Title::factory()->count(2)->create();

        ListItem::create(['list_id' => $list->id, 'title_id' => $titles[0]->id, 'position' => 1, 'note' => 'Imperdible.']);
        ListItem::create(['list_id' => $list->id, 'title_id' => $titles[1]->id, 'position' => 2]);

        $this->actingAs($owner)->post("/titles/{$titles[0]->slug}/watched");

        $this->actingAs($owner)->get("/list/{$owner->username}/{$list->id}")->assertInertia(
            fn (Assert $page) => $page
                ->component('lists/Show')
                ->has('items', 2)
                ->where('items.0.note', 'Imperdible.')
                ->where('items.0.watched', true)
                ->where('items.1.watched', false)
        );
    }

    public function test_lists_can_be_liked_and_commented()
    {
        $owner = User::factory()->create();
        $fan = User::factory()->create();
        $list = ListModel::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($fan)->post("/lists/{$list->id}/like");
        $this->assertSame(1, $list->fresh()->likes_count);

        $this->actingAs($fan)->post("/lists/{$list->id}/comments", ['body' => 'Gran lista.']);
        $this->assertSame(1, $list->fresh()->comments_count);
    }

    public function test_public_index_and_profile_tab_render()
    {
        $user = User::factory()->create();
        ListModel::factory()->create(['user_id' => $user->id]);
        ListModel::factory()->create(['user_id' => $user->id, 'is_public' => false]);

        // Como invitado: solo listas públicas
        $this->get('/lists')->assertInertia(fn (Assert $page) => $page->component('lists/Index')->has('lists.data', 1));

        $this->get("/u/{$user->username}/lists")->assertInertia(
            fn (Assert $page) => $page->component('profile/Lists')->has('lists.data', 1)
        );

        // El dueño ve también las privadas en su perfil
        $this->actingAs($user)->get("/u/{$user->username}/lists")->assertInertia(
            fn (Assert $page) => $page->has('lists.data', 2)
        );
    }
}
