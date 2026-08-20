<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private string $deviceToken = 'device-abc';

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
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'X-Device-Token' => $this->deviceToken,
        ];
    }

    public function test_public_index_returns_all_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/public/categories');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_admin_index_returns_all_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/categories');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_it_stores_a_category(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/categories', [
                'name' => 'Submersible Pumps',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Submersible Pumps']);

        $this->assertDatabaseHas('categories', ['name' => 'Submersible Pumps']);
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Category']);

        $this->assertDatabaseHas('categories', ['name' => 'Updated Category']);
    }

    public function test_it_deletes_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_it_validates_required_fields_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
