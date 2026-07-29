<?php

namespace Tests\Feature\Social;

use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Services\ActivityFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_is_asymmetric_and_toggleable()
    {
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAs($a)->post("/follow/{$b->username}");

        $this->assertTrue($a->isFollowing($b));
        $this->assertFalse($b->isFollowing($a));

        $this->actingAs($a)->post("/follow/{$b->username}");
        $this->assertFalse($a->fresh()->isFollowing($b));
    }

    public function test_users_cannot_follow_themselves()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post("/follow/{$user->username}")->assertStatus(422);
    }

    public function test_feed_shows_diary_and_reviews_but_not_bare_watches()
    {
        [$viewer, $friend] = User::factory()->count(2)->create();
        $this->actingAs($viewer)->post("/follow/{$friend->username}");

        $logged = Title::factory()->create();
        $reviewed = Title::factory()->create();
        $justWatched = Title::factory()->create();

        // Log con review adjunta → UN solo ítem
        $this->actingAs($friend)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $logged->id,
            'watched_on' => now()->toDateString(),
            'rating' => 8,
            'review' => 'Muy buena.',
        ]);

        // Review standalone → un ítem
        Review::factory()->create(['user_id' => $friend->id, 'reviewable_id' => $reviewed->id]);

        // Watched suelto → NO aparece (regla 7)
        WatchedTitle::create(['user_id' => $friend->id, 'title_id' => $justWatched->id]);

        $feed = app(ActivityFeedService::class)->for($viewer->fresh());

        // Se comparan como conjunto: ambos ítems se crean en el mismo segundo
        $this->assertCount(2, $feed);
        $this->assertEqualsCanonicalizing(['diary', 'review'], $feed->pluck('kind')->all());

        $diaryItem = $feed->firstWhere('kind', 'diary');
        $this->assertNotNull($diaryItem['review'], 'El log con review adjunta debe incluir la review en el mismo ítem');
    }

    public function test_profile_pages_render_with_all_tabs()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$title->slug}/watched");

        $this->get("/u/{$user->username}")->assertInertia(
            fn (Assert $page) => $page
                ->component('profile/Films')
                ->where('profile.username', $user->username)
                ->has('titles.data', 1)
        );

        foreach (['diary', 'reviews', 'watchlist', 'likes', 'network'] as $tab) {
            $this->get("/u/{$user->username}/{$tab}")->assertOk();
        }

        $this->get("/u/{$user->username}/inexistente")->assertNotFound();
    }

    public function test_favorites_are_capped_at_four()
    {
        $user = User::factory()->create();
        $titles = Title::factory()->count(5)->create();

        foreach ($titles->take(4) as $title) {
            $this->actingAs($user)->post("/titles/{$title->slug}/favorite");
        }

        $this->assertSame(4, $user->favorites()->count());

        $this->actingAs($user)
            ->post("/titles/{$titles->last()->slug}/favorite")
            ->assertSessionHasErrors('favorites');

        $this->assertSame(4, $user->favorites()->count());

        // Toggle: quitar uno libera el cupo
        $this->actingAs($user)->post("/titles/{$titles->first()->slug}/favorite");
        $this->assertSame(3, $user->favorites()->count());
    }

    public function test_home_shows_feed_for_authenticated_users()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $page) => $page->component('Home')->has('feed')
        );
    }
}
