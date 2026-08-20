<?php

namespace Database\Factories;

use App\Models\HeroImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroImage>
 */
class HeroImageFactory extends Factory
{
    protected $model = HeroImage::class;

    public function definition(): array
    {
        return [
            'image_path' => '/storage/heroes/' . fake()->uuid() . '.jpg',
            'title' => fake()->sentence(3),
            'is_active' => true,
            'order' => fake()->numberBetween(0, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
