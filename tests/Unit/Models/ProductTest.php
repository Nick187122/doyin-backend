<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Pump 2000',
            'description' => 'A high-performance pump',
            'in_stock' => true,
        ]);

        $this->assertSame('Test Pump 2000', $product->name);
        $this->assertSame('A high-performance pump', $product->description);
        $this->assertTrue($product->in_stock);
    }

    public function test_it_belongs_to_a_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_it_casts_in_stock_as_boolean(): void
    {
        $product = Product::factory()->create(['in_stock' => true]);
        $this->assertIsBool($product->in_stock);
        $this->assertTrue($product->in_stock);

        $product = Product::factory()->outOfStock()->create();
        $this->assertIsBool($product->in_stock);
        $this->assertFalse($product->in_stock);
    }

    public function test_it_casts_performance_curves_as_array(): void
    {
        $product = Product::factory()->withPerformanceCurves()->create();

        $this->assertIsArray($product->performance_curves);
        $this->assertCount(4, $product->performance_curves);
    }

    public function test_performance_curves_can_be_null(): void
    {
        $product = Product::factory()->create(['performance_curves' => null]);

        $this->assertNull($product->performance_curves);
    }

    public function test_it_can_be_out_of_stock(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->assertFalse($product->in_stock);
    }
}
