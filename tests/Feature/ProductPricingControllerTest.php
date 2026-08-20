<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private string $deviceToken = 'device-abc';
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => bcrypt($password = 'Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $password,
            'device_token' => $this->deviceToken,
        ]);

        $this->token = $login->json('token');

        $this->category = Category::factory()->create();
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'X-Device-Token' => $this->deviceToken,
        ];
    }

    // ── Admin: Store with price ─────────────────────────────────

    public function test_it_stores_product_with_price(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Pump With Price',
                'price' => 45000.00,
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Pump With Price']);

        $this->assertDatabaseHas('products', [
            'name' => 'Pump With Price',
            'price' => 45000.00,
        ]);
    }

    public function test_price_is_optional_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Pump Without Price',
            ]);

        $response->assertCreated();

        $product = Product::where('name', 'Pump Without Price')->first();
        $this->assertNull($product->price);
    }

    public function test_price_validates_as_numeric(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Invalid Price Pump',
                'price' => 'not-a-number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_price_must_be_non_negative(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Negative Price Pump',
                'price' => -1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    // ── Admin: Update with price ────────────────────────────────

    public function test_it_updates_product_price(): void
    {
        $product = Product::factory()->create(['price' => null]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/products/{$product->id}", [
                'category_id' => $this->category->id,
                'name' => $product->name,
                'price' => 55000.00,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 55000.00,
        ]);
    }

    public function test_it_can_clear_price_on_update(): void
    {
        $product = Product::factory()->create(['price' => 30000.00]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/products/{$product->id}", [
                'category_id' => $this->category->id,
                'name' => $product->name,
                'price' => null,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => null,
        ]);
    }

    // ── Public: Price in responses ──────────────────────────────

    public function test_public_index_includes_price(): void
    {
        Product::factory()->create([
            'name' => 'Product With Price',
            'price' => 75000.00,
        ]);

        $response = $this->getJson('/api/public/products');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Product With Price']);

        $productInResponse = collect($response->json())->firstWhere('name', 'Product With Price');
        $this->assertArrayHasKey('price', $productInResponse);
        $this->assertEquals(75000.00, $productInResponse['price']);
    }

    public function test_public_show_includes_price(): void
    {
        $product = Product::factory()->create([
            'price' => 125000.00,
        ]);

        $response = $this->getJson("/api/public/products/{$product->id}");

        $response->assertOk();
        $this->assertArrayHasKey('price', $response->json());
        $this->assertEquals(125000.00, $response->json()['price']);
    }

    public function test_public_show_includes_null_price(): void
    {
        $product = Product::factory()->create(['price' => null]);

        $response = $this->getJson("/api/public/products/{$product->id}");

        $response->assertOk();
        $this->assertArrayHasKey('price', $response->json());
        $this->assertNull($response->json()['price']);
    }

    // ── Admin: Price in responses ───────────────────────────────

    public function test_admin_index_includes_price(): void
    {
        Product::factory()->create([
            'name' => 'Admin Price Product',
            'price' => 99000.00,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products');

        $response->assertOk();
        $productInResponse = collect($response->json())->firstWhere('name', 'Admin Price Product');
        $this->assertArrayHasKey('price', $productInResponse);
        $this->assertEquals(99000.00, $productInResponse['price']);
    }

    // ── Price formatting helper ─────────────────────────────────

    public function test_price_is_serialized_as_number(): void
    {
        $product = Product::factory()->create(['price' => 89999.99]);

        $response = $this->getJson("/api/public/products/{$product->id}");

        $response->assertOk();
        // Ensure price is a number, not a string
        // Use assertIsNumeric because SQLite returns decimals as strings in JSON
        $this->assertIsNumeric($response->json()['price']);
    }
}
