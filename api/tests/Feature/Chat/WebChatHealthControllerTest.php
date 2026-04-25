<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class WebChatHealthControllerTest extends TestCase
{
    public function test_returns_ok_status_when_redis_is_available(): void
    {
        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturnSelf();
        Redis::shouldReceive('ping')
            ->andReturn(true);

        $response = $this->getJson('/api/webchat/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'redis', 'timestamp'])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('redis', true);
    }

    public function test_returns_degraded_status_when_redis_is_unavailable(): void
    {
        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andThrow(new \Exception('Redis connection failed'));

        $response = $this->getJson('/api/webchat/health');

        $response->assertStatus(503)
            ->assertJsonStructure(['status', 'redis', 'timestamp'])
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('redis', false);
    }
}
