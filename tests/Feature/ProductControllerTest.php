<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private string $deviceToken = 'device-abc';
    private Category $pumpCategory;
    private Category $accessoryCategory;

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

        $this->pumpCategory = Category::factory()->create(['is_pump' => true, 'has_ideal_power' => true]);
        $this->accessoryCategory = Category::factory()->accessory()->create();
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'X-Device-Token' => $this->deviceToken,
        ];
    }

    // ── Admin CRUD ──────────────────────────────────────────────

    public function test_admin_index_returns_all_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_it_stores_a_product(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->pumpCategory->id,
                'name' => 'New Pump 5000',
                'description' => 'A powerful pump',
                'max_flow_rate' => '10 m³/h',
                'max_height' => '50 m',
                'recommended_depth' => '20 m',
                'ideal_power' => '3 kW',
                'in_stock' => true,
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Pump 5000']);

        $this->assertDatabaseHas('products', ['name' => 'New Pump 5000']);
    }

    public function test_it_updates_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/products/{$product->id}", [
                'category_id' => $this->pumpCategory->id,
                'name' => 'Updated Pump',
                'description' => 'Updated description',
                'in_stock' => false,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Pump']);

        $this->assertDatabaseHas('products', ['name' => 'Updated Pump', 'in_stock' => false]);
    }

    public function test_it_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_it_requires_name_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->pumpCategory->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    // ── Duplicate Detection ─────────────────────────────────────

    public function test_it_rejects_duplicate_products(): void
    {
        Product::factory()->create([
            'category_id' => $this->pumpCategory->id,
            'name' => 'Unique Pump',
            'description' => 'Same description',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->pumpCategory->id,
                'name' => 'Unique Pump',
                'description' => 'Same description',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'similar_product']);
    }

    // ── Category-specific field normalization ───────────────────

    public function test_it_clears_pump_fields_for_accessories(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $this->accessoryCategory->id,
                'name' => 'Pipe Fitting',
                'max_flow_rate' => 'should be null',
                'max_height' => 'should be null',
            ]);

        $response->assertCreated();

        $product = Product::where('name', 'Pipe Fitting')->first();
        $this->assertNull($product->max_flow_rate);
        $this->assertNull($product->max_height);
        $this->assertNull($product->recommended_depth);
        $this->assertNull($product->ideal_power);
    }

    public function test_it_clears_ideal_power_when_category_has_no_ideal_power(): void
    {
        $categoryWithoutIdealPower = Category::factory()->create([
            'is_pump' => true,
            'has_ideal_power' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [
                'category_id' => $categoryWithoutIdealPower->id,
                'name' => 'Basic Pump',
                'ideal_power' => '5 kW',
            ]);

        $response->assertCreated();

        $product = Product::where('name', 'Basic Pump')->first();
        $this->assertNull($product->ideal_power);
    }

    // ── Public Endpoints ────────────────────────────────────────

    public function test_public_index_returns_all_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/public/products');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_public_show_returns_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/public/products/{$product->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => $product->name]);
    }

    public function test_public_show_returns_404_for_missing_product(): void
    {
        $response = $this->getJson('/api/public/products/99999');

        $response->assertStatus(404);
    }

    public function test_it_increments_view_count(): void
    {
        $product = Product::factory()->create(['views_count' => 0]);

        $response = $this->postJson("/api/public/products/{$product->id}/view");

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'views_count' => 1]);
    }

    public function test_public_sitemap_index_returns_product_ids(): void
    {
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/public/sitemap/products');

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertArrayHasKey('id', $response->json()[0]);
        $this->assertArrayHasKey('updated_at', $response->json()[0]);
    }
}
