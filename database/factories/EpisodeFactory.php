<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'title_id' => fn (array $attributes) => Season::find($attributes['season_id'])->title_id,
            'tmdb_id' => fake()->unique()->numberBetween(1, 5000000),
            'season_number' => fn (array $attributes) => Season::find($attributes['season_id'])->season_number,
            'episode_number' => 1,
            'name' => fake()->sentence(3),
            'overview' => fake()->paragraph(),
            'air_date' => fake()->date(),
            'runtime' => fake()->numberBetween(20, 60),
        ];
    }
}
