<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Title;
use App\Models\User;
use App\Support\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = ['Accept' => 'text/html', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0'];

    private function total(string $metric): int
    {
        return (int) DB::table('daily_metrics')->where('metric', $metric)->value('total');
    }

    public function test_only_admins_can_see_the_stats()
    {
        $this->get('/admin/stats')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/stats')->assertForbidden();
    }

    public function test_the_page_shows_usage_per_day_and_per_user()
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $user = User::factory()->create();
        Metrics::increment(Metrics::TMDB_REQUESTS, 5);

        $this->actingAs($user)->withHeaders(self::BROWSER)->get('/about');
        $this->post(route('titles.watchlist', Title::factory()->create()));

        $this->actingAs($admin)->get('/admin/stats')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/Stats')
                ->where('demo', false)
                ->where('summary.visitsToday', 1)
                ->where('summary.actions30', 1)
                ->where('summary.users', 2)
                ->has('days', 30)
                ->where('days.29.date', now()->toDateString())
                ->where('days.29.visits', 1)
                ->where('days.29.pageviews', 1)
                ->where('days.29.actions', 1)
                ->where('days.29.activeUsers', 2)
                ->where('days.29.tmdbRequests', 5)
                ->where('actions.0', ['key' => 'watchlist', 'total' => 1])
                ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $user->id)['watchlist'] === 1)
        );
    }

    public function test_in_the_demo_the_per_user_section_is_hidden()
    {
        config(['demo.enabled' => true]);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get('/admin/stats')->assertInertia(
            fn (Assert $page) => $page->where('demo', true)->where('users', null)
        );
    }

    public function test_visits_count_once_per_session_and_day_and_page_views_every_page()
    {
        $this->withHeaders(self::BROWSER)->get('/about')->assertOk();
        $this->get('/about');
        $this->get('/about');

        $this->assertSame(1, $this->total(Metrics::VISITS));
        $this->assertSame(3, $this->total(Metrics::PAGEVIEWS));

        // Mañana la misma sesión vuelve a contar como visita
        $this->travel(1)->days();
        $this->get('/about');
        $this->assertSame(1, (int) DB::table('daily_metrics')->where('metric', Metrics::VISITS)->where('date', now()->toDateString())->value('total'));
    }

    public function test_bots_partial_reloads_and_admins_do_not_count()
    {
        $this->withHeaders(['Accept' => 'text/html', 'User-Agent' => 'Googlebot/2.1'])->get('/about');
        $this->withHeaders(self::BROWSER + ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'reviews'])->get('/about');

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->withHeaders(self::BROWSER)->get('/about');
        $this->post(route('titles.watchlist', Title::factory()->create()));

        $this->assertSame(0, DB::table('daily_metrics')->whereIn('metric', [Metrics::VISITS, Metrics::PAGEVIEWS, 'actions.watchlist'])->count());
    }

    public function test_logins_are_counted()
    {
        $user = User::factory()->create(['password' => 'secreto-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secreto-123'])->assertRedirect();

        $this->assertSame(1, $this->total(Metrics::LOGINS));
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

        $this->assertSame(4, $this->total('prueba'));
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

        $this->assertSame(1, $this->total(Metrics::TMDB_REQUESTS));
        $this->assertSame(1, $this->total(Metrics::IMPORTED_TITLES));
        $this->assertSame(1, $this->total(Metrics::BLOCKED_CRAWLERS));
    }
}
