<?php

namespace Tests\Feature;

use App\Models\HeroImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroImageControllerTest extends TestCase
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

    public function test_public_index_returns_only_active_hero_images(): void
    {
        HeroImage::factory()->count(2)->create(['is_active' => true]);
        HeroImage::factory()->inactive()->create();

        $response = $this->getJson('/api/public/hero-images');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_index_returns_all_hero_images(): void
    {
        HeroImage::factory()->count(2)->create(['is_active' => true]);
        HeroImage::factory()->inactive()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/hero-images');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_it_requires_image_file_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/hero-images', [
                'title' => 'Main Banner',
                'is_active' => true,
                'order' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_it_updates_hero_image_without_image(): void
    {
        $heroImage = HeroImage::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/hero-images/{$heroImage->id}", [
                'title' => 'Updated Title',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('hero_images', [
            'id' => $heroImage->id,
            'title' => 'Updated Title',
            'is_active' => false,
        ]);
    }

    public function test_it_updates_a_hero_image(): void
    {
        $heroImage = HeroImage::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/hero-images/{$heroImage->id}", [
                'title' => 'Updated Banner',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Banner']);

        $this->assertDatabaseHas('hero_images', ['id' => $heroImage->id, 'title' => 'Updated Banner', 'is_active' => false]);
    }

    public function test_it_deletes_a_hero_image(): void
    {
        $heroImage = HeroImage::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/hero-images/{$heroImage->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('hero_images', ['id' => $heroImage->id]);
    }

    public function test_it_requires_image_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/hero-images', [
                'title' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }
}
