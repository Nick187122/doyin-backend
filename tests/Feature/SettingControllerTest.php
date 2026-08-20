<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerTest extends TestCase
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

    public function test_public_index_returns_settings_as_object(): void
    {
        Setting::create(['key' => 'contact_phone', 'value' => '+254700000000']);
        Setting::create(['key' => 'contact_email', 'value' => 'test@example.com']);

        $response = $this->getJson('/api/public/settings');

        $response->assertOk();
        $this->assertEquals('+254700000000', $response->json('contact_phone'));
        $this->assertEquals('test@example.com', $response->json('contact_email'));
    }

    public function test_it_updates_settings(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings', [
                'settings' => [
                    'contact_phone' => '+254711111111',
                    'contact_email' => 'new@example.com',
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('settings', ['key' => 'contact_phone', 'value' => '+254711111111']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'new@example.com']);
    }

    public function test_it_rejects_unapproved_video_hosts(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings', [
                'settings' => [
                    'about_video_url' => 'https://evil.example.com/video',
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_it_accepts_youtube_video_urls(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings', [
                'settings' => [
                    'about_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ],
            ]);

        $response->assertOk();
    }

    public function test_it_accepts_vimeo_video_urls(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings', [
                'settings' => [
                    'about_video_url' => 'https://vimeo.com/123456789',
                ],
            ]);

        $response->assertOk();
    }

    public function test_it_updates_existing_settings_instead_of_duplicating(): void
    {
        Setting::create(['key' => 'contact_phone', 'value' => '+254700000000']);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/settings', [
                'settings' => ['contact_phone' => '+254711111111'],
            ]);

        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('settings', ['key' => 'contact_phone', 'value' => '+254711111111']);
    }
}
