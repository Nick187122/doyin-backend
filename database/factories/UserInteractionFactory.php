<?php

namespace Database\Factories;

use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInteraction>
 */
class UserInteractionFactory extends Factory
{
    protected $model = UserInteraction::class;

    public function definition(): array
    {
        return [
            'type' => 'message',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'content' => fake()->paragraph(),
            'is_read' => false,
        ];
    }

    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'issue',
            'name' => null,
            'email' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }
}
