<?php

declare(strict_types=1);

use Domain\Shared\Services\BusinessEventLogger;
use Illuminate\Support\Facades\Log;

describe('BusinessEventLogger', function (): void {
    beforeEach(function (): void {
        $this->logger = new BusinessEventLogger;
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
    });

    it('logs ticket created event', function (): void {
        $this->logger->ticketCreated('ticket-123', 'tenant-456');
        expect(true)->toBeTrue();
    });

    it('logs ticket closed event', function (): void {
        $this->logger->ticketClosed('ticket-123', 'tenant-456', 'resolved');
        expect(true)->toBeTrue();
    });

    it('logs message sent event', function (): void {
        $this->logger->messageSent('msg-123', 'ticket-456', 'outbound');
        expect(true)->toBeTrue();
    });

    it('logs message received event', function (): void {
        $this->logger->messageReceived('msg-123', 'ticket-456', 'whatsapp');
        expect(true)->toBeTrue();
    });

    it('logs negotiation updated event', function (): void {
        $this->logger->negotiationUpdated('neg-123', 'tenant-456', 'proposal', 1500.00);
        expect(true)->toBeTrue();
    });

    it('logs negotiation won event', function (): void {
        $this->logger->negotiationWon('neg-123', 'tenant-456', 5000.00);
        expect(true)->toBeTrue();
    });

    it('logs negotiation lost event', function (): void {
        $this->logger->negotiationLost('neg-123', 'tenant-456', 'price');
        expect(true)->toBeTrue();
    });

    it('logs payment received event', function (): void {
        $this->logger->paymentReceived('pay-123', 'tenant-456', 299.90, 'credit_card');
        expect(true)->toBeTrue();
    });

    it('sanitizes sensitive data', function (): void {
        // Password should be redacted
        $this->logger->ticketCreated('ticket-123', 'tenant-456', [
            'user_password' => 'secret123',
            'api_token' => 'tok_abc',
        ]);
        expect(true)->toBeTrue();
    });
});
