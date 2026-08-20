<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_store_a_price(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 25000.00,
        ]);

        $this->assertSame(25000.00, (float) $product->price);
    }

    public function test_price_can_be_null(): void
    {
        $product = Product::factory()->create(['price' => null]);

        $this->assertNull($product->price);
    }

    public function test_price_casts_as_decimal(): void
    {
        $product = Product::factory()->create(['price' => 150000.50]);

        $this->assertIsNumeric($product->price);
        $this->assertEquals(150000.50, (float) $product->price);
    }

    public function test_price_defaults_to_null(): void
    {
        $product = Product::factory()->create();

        // Price should default to null since no explicit value was set
        // (the factory doesn't set a price by default — it's a nullable field)
        $this->assertNull($product->price);
    }

    public function test_price_accepts_zero(): void
    {
        $product = Product::factory()->create(['price' => 0]);

        $this->assertSame(0, (int) $product->price);
        $this->assertIsNumeric($product->price);
    }
}
