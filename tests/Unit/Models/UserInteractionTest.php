<?php

namespace Tests\Unit\Models;

use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $interaction = UserInteraction::factory()->create([
            'type' => 'message',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'Hello, I need help.',
            'is_read' => false,
        ]);

        $this->assertSame('message', $interaction->type);
        $this->assertSame('John Doe', $interaction->name);
        $this->assertSame('john@example.com', $interaction->email);
        $this->assertSame('Hello, I need help.', $interaction->content);
        $this->assertFalse($interaction->is_read);
    }

    public function test_it_casts_is_read_as_boolean(): void
    {
        $interaction = UserInteraction::factory()->read()->create();
        $this->assertIsBool($interaction->is_read);
        $this->assertTrue($interaction->is_read);
    }

    public function test_it_can_be_an_issue_type(): void
    {
        $interaction = UserInteraction::factory()->issue()->create();

        $this->assertSame('issue', $interaction->type);
        $this->assertNull($interaction->name);
        $this->assertNull($interaction->email);
    }

    public function test_it_defaults_to_unread(): void
    {
        $interaction = UserInteraction::factory()->create();
        $this->assertFalse($interaction->is_read);
    }
}
