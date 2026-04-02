<?php

declare(strict_types=1);

use Domain\Gateway\DTOs\GatewayError;
use Domain\Gateway\DTOs\GatewayResponse;

describe('GatewayResponse', function (): void {
    it('parses correctly from array via fromArray', function (): void {
        $data = [
            'correlationId' => 'test-correlation-id',
            'timestamp' => '2026-01-28T12:00:00+00:00',
            'success' => true,
            'data' => ['result' => 'success'],
        ];

        $response = GatewayResponse::fromArray($data);

        expect($response->correlationId)->toBe('test-correlation-id')
            ->and($response->timestamp)->toBe('2026-01-28T12:00:00+00:00')
            ->and($response->success)->toBeTrue()
            ->and($response->data)->toBe(['result' => 'success'])
            ->and($response->error)->toBeNull();
    });

    it('returns true from failed() when success is false', function (): void {
        $data = [
            'correlationId' => 'test-correlation-id',
            'timestamp' => '2026-01-28T12:00:00+00:00',
            'success' => false,
            'error' => [
                'code' => 'PROVIDER_ERROR',
                'message' => 'Something went wrong',
                'retryable' => true,
            ],
        ];

        $response = GatewayResponse::fromArray($data);

        expect($response->failed())->toBeTrue()
            ->and($response->success)->toBeFalse();
    });

    it('returns false from failed() when success is true', function (): void {
        $data = [
            'correlationId' => 'test-correlation-id',
            'timestamp' => '2026-01-28T12:00:00+00:00',
            'success' => true,
            'data' => ['content' => 'Hello'],
        ];

        $response = GatewayResponse::fromArray($data);

        expect($response->failed())->toBeFalse()
            ->and($response->success)->toBeTrue();
    });

    it('parses error to GatewayError when present', function (): void {
        $data = [
            'correlationId' => 'test-correlation-id',
            'timestamp' => '2026-01-28T12:00:00+00:00',
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests',
                'retryable' => true,
                'details' => ['retry_after' => 60],
            ],
        ];

        $response = GatewayResponse::fromArray($data);

        expect($response->error)
            ->toBeInstanceOf(GatewayError::class)
            ->and($response->error->code)->toBe('RATE_LIMIT_EXCEEDED')
            ->and($response->error->message)->toBe('Too many requests')
            ->and($response->error->retryable)->toBeTrue()
            ->and($response->error->details)->toBe(['retry_after' => 60]);
    });

    it('handles response without data or error', function (): void {
        $data = [
            'correlationId' => 'test-correlation-id',
            'timestamp' => '2026-01-28T12:00:00+00:00',
            'success' => true,
        ];

        $response = GatewayResponse::fromArray($data);

        expect($response->data)->toBeNull()
            ->and($response->error)->toBeNull()
            ->and($response->success)->toBeTrue();
    });

    it('can be constructed directly with all properties', function (): void {
        $error = new GatewayError(
            code: 'TIMEOUT',
            message: 'Request timed out',
            retryable: true,
        );

        $response = new GatewayResponse(
            correlationId: 'direct-construction',
            timestamp: '2026-01-28T15:00:00+00:00',
            success: false,
            error: $error,
        );

        expect($response->correlationId)->toBe('direct-construction')
            ->and($response->error)->toBe($error)
            ->and($response->failed())->toBeTrue();
    });
});
