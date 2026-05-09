<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use Domain\Chat\Services\ChatBroadcastService;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ChatBroadcastServiceTest extends TestCase
{
    private ChatBroadcastService $service;

    private string $defaultTenantId = 'tenant-2';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gateway.realtime_pubsub_enabled' => false,
            'services.gateway.realtime_http_fallback_enabled' => true,
        ]);
        Http::fake();

        // Mock authenticated user with tenant usando Auth facade
        $this->setAuthenticatedTenant($this->defaultTenantId);

        $gatewayBroadcast = new GatewayBroadcastService;
        $this->service = new ChatBroadcastService($gatewayBroadcast);
    }

    private function setAuthenticatedTenant(string $tenantId): void
    {
        Auth::shouldReceive('user')
            ->andReturn((object) ['tenant_id' => $tenantId]);
    }

    public function test_emit_message_status_sanitizes_payload(): void
    {
        $payload = [
            'message_id' => 'msg-1',
            'ticket_id' => 'ticket-1',
            'tenant_id' => 'tenant-2', // Match authenticated tenant
            'status' => 'sent',
            'raw' => 'binary',
        ];

        $this->service->emitMessageStatus($payload);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.message.status'
                && ! array_key_exists('raw', $data['data'] ?? [])
                && ($data['data']['ticket_id'] ?? null) === 'ticket-1';
        });
    }

    public function test_emit_with_room_broadcasts_event(): void
    {
        $this->service->emit('custom.event', [
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message' => ['content' => 'test'],
        ], 'ticket:ticket-2');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'custom.event'
                && ($data['room'] ?? null) === 'ticket:ticket-2'
                && isset($data['data']);
        });
    }

    public function test_emit_new_message_broadcasts(): void
    {
        $this->service->emitNewMessage([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message' => ['content' => 'hello'],
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.message.new'
                && ($data['data']['ticket_id'] ?? null) === 'ticket-2'
                && isset($data['data']['message']);
        });
    }

    public function test_emit_uses_pubsub_when_enabled(): void
    {
        config([
            'services.gateway.realtime_pubsub_enabled' => true,
            'services.gateway.realtime_http_fallback_enabled' => true,
        ]);

        Redis::shouldReceive('connection')->once()->with('gateway')->andReturnSelf();
        Redis::shouldReceive('publish')
            ->once()
            ->with('ws.events', \Mockery::on(
                fn (string $payload): bool => str_contains($payload, '"event":"custom.event"')
                    && str_contains($payload, '"rooms":["ticket:ticket-2","tenant:tenant-2"]')
                    && str_contains($payload, '"version":"v1"')
            ))
            ->andReturn(1);

        $gatewayBroadcast = new GatewayBroadcastService;
        $service = new ChatBroadcastService($gatewayBroadcast);

        $service->emit('custom.event', [
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message' => ['content' => 'test'],
        ], 'ticket:ticket-2');
    }

    public function test_emit_new_ticket_broadcasts(): void
    {
        $this->service->emitNewTicket([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'ticket' => ['status' => 'open'],
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.ticket.new'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && isset($data['data']);
        });
    }

    public function test_emit_ticket_updated_broadcasts(): void
    {
        $this->service->emitTicketUpdated([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'ticket' => ['status' => 'pending'],
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.ticket.updated'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && isset($data['data']);
        });
    }

    public function test_emit_message_reaction_broadcasts(): void
    {
        $this->service->emitMessageReaction([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message_id' => 'message-1',
            'emoji' => '👍',
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.message.reaction'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && isset($data['data']);
        });
    }

    public function test_emit_message_edit_broadcasts(): void
    {
        $this->service->emitMessageEdit([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message_id' => 'message-1',
            'content' => 'edited',
            'is_edited' => true,
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.message.edit'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && isset($data['data']);
        });
    }

    public function test_emit_message_delete_broadcasts(): void
    {
        $this->service->emitMessageDelete([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message_id' => 'message-1',
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.message.delete'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && isset($data['data']);
        });
    }

    public function test_sanitizes_large_payloads(): void
    {
        $largePayload = str_repeat('x', 10000);

        $this->service->emit('custom.event', [
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message' => [
                'metadata' => [
                    'content' => [
                        'base64' => 'data',
                        'url' => 'https://files.test/file.png',
                    ],
                ],
            ],
            'metadata' => [
                'content' => [
                    'raw' => $largePayload,
                    'url' => 'https://files.test/large.txt',
                ],
            ],
        ], 'ticket:ticket-2');

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $payload = $data['data'] ?? [];

            // 'raw' and 'base64' keys should be stripped by sanitizePayload
            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'custom.event'
                && ($data['room'] ?? null) === 'ticket:ticket-2'
                && ! isset($payload['metadata']['content']['raw'])
                && ! isset($payload['message']['metadata']['content']['base64']);
        });
    }

    public function test_validates_tenant_isolation(): void
    {
        // Bypass broadcasts - não precisamos do HTTP fake aqui
        // Vamos testar diretamente validateTenantIsolation via reflection

        $gatewayService = new GatewayBroadcastService;
        $reflection = new \ReflectionClass($gatewayService);
        $method = $reflection->getMethod('validateTenantIsolation');

        // Simular tenant autenticado tenant-2 (do setUp)
        // e payload de outro tenant
        $payload = [
            'tenant_id' => 'tenant-X', // Diferente!
            'message' => ['content' => 'test'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant isolation violation');

        $method->invoke($gatewayService, $payload);
    }

    public function test_includes_api_key_header_when_configured(): void
    {
        config(['services.gateway.api_key' => 'test-api-key']);

        $this->service->emitNewMessage([
            'tenant_id' => 'tenant-2',
            'ticket_id' => 'ticket-2',
            'message' => ['content' => 'hello'],
        ]);

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-API-Key')
            && $request->header('X-API-Key')[0] === 'test-api-key');
    }

    public function test_emit_typing_calls_broadcast_event_with_correct_params(): void
    {
        $this->service->emitTyping('tenant-2', 'ticket-2', true, 'composing');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.typing'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && ($data['data']['tenant_id'] ?? null) === 'tenant-2'
                && ($data['data']['ticket_id'] ?? null) === 'ticket-2'
                && ($data['data']['is_typing'] ?? null) === true
                && ($data['data']['presence'] ?? null) === 'composing';
        });
    }

    public function test_emit_typing_with_null_presence(): void
    {
        $this->service->emitTyping('tenant-2', 'ticket-2', false);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), '/internal/broadcast/event')
                && ($data['event'] ?? null) === 'chat.typing'
                && ($data['room'] ?? null) === 'tenant:tenant-2'
                && ($data['data']['is_typing'] ?? null) === false
                && array_key_exists('presence', $data['data'])
                && $data['data']['presence'] === null;
        });
    }
}
