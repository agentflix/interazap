<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trait para padronizar logs e métricas em adapters.
 */
trait HasObservability
{
    protected ?string $requestId = null;

    protected function generateRequestId(): string
    {
        $this->requestId = Str::uuid()->toString();

        return $this->requestId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logOperationStart(string $operation, array $context = []): float
    {
        $startTime = microtime(true);

        Log::info('[WhatsApp] Iniciando operação', [
            'request_id' => $this->requestId,
            'provider' => $this->getProviderName(),
            'operation' => $operation,
            'tenant_id' => $this->resolveTenantId(),
            ...$context,
        ]);

        return $startTime;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logOperationEnd(string $operation, float $startTime, bool $success, array $context = []): void
    {
        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
        $logMethod = $success ? 'info' : 'error';

        Log::$logMethod('[WhatsApp] Operação concluída', [
            'request_id' => $this->requestId,
            'provider' => $this->getProviderName(),
            'operation' => $operation,
            'tenant_id' => $this->resolveTenantId(),
            'latency_ms' => $latencyMs,
            'success' => $success,
            ...$context,
        ]);
    }

    protected function logRateLimitHit(int $retryAfterSeconds): void
    {
        Log::warning('[WhatsApp] Rate limit atingido', [
            'request_id' => $this->requestId,
            'provider' => $this->getProviderName(),
            'tenant_id' => $this->resolveTenantId(),
            'retry_after_seconds' => $retryAfterSeconds,
        ]);
    }

    protected function logCircuitBreakerState(string $state, string $circuitKey): void
    {
        $level = $state === 'open' ? 'error' : 'info';

        Log::$level('[WhatsApp] Circuit breaker state change', [
            'request_id' => $this->requestId,
            'provider' => $this->getProviderName(),
            'tenant_id' => $this->resolveTenantId(),
            'circuit_key' => $circuitKey,
            'state' => $state,
        ]);
    }

    /**
     * Resolves tenant ID using app('tenant') container binding.
     *
     * @note Uses app() container binding instead of TenantContext::get() because
     *       this trait executes in WhatsApp adapter context where TenantContext
     *       may not be initialized via HTTP middleware.
     */
    protected function resolveTenantId(): ?string
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        if (is_object($tenant) && property_exists($tenant, 'id')) {
            return (string) $tenant->id;
        }

        return null;
    }

    abstract public function getProviderName(): string;
}
