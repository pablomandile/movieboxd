<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Support\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_see_the_stats()
    {
        $this->get('/admin/stats')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/stats')->assertForbidden();
    }

    public function test_the_page_shows_activity_per_day_and_per_user()
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $user = User::factory()->create();
        WatchlistItem::create(['user_id' => $user->id, 'title_id' => Title::factory()->create()->id]);
        Metrics::increment(Metrics::TMDB_REQUESTS, 5);

        $this->actingAs($user)->get('/about');

        $this->actingAs($admin)->get('/admin/stats')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/Stats')
                ->where('summary.users', 2)
                ->where('summary.activeToday', 2)
                ->has('days', 30)
                ->where('days.29.date', now()->toDateString())
                ->where('days.29.activeUsers', 2)
                ->where('days.29.signups', 2)
                ->where('days.29.tmdbRequests', 5)
                ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $user->id)['watchlist'] === 1)
                ->where('technical.last30.tmdbRequests', 5)
        );
    }

    public function test_activity_is_recorded_at_most_every_ten_minutes()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/about');
        $first = $user->fresh()->last_seen_at;
        $this->assertNotNull($first);
        $this->assertSame(1, DB::table('user_activity_days')->where('user_id', $user->id)->count());

        $this->travel(5)->minutes();
        $this->get('/about');
        $this->assertTrue($user->fresh()->last_seen_at->equalTo($first));

        $this->travel(6)->minutes();
        $this->get('/about');
        $this->assertTrue($user->fresh()->last_seen_at->gt($first));
    }

    public function test_metrics_accumulate_per_day()
    {
        Metrics::increment('prueba');
        Metrics::increment('prueba', 3);
        Metrics::increment('prueba', 0);

        $this->assertSame(4, (int) DB::table('daily_metrics')->where('metric', 'prueba')->value('total'));
    }

    public function test_imports_and_blocked_crawlers_are_counted()
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::fake(['*/movie/603*' => Http::response([
            'id' => 603, 'title' => 'The Matrix', 'overview' => '', 'tagline' => '', 'release_date' => '1999-03-31',
            'translations' => ['translations' => []], 'credits' => ['cast' => [], 'crew' => []],
        ])]);

        $this->get('/resolve/movie/603')->assertRedirect();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/resolve/movie/604')->assertNotFound();

        $total = fn (string $metric) => (int) DB::table('daily_metrics')->where('metric', $metric)->value('total');
        $this->assertSame(1, $total(Metrics::TMDB_REQUESTS));
        $this->assertSame(1, $total(Metrics::IMPORTED_TITLES));
        $this->assertSame(1, $total(Metrics::BLOCKED_CRAWLERS));
    }
}
