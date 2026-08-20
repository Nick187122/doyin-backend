<?php

namespace Database\Factories;

use App\Models\Salesperson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salesperson>
 */
class SalespersonFactory extends Factory
{
    protected $model = Salesperson::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_number' => fake()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
