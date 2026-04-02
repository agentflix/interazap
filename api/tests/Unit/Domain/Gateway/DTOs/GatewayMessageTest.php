<?php

declare(strict_types=1);

use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\Enums\GatewayDomain;

describe('GatewayMessage', function (): void {
    beforeEach(function (): void {
        \Illuminate\Support\Facades\Date::setTestNow('2026-01-28 12:00:00');
    });

    afterEach(function (): void {
        \Illuminate\Support\Facades\Date::setTestNow();
    });

    it('creates message with ordered uuid as correlationId', function (): void {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: 'openai',
            payload: ['messages' => []],
        );

        expect($message->correlationId)
            ->not->toBeEmpty()
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('generates timestamp automatically in ISO8601 format', function (): void {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: 'openai',
            payload: ['messages' => []],
        );

        expect($message->timestamp)
            ->not->toBeEmpty()
            ->toContain('2026-01-28');
    });

    it('serializes to array correctly via toArray', function (): void {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: 'openai',
            payload: ['messages' => [['role' => 'user', 'content' => 'Hello']]],
            metadata: ['tenant_id' => 1],
        );

        $array = $message->toArray();

        expect($array)
            ->toHaveKeys(['correlationId', 'timestamp', 'domain', 'action', 'provider', 'payload', 'metadata'])
            ->and($array['domain'])->toBe('ai')
            ->and($array['action'])->toBe('complete')
            ->and($array['provider'])->toBe('openai')
            ->and($array['payload'])->toBeString()
            ->and(json_decode((string) $array['payload'], true))->toBe(['messages' => [['role' => 'user', 'content' => 'Hello']]])
            ->and(json_decode((string) $array['metadata'], true))->toBe(['tenant_id' => 1]);
    });

    it('generates unique correlationIds for different messages', function (): void {
        $message1 = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: 'openai',
            payload: [],
        );

        $message2 = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: 'openai',
            payload: [],
        );

        expect($message1->correlationId)->not->toBe($message2->correlationId);
    });

    it('preserves all properties when constructed directly', function (): void {
        $message = new GatewayMessage(
            correlationId: 'custom-id-123',
            timestamp: '2026-01-28T10:00:00+00:00',
            domain: GatewayDomain::WHATSAPP,
            action: 'send',
            provider: 'zapi',
            payload: ['phone' => '5511999999999'],
            metadata: ['priority' => 'high'],
        );

        expect($message->correlationId)->toBe('custom-id-123')
            ->and($message->timestamp)->toBe('2026-01-28T10:00:00+00:00')
            ->and($message->domain)->toBe(GatewayDomain::WHATSAPP)
            ->and($message->action)->toBe('send')
            ->and($message->provider)->toBe('zapi')
            ->and($message->payload)->toBe(['phone' => '5511999999999'])
            ->and($message->metadata)->toBe(['priority' => 'high']);
    });
});
