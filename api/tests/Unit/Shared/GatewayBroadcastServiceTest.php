<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GatewayBroadcastServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_publishes_to_redis_when_pubsub_is_enabled(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', true);
        config()->set('services.gateway.realtime_http_fallback_enabled', false);

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_TRACE_ID' => 'trace-123']);
        $this->app->instance('request', $request);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        Redis::shouldReceive('connection')->once()->with('gateway')->andReturnSelf();
        Redis::shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(fn (string $payload): bool => str_contains($payload, '"event":"chat.message.new"')
                && str_contains($payload, '"tenant_id":"'.$user->tenant_id.'"')
                && str_contains($payload, '"trace_id":"trace-123"')));

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('chat.message.new', [
            'tenant_id' => (string) $user->tenant_id,
            'message' => ['id' => 'm1'],
        ]);

        $this->assertTrue(true);
    }

    public function test_falls_back_to_http_when_redis_publish_fails(): void
    {
        config()->set('services.gateway.url', 'http://gateway.test');
        config()->set('services.gateway.realtime_pubsub_enabled', true);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        Redis::shouldReceive('connection')->once()->with('gateway')->andReturnSelf();
        Redis::shouldReceive('publish')->once()->andThrow(new RuntimeException('redis down'));

        Http::fake([
            'http://gateway.test/internal/broadcast/event' => Http::response([], 200),
        ]);

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('chat.message.status', [
            'tenant_id' => (string) $user->tenant_id,
            'status' => 'sent',
        ], 'ticket:123');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://gateway.test/internal/broadcast/event'
            && ($request['event'] === 'chat.message.status')
            && in_array($request['room'], ['ticket:123', 'tenant:'.$request['data']['tenant_id']], true));
    }

    public function test_throws_when_no_auth_and_payload_has_no_tenant(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', false);

        $service = new GatewayBroadcastService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant isolation violation: No authenticated user and no tenant_id in payload');

        $service->broadcastEvent('chat.message.new', [
            'message' => ['id' => 'm1'],
        ]);
    }

    public function test_throws_when_payload_tenant_differs_from_authenticated_user(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', false);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $service = new GatewayBroadcastService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant isolation violation: Cannot broadcast to different tenant');

        $service->broadcastEvent('chat.message.new', [
            'tenant_id' => (string) \Illuminate\Support\Str::uuid(),
            'message' => ['id' => 'm1'],
        ]);
    }

    public function test_wrapper_methods_delegate_to_broadcast_event(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        config()->set('services.gateway.url', 'http://gateway.test');

        Http::fake([
            'http://gateway.test/internal/broadcast/event' => Http::response([], 200),
        ]);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $service = new GatewayBroadcastService;

        $service->broadcastMessageStatus([
            'message_id' => 'msg-1',
            'ticket_id' => 'ticket-1',
            'tenant_id' => (string) $user->tenant_id,
            'status' => 'delivered',
        ]);

        $service->broadcastNewMessage([
            'ticket_id' => 'ticket-1',
            'tenant_id' => (string) $user->tenant_id,
            'message' => ['id' => 'msg-2'],
        ]);

        Http::assertSent(fn ($request): bool => in_array($request['event'], ['chat.message.status', 'chat.message.new'], true));
    }

    public function test_uses_only_explicit_room_when_payload_has_empty_tenant_key(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        config()->set('services.gateway.url', 'http://gateway.test');

        Http::fake([
            'http://gateway.test/internal/broadcast/event' => Http::response([], 200),
        ]);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('custom.event', [
            'data' => ['foo' => 'bar'],
            'tenant_id' => '',
        ], 'room:explicit');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['event'] === 'custom.event' && $request['room'] === 'room:explicit');
    }

    public function test_uses_single_tenant_room_when_custom_room_matches_tenant_room(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        config()->set('services.gateway.url', 'http://gateway.test');

        Http::fake([
            'http://gateway.test/internal/broadcast/event' => Http::response([], 200),
        ]);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $tenantRoom = 'tenant:'.$user->tenant_id;

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('custom.event', [
            'tenant_id' => (string) $user->tenant_id,
        ], $tenantRoom);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['room'] === $tenantRoom);
    }

    public function test_skips_publish_without_tenant_and_returns_when_http_fallback_is_disabled(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', true);
        config()->set('services.gateway.realtime_http_fallback_enabled', false);

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        Log::shouldReceive('warning')
            ->once()
            ->with('[GatewayBroadcastService] Skip publish without tenant_id', \Mockery::type('array'));

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('custom.event', [
            'data' => ['foo' => 'bar'],
        ]);

        $this->assertTrue(true);
    }

    public function test_logs_warning_when_http_fallback_throws_exception(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        config()->set('services.gateway.url', 'http://gateway.test');

        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        Http::fake(static function (): never {
            throw new \RuntimeException('http-fallback-failure');
        });

        Log::shouldReceive('warning')
            ->once()
            ->with('[GatewayBroadcastService] Failed to broadcast event via HTTP fallback', \Mockery::on(fn (array $context): bool => $context['event'] === 'custom.event'
                && str_starts_with((string) $context['room'], 'tenant:')
                && str_contains((string) $context['error'], 'http-fallback-failure')));

        $service = new GatewayBroadcastService;
        $service->broadcastEvent('custom.event', [
            'tenant_id' => (string) $user->tenant_id,
        ]);

        $this->assertTrue(true);
    }
}
