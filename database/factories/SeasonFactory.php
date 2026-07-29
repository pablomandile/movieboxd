<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\Title;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title_id' => Title::factory()->tv(),
            'tmdb_id' => fake()->unique()->numberBetween(1, 2000000),
            'season_number' => 1,
            'name' => 'Season 1',
            'episodes_count' => 0,
            'synced_at' => null,
        ];
    }

    public function synced(): static
    {
        return $this->state(fn () => ['synced_at' => now()]);
    }
}
