<?php

namespace Database\Factories;

use App\Models\ListModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListModel>
 */
class ListModelFactory extends Factory
{
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_ranked' => false,
            'is_public' => true,
        ];
    }
}
