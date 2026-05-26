<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trait para padronizar logs estruturados e métricas em adapters de WhatsApp.
 *
 * Fornece geração de request_id para correlação de logs, registro de início/fim
 * de operações com latência calculada e logs de rate limit e circuit breaker.
 */
trait HasObservability
{
    protected ?string $requestId = null;

    /**
     * Gera e armazena um UUID como request ID para correlação de logs.
     *
     * @return string UUID gerado.
     */
    protected function generateRequestId(): string
    {
        $this->requestId = Str::uuid()->toString();

        return $this->requestId;
    }

    /**
     * Registra o início de uma operação e retorna o timestamp de início.
     *
     * @param  string  $operation  Nome da operação (ex: 'sendText').
     * @param  array<string, mixed>  $context  Contexto adicional para o log.
     * @return float Timestamp de início via microtime(true).
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
     * Registra o fim de uma operação com latência calculada.
     *
     * @param  string  $operation  Nome da operação.
     * @param  float  $startTime  Timestamp de início retornado por logOperationStart().
     * @param  bool  $success  Indica se a operação foi bem-sucedida.
     * @param  array<string, mixed>  $context  Contexto adicional para o log (ex: código de erro).
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

    /**
     * Registra um aviso de rate limit atingido pelo provedor.
     *
     * @param  int  $retryAfterSeconds  Segundos a aguardar antes de nova tentativa.
     */
    protected function logRateLimitHit(int $retryAfterSeconds): void
    {
        Log::warning('[WhatsApp] Rate limit atingido', [
            'request_id' => $this->requestId,
            'provider' => $this->getProviderName(),
            'tenant_id' => $this->resolveTenantId(),
            'retry_after_seconds' => $retryAfterSeconds,
        ]);
    }

    /**
     * Registra mudança de estado do circuit breaker.
     *
     * @param  string  $state  Novo estado do circuito ('open', 'closed', 'half-open').
     * @param  string  $circuitKey  Chave identificadora do circuito.
     */
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
     * Resolve o tenant_id via binding 'tenant' do container da aplicação.
     *
     * Usa o container em vez de TenantContext::get() porque adapters WhatsApp
     * podem executar fora do ciclo de requisição HTTP (jobs/workers).
     *
     * @return string|null Identificador do tenant ou null quando não disponível.
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
