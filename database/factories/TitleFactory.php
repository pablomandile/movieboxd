<?php

namespace Database\Factories;

use App\Enums\TitleType;
use App\Models\Title;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Title>
 */
class TitleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'type' => TitleType::Movie,
            'tmdb_id' => fake()->unique()->numberBetween(1, 2000000),
            'slug' => Str::slug($name),
            'title' => rtrim($name, '.'),
            'original_title' => rtrim($name, '.'),
            'overview' => fake()->paragraph(),
            'release_date' => fake()->date(),
            'runtime' => fake()->numberBetween(80, 180),
            'genres' => [['id' => 18, 'name' => 'Drama']],
            'popularity' => fake()->randomFloat(3, 0, 100),
            'synced_at' => now(),
        ];
    }

    public function tv(): static
    {
        return $this->state(fn () => [
            'type' => TitleType::Tv,
            'runtime' => null,
            'tv_status' => 'Ended',
            'seasons_count' => 1,
            'episodes_count' => 8,
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn () => ['synced_at' => now()->subDays(90)]);
    }
}
