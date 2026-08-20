<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'must_change_password' => true,
        ]);

        $this->assertSame('Admin User', $user->name);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertTrue($user->must_change_password);
    }

    public function test_it_hides_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
        $this->assertContains('active_device_token', $hidden);
        $this->assertContains('password_change_otp', $hidden);
    }

    public function test_it_hashes_password_automatically(): void
    {
        $user = User::factory()->create([
            'password' => 'plain-text-password',
        ]);

        $this->assertNotSame('plain-text-password', $user->password);
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    public function test_it_casts_must_change_password_as_boolean(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->assertIsBool($user->must_change_password);
        $this->assertTrue($user->must_change_password);
    }

    public function test_it_uses_has_api_tokens_trait(): void
    {
        $traits = class_uses(User::class);
        $this->assertContains('Laravel\Sanctum\HasApiTokens', $traits);
    }

    public function test_it_uses_notifiable_trait(): void
    {
        $traits = class_uses_recursive(User::class);
        $this->assertContains('Illuminate\Notifications\Notifiable', $traits);
    }
}
