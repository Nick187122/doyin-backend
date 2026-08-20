<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_healthy(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'database',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'healthy',
                'database' => 'ok',
            ]);
    }

    public function test_health_endpoint_returns_valid_iso_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $timestamp = $response->json('timestamp');

        $this->assertNotNull($timestamp);
        $this->assertIsString($timestamp);
        // Assert it's ISO 8601 format
        $this->assertNotEmpty(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp));
    }
}
