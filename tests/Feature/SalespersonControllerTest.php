<?php

namespace Tests\Feature;

use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalespersonControllerTest extends TestCase
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

    public function test_public_index_returns_only_active_salespersons(): void
    {
        Salesperson::factory()->count(2)->create(['is_active' => true]);
        Salesperson::factory()->inactive()->create();

        $response = $this->getJson('/api/public/salespersons');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_index_returns_all_salespersons(): void
    {
        Salesperson::factory()->count(2)->create(['is_active' => true]);
        Salesperson::factory()->inactive()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/salespersons');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_it_stores_a_salesperson(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/salespersons', [
                'name' => 'Jane Sales',
                'phone_number' => '+254712345678',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Jane Sales']);

        $this->assertDatabaseHas('salespersons', ['name' => 'Jane Sales']);
    }

    public function test_it_updates_a_salesperson(): void
    {
        $salesperson = Salesperson::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/salespersons/{$salesperson->id}", [
                'name' => 'Updated Name',
                'phone_number' => '+254700000000',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('salespersons', ['id' => $salesperson->id, 'name' => 'Updated Name', 'is_active' => false]);
    }

    public function test_it_deletes_a_salesperson(): void
    {
        $salesperson = Salesperson::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/salespersons/{$salesperson->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('salespersons', ['id' => $salesperson->id]);
    }

    public function test_it_requires_name_and_phone_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/salespersons', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone_number']);
    }
}
