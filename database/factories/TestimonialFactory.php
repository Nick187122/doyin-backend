<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'content' => fake()->paragraph(3),
            'avatar' => null,
            'video_url' => null,
            'rating' => fake()->numberBetween(3, 5),
            'is_visible' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }

    public function withVideo(): static
    {
        return $this->state(fn (array $attributes) => [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }
}
