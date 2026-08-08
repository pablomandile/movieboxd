<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Report;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    public function test_dashboard_shows_aggregate_stats()
    {
        Title::factory()->count(2)->create();
        Title::factory()->tv()->create();
        // El factory de Review crea su propio Title (movie) → 3 películas en total
        Review::factory()->create();

        $this->actingAs($this->admin)->get('/admin')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('stats.movies', 3)
                ->where('stats.shows', 1)
                ->where('stats.reviews', 1)
                ->has('recentUsers')
        );
    }

    public function test_admin_sections_are_closed_to_regular_users()
    {
        $user = User::factory()->create();

        foreach (['/admin', '/admin/users', '/admin/reports', '/admin/settings'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_users_can_be_searched_and_have_their_role_changed()
    {
        $target = User::factory()->create(['name' => 'Ada Lovelace', 'username' => 'ada']);
        User::factory()->create(['name' => 'Otro', 'username' => 'otro']);

        $this->actingAs($this->admin)->get('/admin/users?search=ada')->assertInertia(
            fn (Assert $page) => $page->component('admin/Users')->has('users.data', 1)->where('users.data.0.username', 'ada')
        );

        $this->actingAs($this->admin)->put("/admin/users/{$target->id}/role", ['role' => 'admin']);
        $this->assertSame(Role::Admin, $target->fresh()->role);
    }

    public function test_admins_cannot_demote_or_ban_themselves()
    {
        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->id}/role", ['role' => 'user'])
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);

        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->id}/ban")
            ->assertSessionHasErrors('ban');

        $this->assertFalse($this->admin->fresh()->isBanned());
    }

    public function test_banning_kills_active_sessions_and_blocks_navigation()
    {
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-of-target',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->admin)->put("/admin/users/{$target->id}/ban");

        $this->assertNotNull($target->fresh()->banned_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-of-target']);

        // El baneado ya no puede navegar
        $this->actingAs($target->fresh())->get('/diary')->assertRedirect(route('login'));

        // Y se puede revertir
        $this->actingAs($this->admin)->put("/admin/users/{$target->id}/ban");
        $this->assertNull($target->fresh()->banned_at);
    }

    public function test_resolving_a_report_deletes_content_and_updates_counters()
    {
        $title = Title::factory()->create();
        $review = Review::factory()->create(['reviewable_id' => $title->id]);
        $this->assertSame(1, $title->fresh()->reviews_count);

        $report = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'abuse',
        ]);

        $this->actingAs($this->admin)->put("/admin/reports/{$report->id}", ['action' => 'delete_content']);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertSame(0, $title->fresh()->reviews_count);
        $this->assertSame('resolved', $report->fresh()->status->value);
        $this->assertSame($this->admin->id, $report->fresh()->resolved_by);
    }

    public function test_dismissing_a_report_keeps_the_content()
    {
        $review = Review::factory()->create();
        $report = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($this->admin)->put("/admin/reports/{$report->id}", ['action' => 'dismiss']);

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
        $this->assertSame('dismissed', $report->fresh()->status->value);
    }

    public function test_tmdb_key_is_stored_encrypted_and_used_by_the_client()
    {
        $this->actingAs($this->admin)->put('/admin/settings', [
            'tmdb_api_key' => 'super-secret-key',
            'rating_prior' => 30,
            'features' => [],
        ]);

        $stored = DB::table('settings')->where('key', 'tmdb.api_key')->value('value');

        $this->assertNotSame('super-secret-key', $stored, 'La key no puede quedar en texto plano');
        $this->assertSame('super-secret-key', Crypt::decryptString($stored));
        $this->assertSame('super-secret-key', Setting::get('tmdb.api_key'));

        // La página nunca devuelve la key, solo si existe
        $this->actingAs($this->admin)->get('/admin/settings')->assertInertia(
            fn (Assert $page) => $page->component('admin/Settings')->where('tmdb.hasKey', true)->missing('tmdb.key')
        );
    }

    public function test_feature_flags_block_the_matching_endpoints()
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($this->admin)->put('/admin/settings', [
            'rating_prior' => 30,
            'features' => ['features.comments' => false, 'features.lists' => false, 'features.registration' => false],
        ]);

        // El registro se corta antes que el middleware guest: se prueba deslogueado
        $this->post('/logout');
        $this->get('/register')->assertForbidden();

        $this->actingAs($user)
            ->post("/reviews/{$review->id}/comments", ['body' => 'hola'])
            ->assertForbidden();

        $this->actingAs($user)->post('/lists', ['name' => 'X'])->assertForbidden();

        // Reactivar los flags restaura el acceso
        $this->actingAs($this->admin)->put('/admin/settings', [
            'rating_prior' => 30,
            'features' => ['features.comments' => true, 'features.lists' => true, 'features.registration' => true],
        ]);

        $this->actingAs($user)->post("/reviews/{$review->id}/comments", ['body' => 'hola'])->assertRedirect();
    }

    public function test_rating_prior_setting_changes_the_weighted_average()
    {
        $title = Title::factory()->create();
        $other = Title::factory()->create();
        $user = User::factory()->create();

        // 5★ en el título medido y 1★ en otro: la media global queda por debajo de 5
        $this->actingAs($user)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $title->id, 'value' => 10]);
        $this->actingAs($user)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $other->id, 'value' => 2]);

        $this->actingAs($this->admin)->put('/admin/settings', ['rating_prior' => 0, 'features' => []]);
        Cache::forget('ratings:global-average');

        // Sin prior, un solo voto de 5★ da exactamente 5.0
        $this->assertSame(5.0, $title->fresh()->weighted_rating);

        // Con prior 30, el promedio se amortigua hacia la media global (3.0)
        $this->actingAs($this->admin)->put('/admin/settings', ['rating_prior' => 30, 'features' => []]);
        Cache::forget('ratings:global-average');

        $this->assertLessThan(5.0, $title->fresh()->weighted_rating);
    }
}
