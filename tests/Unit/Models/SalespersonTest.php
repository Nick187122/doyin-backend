<?php

namespace Tests\Unit\Models;

use App\Models\Salesperson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalespersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $salesperson = Salesperson::factory()->create([
            'name' => 'Jane Sales',
            'phone_number' => '+254712345678',
            'is_active' => true,
        ]);

        $this->assertSame('Jane Sales', $salesperson->name);
        $this->assertSame('+254712345678', $salesperson->phone_number);
        $this->assertTrue($salesperson->is_active);
    }

    public function test_it_casts_is_active_as_boolean(): void
    {
        $salesperson = Salesperson::factory()->create(['is_active' => false]);
        $this->assertIsBool($salesperson->is_active);
        $this->assertFalse($salesperson->is_active);
    }

    public function test_it_uses_correct_table(): void
    {
        $salesperson = Salesperson::factory()->create();
        $this->assertSame('salespersons', $salesperson->getTable());
    }

    public function test_scope_active_returns_only_active(): void
    {
        Salesperson::factory()->count(2)->create(['is_active' => true]);
        Salesperson::factory()->inactive()->create();

        $active = Salesperson::active()->get();

        $this->assertCount(2, $active);
    }
}
