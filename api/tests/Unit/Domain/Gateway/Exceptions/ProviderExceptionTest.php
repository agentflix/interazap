<?php

declare(strict_types=1);

use Domain\Gateway\DTOs\GatewayError;
use Domain\Gateway\Exceptions\GatewayException;
use Domain\Gateway\Exceptions\ProviderException;

describe('ProviderException', function (): void {
    it('creates exception from GatewayError via fromGatewayError factory', function (): void {
        $error = new GatewayError(
            code: 'RATE_LIMIT_EXCEEDED',
            message: 'Too many requests',
            retryable: true,
            details: ['retry_after' => 60],
        );

        $exception = ProviderException::fromGatewayError($error, 'correlation-123');

        expect($exception)
            ->toBeInstanceOf(ProviderException::class)
            ->toBeInstanceOf(GatewayException::class)
            ->and($exception->getMessage())->toBe('Too many requests')
            ->and($exception->errorCode)->toBe('RATE_LIMIT_EXCEEDED')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->correlationId)->toBe('correlation-123')
            ->and($exception->details)->toBe(['retry_after' => 60]);
    });

    it('returns true from isRetryable() when retryable is true', function (): void {
        $error = new GatewayError(
            code: 'TIMEOUT',
            message: 'Request timed out',
            retryable: true,
        );

        $exception = ProviderException::fromGatewayError($error);

        expect($exception->isRetryable())->toBeTrue();
    });

    it('returns false from isRetryable() when retryable is false', function (): void {
        $error = new GatewayError(
            code: 'INVALID_API_KEY',
            message: 'Invalid API key provided',
            retryable: false,
        );

        $exception = ProviderException::fromGatewayError($error);

        expect($exception->isRetryable())->toBeFalse();
    });

    it('handles GatewayError without details', function (): void {
        $error = new GatewayError(
            code: 'INTERNAL_ERROR',
            message: 'Internal server error',
            retryable: true,
        );

        $exception = ProviderException::fromGatewayError($error, 'test-correlation');

        expect($exception->details)->toBeNull()
            ->and($exception->errorCode)->toBe('INTERNAL_ERROR')
            ->and($exception->correlationId)->toBe('test-correlation');
    });

    it('can be constructed directly with all properties', function (): void {
        $exception = new ProviderException(
            message: 'Provider connection failed',
            errorCode: 'CONNECTION_FAILED',
            retryable: true,
            correlationId: 'manual-correlation',
            details: ['provider' => 'openai', 'attempt' => 3],
        );

        expect($exception->getMessage())->toBe('Provider connection failed')
            ->and($exception->errorCode)->toBe('CONNECTION_FAILED')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->isRetryable())->toBeTrue()
            ->and($exception->correlationId)->toBe('manual-correlation')
            ->and($exception->details)->toBe(['provider' => 'openai', 'attempt' => 3]);
    });

    it('extends GatewayException', function (): void {
        $exception = new ProviderException(
            message: 'Test exception',
            errorCode: 'TEST_ERROR',
            retryable: false,
        );

        expect($exception)->toBeInstanceOf(GatewayException::class);
    });

    it('handles null correlationId', function (): void {
        $error = new GatewayError(
            code: 'VALIDATION_ERROR',
            message: 'Invalid request payload',
            retryable: false,
        );

        $exception = ProviderException::fromGatewayError($error);

        expect($exception->correlationId)->toBeNull();
    });
});
