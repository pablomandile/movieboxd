<?php

namespace Database\Factories;

use App\Models\DiaryEntry;
use App\Models\Title;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryEntry>
 */
class DiaryEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'loggable_type' => 'title',
            'loggable_id' => Title::factory(),
            'watched_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'rating' => fake()->optional()->numberBetween(1, 10),
            'is_rewatch' => false,
        ];
    }
}
