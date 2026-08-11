<?php

namespace Tests\Feature\Tracking;

use App\Models\DiaryEntry;
use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Como en Letterboxd: "Lo que vi" muestra todo lo visionado y el Diario es la
 * bitácora, con las reseñas y los revisionados. El registro del visionado no
 * cambia, así que las estadísticas y los contadores siguen viendo todo.
 */
class DiaryOnlyReviewsAndRewatchesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_a_plain_viewing_does_not_show_up_in_the_diary()
    {
        $title = Title::factory()->create();

        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => now()->toDateString(),
        ]);

        // Se registró el visionado…
        $this->assertSame(1, DiaryEntry::where('user_id', $this->user->id)->count());
        $this->assertDatabaseHas('watched_titles', ['user_id' => $this->user->id, 'title_id' => $title->id]);

        // …pero el Diario no lo lista
        $this->actingAs($this->user)->get('/diary')->assertInertia(
            fn ($page) => $page->component('Diary/Index')->has('entries.data', 0)
        );
    }

    public function test_an_entry_with_a_review_shows_up()
    {
        $title = Title::factory()->create();

        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => now()->toDateString(),
            'review' => 'Una maravilla.',
        ]);

        $this->actingAs($this->user)->get('/diary')->assertInertia(
            fn ($page) => $page->has('entries.data', 1)
                ->where('entries.data.0.name', $title->localized_title)
                ->where('entries.data.0.reviewUrl', route('reviews.show', Review::firstOrFail()))
        );
    }

    public function test_a_rewatch_shows_up_even_without_a_review()
    {
        $title = Title::factory()->create();

        // El segundo log del mismo título se autodetecta como revisionado
        foreach ([now()->subMonth(), now()] as $date) {
            $this->actingAs($this->user)->post('/log', [
                'loggable_type' => 'title',
                'loggable_id' => $title->id,
                'watched_on' => $date->toDateString(),
            ]);
        }

        $this->assertSame(2, DiaryEntry::where('user_id', $this->user->id)->count());

        $this->actingAs($this->user)->get('/diary')->assertInertia(
            fn ($page) => $page->has('entries.data', 1)
                ->where('entries.data.0.isRewatch', true)
                ->where('entries.data.0.reviewUrl', null)
        );
    }

    public function test_the_entry_created_by_rating_stays_out_of_the_diary()
    {
        $title = Title::factory()->create();

        // Calificar implica visto y crea la entrada de diario
        $this->actingAs($this->user)->put('/ratings', [
            'rateable_type' => 'title',
            'rateable_id' => $title->id,
            'value' => 8,
        ]);

        $this->assertSame(1, DiaryEntry::where('user_id', $this->user->id)->count());

        $this->actingAs($this->user)->get('/diary')->assertInertia(
            fn ($page) => $page->has('entries.data', 0)
        );

        // Pero sí está en "Lo que vi"
        $this->actingAs($this->user)->get('/watched')->assertInertia(
            fn ($page) => $page->has('titles.data', 1)->where('titles.data.0.rating', 8)
        );
    }

    public function test_the_public_profile_diary_tab_applies_the_same_rule()
    {
        $reviewed = Title::factory()->create();
        $plain = Title::factory()->create();

        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $reviewed->id,
            'watched_on' => '2025-07-01',
            'review' => 'Buenísima.',
        ]);

        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $plain->id,
            'watched_on' => '2024-03-02',
        ]);

        // Solo el año de la entrada reseñada aparece en el navegador de años
        $this->get("/u/{$this->user->username}/diary")->assertInertia(
            fn ($page) => $page->component('profile/Diary')
                ->has('entries.data', 1)
                ->where('entries.data.0.name', $reviewed->localized_title)
                ->where('years', [2025])
        );
    }

    public function test_the_statistics_still_count_every_viewing()
    {
        $title = Title::factory()->create(['runtime' => 100]);

        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => now()->toDateString(),
        ]);

        // El visionado sin reseña no se lista en el Diario pero sigue contando
        $this->assertSame(1, $this->user->diaryEntries()->count());
        $this->assertSame(0, $this->user->diaryEntries()->inDiary()->count());
        $this->assertSame(1, $title->fresh()->watched_count);
    }
}
