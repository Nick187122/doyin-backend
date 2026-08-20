<?php

namespace Tests\Unit\Models;

use App\Models\HeroImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $heroImage = HeroImage::factory()->create([
            'image_path' => '/storage/heroes/test.jpg',
            'title' => 'Welcome Banner',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->assertSame('/storage/heroes/test.jpg', $heroImage->image_path);
        $this->assertSame('Welcome Banner', $heroImage->title);
        $this->assertTrue($heroImage->is_active);
        $this->assertSame(1, $heroImage->order);
    }

    public function test_it_casts_is_active_as_boolean(): void
    {
        $heroImage = HeroImage::factory()->create(['is_active' => false]);
        $this->assertIsBool($heroImage->is_active);
        $this->assertFalse($heroImage->is_active);
    }

    public function test_it_casts_order_as_integer(): void
    {
        $heroImage = HeroImage::factory()->create(['order' => 5]);
        $this->assertIsInt($heroImage->order);
        $this->assertSame(5, $heroImage->order);
    }

    public function test_it_can_be_inactive(): void
    {
        $heroImage = HeroImage::factory()->inactive()->create();
        $this->assertFalse($heroImage->is_active);
    }
}
