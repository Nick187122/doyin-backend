<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $category = Category::factory()->create([
            'name' => 'Submersible Pumps',
            'is_pump' => true,
            'has_ideal_power' => true,
        ]);

        $this->assertSame('Submersible Pumps', $category->name);
        $this->assertTrue($category->is_pump);
        $this->assertTrue($category->has_ideal_power);
    }

    public function test_it_casts_boolean_fields(): void
    {
        $category = Category::factory()->create([
            'is_pump' => true,
            'has_ideal_power' => false,
        ]);

        $this->assertIsBool($category->is_pump);
        $this->assertIsBool($category->has_ideal_power);
        $this->assertTrue($category->is_pump);
        $this->assertFalse($category->has_ideal_power);
    }

    public function test_it_has_many_products(): void
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->assertCount(3, $category->fresh()->products);
        $this->assertInstanceOf(Product::class, $category->fresh()->products->first());
    }

    public function test_it_can_be_an_accessory_category(): void
    {
        $category = Category::factory()->accessory()->create();

        $this->assertFalse($category->is_pump);
        $this->assertFalse($category->has_ideal_power);
    }

    public function test_it_can_have_ideal_power_field(): void
    {
        $category = Category::factory()->pumpWithIdealPower()->create();

        $this->assertTrue($category->is_pump);
        $this->assertTrue($category->has_ideal_power);
    }
}
