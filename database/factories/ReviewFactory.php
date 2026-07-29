<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reviewable_type' => 'title',
            'reviewable_id' => Title::factory(),
            'body' => fake()->paragraphs(2, true),
            'contains_spoilers' => false,
        ];
    }

    public function withSpoilers(): static
    {
        return $this->state(fn () => ['contains_spoilers' => true]);
    }
}
