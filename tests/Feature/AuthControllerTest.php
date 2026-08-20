<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminPasswordChangeOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $password = 'Secret123!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => bcrypt($this->password),
        ]);
    }

    public function test_it_logs_in_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'must_change_password',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_it_requires_device_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('device_token');
    }

    public function test_it_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
            'device_token' => 'device-abc',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_it_returns_me_with_valid_token(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-abc',
        ])->getJson('/api/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'must_change_password',
            ]);
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_it_logs_out(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-abc',
        ])->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'active_device_token' => null,
        ]);

        $login2 = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $login2->assertOk();
    }

    public function test_it_updates_active_device_token_on_login(): void
    {
        $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-xyz',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'active_device_token' => 'device-xyz',
        ]);
    }

    public function test_it_requests_password_change_otp(): void
    {
        Notification::fake();

        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-abc',
        ])->postJson('/api/change-password/request-otp', [
            'current_password' => $this->password,
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'expires_in']);

        // The notification is routed via mail route, not directly to the user,
        // so we check that a notification was sent (1 total)
        Notification::assertCount(1);

        // Verify OTP data was stored in the database
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
        ]);

        $freshUser = $this->user->fresh();
        $this->assertNotNull($freshUser->password_change_otp);
        $this->assertNotNull($freshUser->password_change_otp_expires_at);
    }

    public function test_it_rejects_password_change_without_otp_request(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-abc',
        ])->postJson('/api/change-password', [
            'current_password' => $this->password,
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('otp');
    }

    public function test_it_requires_current_password_for_otp_request(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_token' => 'device-abc',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Device-Token' => 'device-abc',
        ])->postJson('/api/change-password/request-otp', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }
}
