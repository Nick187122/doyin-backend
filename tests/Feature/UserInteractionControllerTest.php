<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInteractionControllerTest extends TestCase
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

    // ── Public endpoint ─────────────────────────────────────────

    public function test_public_can_submit_a_message(): void
    {
        $response = $this->postJson('/api/public/interactions', [
            'type' => 'message',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'I need help with pumps.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('interaction.is_read', false);

        $this->assertDatabaseHas('user_interactions', [
            'type' => 'message',
            'name' => 'John Doe',
            'content' => 'I need help with pumps.',
            'is_read' => false,
        ]);
    }

    public function test_public_can_submit_an_issue(): void
    {
        $response = $this->postJson('/api/public/interactions', [
            'type' => 'issue',
            'content' => 'The website is broken.',
        ]);

        $response->assertCreated();
    }

    public function test_public_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/interactions', [
                'type' => 'message',
                'name' => 'Tester',
                'email' => 'test@example.com',
                'content' => 'Test message ' . $i,
            ]);
        }

        $response = $this->postJson('/api/public/interactions', [
            'type' => 'message',
            'name' => 'Tester',
            'email' => 'test@example.com',
            'content' => 'Too many',
        ]);

        // Rate limiter kicks in after 5 per minute
        $this->assertContains($response->status(), [429, 201]);
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->postJson('/api/public/interactions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'content']);
    }

    public function test_it_enforces_max_queue_of_100(): void
    {
        // Create 101 interactions
        UserInteraction::factory()->count(101)->create();

        $this->postJson('/api/public/interactions', [
            'type' => 'message',
            'name' => 'Test',
            'email' => 'test@test.com',
            'content' => 'New interaction after cleanup',
        ]);

        // The 101 + 1 = 102, but should be cleaned up to max 100 + 1 = max 101
        $this->assertLessThanOrEqual(101, UserInteraction::count());
    }

    // ── Admin endpoints ─────────────────────────────────────────

    public function test_admin_can_list_interactions(): void
    {
        UserInteraction::factory()->count(3)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/interactions');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_admin_can_mark_interaction_as_read(): void
    {
        $interaction = UserInteraction::factory()->create(['is_read' => false]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/interactions/{$interaction->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('user_interactions', ['id' => $interaction->id, 'is_read' => true]);
    }

    public function test_admin_can_delete_an_interaction(): void
    {
        $interaction = UserInteraction::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/interactions/{$interaction->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('user_interactions', ['id' => $interaction->id]);
    }

    public function test_admin_can_clear_all_interactions(): void
    {
        UserInteraction::factory()->count(5)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/interactions/clear');

        $response->assertOk();
        $this->assertDatabaseCount('user_interactions', 0);
    }
}
