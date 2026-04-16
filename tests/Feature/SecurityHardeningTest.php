<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminPasswordChangeOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_interactions_cannot_force_messages_to_read(): void
    {
        $response = $this->postJson('/api/public/interactions', [
            'type' => 'message',
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'content' => 'Hello there',
            'is_read' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('interaction.is_read', false);

        $this->assertDatabaseHas('user_interactions', [
            'email' => 'tester@example.com',
            'is_read' => false,
        ]);
    }

    public function test_protected_routes_require_the_active_device_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'device_token' => 'device-1',
        ]);

        $token = $login->json('token');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/interactions')->assertStatus(401);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-1',
        ])->getJson('/api/interactions')->assertOk();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_token' => 'device-1',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_token' => 'device-1',
        ])->assertStatus(429);
    }

    public function test_admin_settings_reject_unapproved_video_hosts(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'device_token' => 'device-1',
        ]);

        $token = $login->json('token');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-1',
        ])->postJson('/api/settings', [
            'settings' => [
                'about_video_url' => 'https://evil.example/video',
            ],
        ])->assertStatus(422);
    }

    public function test_password_change_requires_a_valid_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => bcrypt('Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'device_token' => 'device-1',
        ]);

        $token = $login->json('token');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-1',
        ])->postJson('/api/change-password', [
            'current_password' => 'Secret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
            'otp' => '123456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('otp');
    }

    public function test_password_change_otp_can_be_requested_and_used_to_rotate_the_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => bcrypt('Secret123!'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'device_token' => 'device-1',
        ]);

        $token = $login->json('token');

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-1',
        ])->postJson('/api/change-password/request-otp', [
            'current_password' => 'Secret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertOk();

        $otp = null;

        Notification::assertSentTo(
            [$user],
            AdminPasswordChangeOtpNotification::class,
            function (AdminPasswordChangeOtpNotification $notification) use (&$otp) {
                $otp = $notification->otp;

                return true;
            }
        );

        $change = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-1',
        ])->postJson('/api/change-password', [
            'current_password' => 'Secret123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
            'otp' => $otp,
        ]);

        $change->assertOk()->assertJsonStructure(['message', 'token']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'must_change_password' => false,
            'password_change_otp' => null,
            'password_change_otp_expires_at' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'NewSecret123!',
            'device_token' => 'device-2',
        ])->assertOk();
    }
}
