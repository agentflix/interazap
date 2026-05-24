<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use Domain\Chat\Services\WebChatJwtService;
use Tests\TestCase;

final class WebChatJwtServiceTest extends TestCase
{
    private WebChatJwtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebChatJwtService;
    }

    public function test_generates_valid_token_with_correct_claims(): void
    {
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = '660e8400-e29b-41d4-a716-446655440001';
        $contactId = '770e8400-e29b-41d4-a716-446655440002';
        $ticketId = '880e8400-e29b-41d4-a716-446655440003';

        $token = $this->service->generateToken($sessionId, $tenantId, $contactId, $ticketId);

        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token);
        $this->assertEquals(2, substr_count($token, '.'));
    }

    public function test_validate_token_returns_payload_for_valid_token(): void
    {
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';
        $tenantId = '660e8400-e29b-41d4-a716-446655440001';
        $contactId = '770e8400-e29b-41d4-a716-446655440002';
        $ticketId = '880e8400-e29b-41d4-a716-446655440003';

        $token = $this->service->generateToken($sessionId, $tenantId, $contactId, $ticketId);
        $payload = $this->service->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals($sessionId, $payload['sub']);
        $this->assertEquals($sessionId, $payload['session_id']);
        $this->assertEquals($tenantId, $payload['tenant_id']);
        $this->assertEquals($contactId, $payload['contact_id']);
        $this->assertEquals($ticketId, $payload['ticket_id']);
        $this->assertEquals('webchat', $payload['type']);
    }

    public function test_validate_token_returns_null_for_invalid_token(): void
    {
        $payload = $this->service->validateToken('invalid.token.here');

        $this->assertNull($payload);
    }

    public function test_validate_token_returns_null_for_tampered_token(): void
    {
        $token = $this->service->generateToken(
            'session-1',
            'tenant-1',
            'contact-1',
            'ticket-1'
        );

        $parts = explode('.', $token);
        $decodedPayload = json_decode(base64_decode($parts[1]), true);
        $decodedPayload['session_id'] = 'hacked';
        $parts[1] = base64_encode(json_encode($decodedPayload));
        $tamperedToken = implode('.', $parts);

        $payload = $this->service->validateToken($tamperedToken);

        $this->assertNull($payload);
    }

    public function test_validate_token_returns_null_for_empty_token(): void
    {
        $this->assertNull($this->service->validateToken(''));
    }

    public function test_validate_token_returns_null_for_malformed_token(): void
    {
        $this->assertNull($this->service->validateToken('not-a-jwt'));
        $this->assertNull($this->service->validateToken('only.two'));
    }

    public function test_default_tenant_id_is_not_used_as_signing_secret_when_app_key_exists(): void
    {
        config()->set('services.webchat.jwt_secret');
        config()->set('services.webchat.fallback_jwt_secret');
        config()->set('app.default_tenant_id', 'predictable-tenant-id');
        config()->set('app.key', 'base64:'.base64_encode('safe-app-key'));

        $service = new WebChatJwtService;
        $token = $service->generateToken('session-1', 'tenant-1', null, 'ticket-1');

        $this->assertNotNull($service->validateToken($token));

        config()->set('app.key');

        $this->expectException(\RuntimeException::class);
        new WebChatJwtService;
    }
}
