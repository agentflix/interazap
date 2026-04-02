<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the chat-specific rate limiter.
 *
 * Validates that chat endpoints use the 180/min limiter
 * instead of the default 60/min API limiter.
 */
class ChatRateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chat_endpoint_allows_more_than_60_requests_per_minute(): void
    {
        $user = AuthUser::factory()->create();

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $user->givePermissionTo($permView);

        $this->be($user, 'sanctum');

        // Send 65 requests — above the api limiter (60) but below chat limiter (180)
        for ($i = 0; $i < 65; $i++) {
            $response = $this->getJson('/api/chat/tickets?per_page=1');

            $this->assertNotEquals(
                429,
                $response->getStatusCode(),
                "Request #{$i} was rate limited at 429. The chat rate limiter should allow 180/min."
            );
        }
    }
}
