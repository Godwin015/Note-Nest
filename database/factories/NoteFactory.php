<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'category' => fake()->randomElement([
                'work',
                'personal',
                'learning',
            ]),
            'is_pinned' => false,
        ];
    }
}
