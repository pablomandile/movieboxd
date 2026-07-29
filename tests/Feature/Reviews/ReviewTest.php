<?php

namespace Tests\Feature\Reviews;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_with_review_creates_a_single_linked_review()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => now()->toDateString(),
            'rating' => 8,
            'review' => 'Una obra maestra del cine.',
            'contains_spoilers' => false,
        ]);

        $review = Review::first();

        $this->assertNotNull($review);
        $this->assertNotNull($review->diary_entry_id);
        $this->assertSame(1, $title->fresh()->reviews_count);
    }

    public function test_standalone_review_can_be_created()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post('/reviews', [
            'reviewable_type' => 'title',
            'reviewable_id' => $title->id,
            'body' => 'Review sin log.',
            'contains_spoilers' => true,
        ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'reviewable_id' => $title->id,
            'diary_entry_id' => null,
            'contains_spoilers' => true,
        ]);
    }

    public function test_review_likes_and_comments_update_counters()
    {
        $author = User::factory()->create();
        $fan = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);

        $this->actingAs($fan)->post("/reviews/{$review->id}/like");
        $this->assertSame(1, $review->fresh()->likes_count);

        // Toggle: quitar el like
        $this->actingAs($fan)->post("/reviews/{$review->id}/like");
        $this->assertSame(0, $review->fresh()->likes_count);

        $this->actingAs($fan)->post("/reviews/{$review->id}/comments", ['body' => 'Totalmente de acuerdo.']);
        $this->assertSame(1, $review->fresh()->comments_count);
    }

    public function test_users_cannot_edit_others_reviews()
    {
        $review = Review::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put("/reviews/{$review->id}", ['body' => 'hackeada'])
            ->assertForbidden();

        $this->actingAs($intruder)->delete("/reviews/{$review->id}")->assertForbidden();
    }

    public function test_content_owner_can_delete_foreign_comments()
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);

        $this->actingAs($commenter)->post("/reviews/{$review->id}/comments", ['body' => 'spam']);
        $comment = Comment::first();

        // El autor de la review puede borrar comentarios ajenos en su contenido
        $this->actingAs($author)->delete("/comments/{$comment->id}")->assertRedirect();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_review_page_renders_with_comments()
    {
        $review = Review::factory()->create();
        Comment::create([
            'user_id' => User::factory()->create()->id,
            'commentable_type' => 'review',
            'commentable_id' => $review->id,
            'body' => 'Buen punto.',
        ]);

        $this->get("/review/{$review->id}")->assertInertia(
            fn (Assert $page) => $page
                ->component('Reviews/Show')
                ->where('review.id', $review->id)
                ->has('comments', 1)
        );
    }

    public function test_reports_can_be_filed_once_per_content()
    {
        $reporter = User::factory()->create();
        $review = Review::factory()->create();

        $payload = [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spoiler',
        ];

        $this->actingAs($reporter)->post('/reports', $payload);
        $this->actingAs($reporter)->post('/reports', $payload);

        $this->assertSame(1, Report::count());
    }
}
