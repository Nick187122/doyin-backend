<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true) . ' Pump',
            'description' => fake()->sentence(),
            'price' => null,
            'image_path' => null,
            'max_flow_rate' => fake()->randomElement(['5 m³/h', '10 m³/h', '20 m³/h', '50 m³/h']),
            'max_height' => fake()->randomElement(['30 m', '50 m', '80 m', '120 m']),
            'recommended_depth' => fake()->randomElement(['10 m', '20 m', '50 m']),
            'ideal_power' => fake()->randomElement(['1.5 kW', '3 kW', '5.5 kW', '7.5 kW']),
            'performance_curves' => null,
            'in_stock' => true,
        ];
    }

    public function withPrice(?float $price = null): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price ?? fake()->randomFloat(2, 5000, 500000),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => false,
        ]);
    }

    public function withPerformanceCurves(): static
    {
        return $this->state(fn (array $attributes) => [
            'performance_curves' => [
                ['flow_rate_m3h' => '5', 'head_m' => '80'],
                ['flow_rate_m3h' => '10', 'head_m' => '65'],
                ['flow_rate_m3h' => '15', 'head_m' => '45'],
                ['flow_rate_m3h' => '20', 'head_m' => '25'],
            ],
        ]);
    }
}
