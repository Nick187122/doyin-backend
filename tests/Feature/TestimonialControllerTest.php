<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialControllerTest extends TestCase
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

    public function test_public_index_returns_only_visible_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['is_visible' => true]);
        Testimonial::factory()->hidden()->create();

        $response = $this->getJson('/api/public/testimonials');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_index_returns_all_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['is_visible' => true]);
        Testimonial::factory()->hidden()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/testimonials');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_it_stores_a_testimonial(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/testimonials', [
                'name' => 'Happy Client',
                'content' => 'Great pumps! Highly recommend.',
                'rating' => 5,
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Happy Client']);

        $this->assertDatabaseHas('testimonials', ['name' => 'Happy Client']);
    }

    public function test_it_updates_a_testimonial(): void
    {
        $testimonial = Testimonial::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/testimonials/{$testimonial->id}", [
                'name' => 'Updated Name',
                'content' => 'Updated content',
                'is_visible' => false,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('testimonials', ['id' => $testimonial->id, 'name' => 'Updated Name', 'is_visible' => false]);
    }

    public function test_it_deletes_a_testimonial(): void
    {
        $testimonial = Testimonial::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/testimonials/{$testimonial->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('testimonials', ['id' => $testimonial->id]);
    }

    public function test_it_requires_name_and_content_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/testimonials', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'content']);
    }

    public function test_it_validates_rating_range(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/testimonials', [
                'name' => 'Test',
                'content' => 'Test content',
                'rating' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }
}
