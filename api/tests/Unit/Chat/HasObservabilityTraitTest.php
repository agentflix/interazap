<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasObservability;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HasObservabilityTraitTest extends TestCase
{
    public function test_generates_request_id_and_logs_start_end_success_and_failure(): void
    {
        app()->instance('tenant', (object) ['id' => 'tenant-obs']);

        $harness = new HasObservabilityHarness;
        $requestId = $harness->generate();

        $this->assertNotSame('', $requestId);

        Log::shouldReceive('info')->once()->with('[WhatsApp] Iniciando operação', \Mockery::type('array'));
        Log::shouldReceive('info')->once()->with('[WhatsApp] Operação concluída', \Mockery::on(fn (array $context): bool => $context['success'] === true
            && $context['provider'] === 'harness'
            && $context['tenant_id'] === 'tenant-obs'));
        Log::shouldReceive('error')->once()->with('[WhatsApp] Operação concluída', \Mockery::on(fn (array $context): bool => $context['success'] === false));

        $start = $harness->start('sendText', ['foo' => 'bar']);
        $harness->end('sendText', $start, true, ['status' => 'ok']);
        $harness->end('sendText', $start, false, ['status' => 'error']);
    }

    public function test_logs_rate_limit_and_circuit_state_changes(): void
    {
        $harness = new HasObservabilityHarness;
        $harness->generate();

        Log::shouldReceive('warning')->once()->with('[WhatsApp] Rate limit atingido', \Mockery::type('array'));
        Log::shouldReceive('error')->once()->with('[WhatsApp] Circuit breaker state change', \Mockery::type('array'));
        Log::shouldReceive('info')->once()->with('[WhatsApp] Circuit breaker state change', \Mockery::type('array'));

        $harness->rateLimit(30);
        $harness->circuitState('open', 'provider:x');
        $harness->circuitState('closed', 'provider:x');
    }
}

final class HasObservabilityHarness
{
    use HasObservability;

    public function getProviderName(): string
    {
        return 'harness';
    }

    public function generate(): string
    {
        return $this->generateRequestId();
    }

    public function start(string $operation, array $context = []): float
    {
        return $this->logOperationStart($operation, $context);
    }

    public function end(string $operation, float $startTime, bool $success, array $context = []): void
    {
        $this->logOperationEnd($operation, $startTime, $success, $context);
    }

    public function rateLimit(int $retryAfterSeconds): void
    {
        $this->logRateLimitHit($retryAfterSeconds);
    }

    public function circuitState(string $state, string $circuitKey): void
    {
        $this->logCircuitBreakerState($state, $circuitKey);
    }
}
