<?php

namespace Tests\Unit\Models;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $testimonial = Testimonial::factory()->create([
            'name' => 'Alice Client',
            'title' => 'CEO',
            'company' => 'FarmCo',
            'content' => 'Great pumps!',
            'rating' => 5,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame('Alice Client', $testimonial->name);
        $this->assertSame('CEO', $testimonial->title);
        $this->assertSame('FarmCo', $testimonial->company);
        $this->assertSame('Great pumps!', $testimonial->content);
        $this->assertSame(5, $testimonial->rating);
        $this->assertTrue($testimonial->is_visible);
        $this->assertSame(1, $testimonial->sort_order);
    }

    public function test_it_casts_fields_correctly(): void
    {
        $testimonial = Testimonial::factory()->create([
            'is_visible' => true,
            'rating' => 4,
            'sort_order' => 10,
        ]);

        $this->assertIsBool($testimonial->is_visible);
        $this->assertIsInt($testimonial->rating);
        $this->assertIsInt($testimonial->sort_order);
    }

    public function test_scope_visible_returns_only_visible(): void
    {
        Testimonial::factory()->count(3)->create(['is_visible' => true]);
        Testimonial::factory()->hidden()->create();

        $visible = Testimonial::visible()->get();

        $this->assertCount(3, $visible);
    }

    public function test_it_can_have_video_url(): void
    {
        $testimonial = Testimonial::factory()->withVideo()->create();

        $this->assertNotNull($testimonial->video_url);
        $this->assertStringContainsString('youtube.com', $testimonial->video_url);
    }

    public function test_it_can_be_hidden(): void
    {
        $testimonial = Testimonial::factory()->hidden()->create();
        $this->assertFalse($testimonial->is_visible);
    }
}
