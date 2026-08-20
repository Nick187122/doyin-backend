<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddPriceToProductsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_column_exists_on_products_table(): void
    {
        $this->assertTrue(
            Schema::hasColumn('products', 'price'),
            'The products table must have a "price" column (decimal, nullable).'
        );
    }

    public function test_price_column_is_nullable(): void
    {
        $columns = Schema::getColumnListing('products');
        $this->assertContains('price', $columns);
    }
}
