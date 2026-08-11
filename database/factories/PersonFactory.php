<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 999999),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => $name,
            'profile_path' => '/'.fake()->lexify('??????????').'.jpg',
            'biography' => fake()->paragraph(),
            'known_for_department' => 'Acting',
            'credits' => [],
            'popularity' => fake()->randomFloat(2, 0, 50),
            'synced_at' => now(),
        ];
    }
}
