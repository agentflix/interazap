<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Chat\Services;

use Domain\Chat\Services\ChatActivityBroadcastService;
use Tests\TestCase;

/**
 * @covers \Domain\Chat\Services\ChatActivityBroadcastService
 */
final class ChatActivityBroadcastServiceTest extends TestCase
{
    public function test_valid_subevent_types_constant_is_complete(): void
    {
        $expectedTypes = [
            'msg.received',
            'msg.status',
            'msg.reaction',
            'msg.edit',
            'msg.delete',
            'ai.processing.started',
            'ai.processing.completed',
            'ai.processing.failed',
            'ai.processing.rejected',
            'chat.list.updated',
            'contact.updated',
            'deal.updated',
            'negotiation.status.changed',
            'ticket.new',
            'ticket.updated',
        ];

        $this->assertEquals($expectedTypes, ChatActivityBroadcastService::VALID_SUBEVENT_TYPES);
    }

    public function test_valid_subevent_types_count(): void
    {
        $this->assertCount(15, ChatActivityBroadcastService::VALID_SUBEVENT_TYPES);
    }

    public function test_service_can_be_instantiated(): void
    {
        $service = app(ChatActivityBroadcastService::class);

        $this->assertInstanceOf(ChatActivityBroadcastService::class, $service);
    }

    public function test_emit_with_empty_subevents_returns_early(): void
    {
        // This test verifies the early return doesn't throw exceptions
        $service = app(ChatActivityBroadcastService::class);

        // Should not throw - empty subevents should return early
        $service->emit('ticket-123', []);

        $this->assertTrue(true);
    }

    public function test_emit_with_invalid_subevents_returns_early(): void
    {
        $service = app(ChatActivityBroadcastService::class);

        // Should not throw - all invalid subevents should return early
        $service->emit('ticket-123', [
            ['type' => 'invalid.type', 'data' => []],
            ['type' => 'another.invalid', 'data' => []],
        ]);

        $this->assertTrue(true);
    }

    public function test_subevent_types_contains_message_events(): void
    {
        $types = ChatActivityBroadcastService::VALID_SUBEVENT_TYPES;

        $this->assertContains('msg.received', $types);
        $this->assertContains('msg.status', $types);
        $this->assertContains('msg.reaction', $types);
        $this->assertContains('msg.edit', $types);
        $this->assertContains('msg.delete', $types);
    }

    public function test_subevent_types_contains_entity_events(): void
    {
        $types = ChatActivityBroadcastService::VALID_SUBEVENT_TYPES;

        $this->assertContains('contact.updated', $types);
        $this->assertContains('deal.updated', $types);
        $this->assertContains('ticket.new', $types);
        $this->assertContains('ticket.updated', $types);
    }

    public function test_subevent_types_contains_ai_processing_events(): void
    {
        $types = ChatActivityBroadcastService::VALID_SUBEVENT_TYPES;

        $this->assertContains('ai.processing.started', $types);
        $this->assertContains('ai.processing.completed', $types);
        $this->assertContains('ai.processing.failed', $types);
        $this->assertContains('ai.processing.rejected', $types);
    }

    public function test_subevent_types_contains_list_event(): void
    {
        $types = ChatActivityBroadcastService::VALID_SUBEVENT_TYPES;

        $this->assertContains('chat.list.updated', $types);
    }

    public function test_emit_strips_heavy_fields_and_emits_compact_payload(): void
    {
        $service = app(ChatActivityBroadcastService::class);

        $subevents = [
            [
                'type' => 'msg.received',
                'data' => [
                    'ticket_id' => 'ticket-1',
                    'tenant_id' => 'tenant-1',
                    'message' => [
                        'id' => 'm-1',
                        'content' => 'hello',
                        'metadata' => [
                            'raw' => ['foo' => 'bar'],
                            'JPEGThumbnail' => 'thumb',
                            'base64' => 'blob',
                            'profilePicThumbObj' => ['x' => 'y'],
                        ],
                    ],
                ],
            ],
        ];

        $method = new \ReflectionMethod($service, 'compactSubevents');

        /** @var array<int, array{type: string, data: array<string, mixed>}> $compact */
        $compact = $method->invoke($service, $subevents);

        $metadata = $compact[0]['data']['message']['metadata'];
        $this->assertArrayNotHasKey('raw', $metadata);
        $this->assertArrayNotHasKey('JPEGThumbnail', $metadata);
        $this->assertArrayNotHasKey('base64', $metadata);
        $this->assertArrayNotHasKey('profilePicThumbObj', $metadata);
    }

    public function test_emit_reduces_payload_to_delta_when_exceeds_max_size(): void
    {
        $service = app(ChatActivityBroadcastService::class);

        $payload = [
            'event' => 'chat.activity',
            'chatId' => 'ticket-2',
            'ticketId' => 'ticket-2',
            'tenantId' => 'tenant-2',
            'timestamp' => now()->toIso8601String(),
            'subevents' => [
                [
                    'type' => 'msg.received',
                    'data' => [
                        'id' => 'evt-1',
                        'ticket_id' => 'ticket-2',
                        'tenant_id' => 'tenant-2',
                        'status' => 'received',
                        'message' => [
                            'id' => 'm-2',
                            'content' => str_repeat('X', 12000),
                            'type' => 'text',
                            'direction' => 'incoming',
                            'status' => 'received',
                            'created_at' => now()->toIso8601String(),
                            'metadata' => [
                                'raw' => str_repeat('Y', 12000),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $method = new \ReflectionMethod($service, 'ensureMaxPayloadSize');

        /** @var array<string, mixed> $compactedPayload */
        $compactedPayload = $method->invoke($service, $payload);

        $subeventData = $compactedPayload['subevents'][0]['data'];
        $this->assertArrayHasKey('id', $subeventData);
        $this->assertArrayHasKey('ticket_id', $subeventData);
        $this->assertArrayHasKey('tenant_id', $subeventData);
        $this->assertArrayHasKey('message', $subeventData);
        $this->assertArrayNotHasKey('metadata', $subeventData['message']);
    }
}
