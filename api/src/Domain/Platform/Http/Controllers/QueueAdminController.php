<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Controller for queue administration endpoints.
 *
 * Proxies administrative queue operations to the Gateway service
 * using the internal API key for authentication. All mutating
 * actions are audited for compliance and traceability.
 */
final class QueueAdminController extends BaseController
{
    /**
     * @param  AuditLogger  $audit  Serviço de auditoria.
     */
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Get overview of all queues.
     *
     * @return JsonResponse Visão geral das filas.
     */
    public function index(): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyGet('v1/admin/queues');

        return response()->json($response);
    }

    /**
     * Get details for a specific queue.
     *
     * @param  string  $name  Nome da fila.
     * @return JsonResponse Detalhes da fila.
     */
    public function show(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyGet("v1/admin/queues/{$name}");

        return response()->json($response);
    }

    /**
     * Pause a queue.
     *
     * @param  string  $name  Nome da fila.
     * @return JsonResponse Resultado da operação.
     */
    public function pause(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyPost("v1/admin/queues/{$name}/pause");

        $this->auditQueueAction('queue.paused', $name, 'pause');

        return response()->json($response);
    }

    /**
     * Resume a queue.
     *
     * @param  string  $name  Nome da fila.
     * @return JsonResponse Resultado da operação.
     */
    public function resume(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyPost("v1/admin/queues/{$name}/resume");

        $this->auditQueueAction('queue.resumed', $name, 'resume');

        return response()->json($response);
    }

    /**
     * Clean completed/failed jobs from a queue.
     *
     * @param  Request  $request  Dados da limpeza.
     * @param  string  $name  Nome da fila.
     * @return JsonResponse Resultado da operação.
     */
    public function clean(Request $request, string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $payload = $request->only(['status', 'count']);
        $response = $this->proxyPost("v1/admin/queues/{$name}/clean", $payload);

        $this->auditQueueAction('queue.cleaned', $name, 'clean', $payload);

        return response()->json($response);
    }

    /**
     * List dead letter queue entries.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista de jobs na DLQ.
     */
    public function deadLetterIndex(Request $request): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $params = $request->query();

        $response = $this->proxyGet('v1/admin/queues/dlq', $params);

        return response()->json($response);
    }

    /**
     * Retry a specific DLQ job.
     *
     * @param  string  $id  Identificador do job.
     * @return JsonResponse Resultado da operação.
     */
    public function deadLetterRetry(string $id): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyPost("v1/admin/queues/dlq/{$id}/retry");

        $this->auditQueueAction('dlq.job_retried', $id, 'dlq_retry', ['job_id' => $id]);

        return response()->json($response);
    }

    /**
     * Retry all DLQ jobs.
     *
     * @param  Request  $request  Filtros opcionais.
     * @return JsonResponse Resultado da operação.
     */
    public function deadLetterRetryAll(Request $request): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $payload = $request->only(['queue', 'job_name', 'tenant_id']);
        $response = $this->proxyPost('v1/admin/queues/dlq/retry-all', $payload);

        $this->auditQueueAction('dlq.all_retried', 'dlq', 'dlq_retry_all', $payload);

        return response()->json($response);
    }

    /**
     * Remove a specific DLQ job.
     *
     * @param  string  $id  Identificador do job.
     * @return JsonResponse Resultado da operação.
     */
    public function deadLetterPurge(string $id): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyDelete("v1/admin/queues/dlq/{$id}");

        $this->auditQueueAction('dlq.job_purged', $id, 'dlq_purge', ['job_id' => $id]);

        return response()->json($response);
    }

    /**
     * Purge all DLQ jobs.
     *
     * @param  Request  $request  Filtros opcionais.
     * @return JsonResponse Resultado da operação.
     */
    public function deadLetterPurgeAll(Request $request): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $payload = $request->only(['queue', 'job_name', 'tenant_id']);
        $response = $this->proxyPost('v1/admin/queues/dlq/purge-all', $payload);

        $this->auditQueueAction('dlq.all_purged', 'dlq', 'dlq_purge_all', $payload);

        return response()->json($response);
    }

    /**
     * Get circuit breaker status for all circuits.
     *
     * @return JsonResponse Status dos circuit breakers.
     */
    public function circuitsIndex(): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyGet('v1/admin/queues/circuits');

        return response()->json($response);
    }

    /**
     * Get circuit breaker status for a specific circuit.
     *
     * @param  string  $name  Nome do circuit breaker.
     * @return JsonResponse Status do circuit breaker.
     */
    public function circuitsShow(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyGet("v1/admin/queues/circuits/{$name}");

        return response()->json($response);
    }

    /**
     * Reset a circuit breaker to CLOSED state.
     *
     * @param  string  $name  Nome do circuit breaker.
     * @return JsonResponse Resultado da operação.
     */
    public function circuitsReset(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyPost("v1/admin/queues/circuits/{$name}/reset");

        $this->auditQueueAction('circuit.reset', $name, 'circuit_reset');

        return response()->json($response);
    }

    /**
     * Force open a circuit breaker.
     *
     * @param  string  $name  Nome do circuit breaker.
     * @return JsonResponse Resultado da operação.
     */
    public function circuitsOpen(string $name): JsonResponse
    {
        $this->authorize('manage', \Domain\Platform\Policies\QueueAdminPolicy::class);

        $response = $this->proxyPost("v1/admin/queues/circuits/{$name}/open");

        $this->auditQueueAction('circuit.forced_open', $name, 'circuit_open');

        return response()->json($response);
    }

    /**
     * Proxy a GET request to the Gateway.
     *
     * @param  string  $endpoint  Endpoint no Gateway.
     * @param  array<string, mixed>  $query  Parâmetros de query.
     * @return array<mixed> Resposta do Gateway.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    private function proxyGet(string $endpoint, array $query = []): array
    {
        return $this->gatewayRequest()->get($endpoint, $query)->json() ?? [];
    }

    /**
     * Proxy a POST request to the Gateway.
     *
     * @param  string  $endpoint  Endpoint no Gateway.
     * @param  array<string, mixed>  $data  Dados do corpo.
     * @return array<mixed> Resposta do Gateway.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    private function proxyPost(string $endpoint, array $data = []): array
    {
        return $this->gatewayRequest()->post($endpoint, $data)->json() ?? [];
    }

    /**
     * Proxy a DELETE request to the Gateway.
     *
     * @param  string  $endpoint  Endpoint no Gateway.
     * @return array<mixed> Resposta do Gateway.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    private function proxyDelete(string $endpoint): array
    {
        return $this->gatewayRequest()->delete($endpoint)->json() ?? [];
    }

    /**
     * Create a configured HTTP request to the Gateway.
     *
     *
     * @throws InvalidArgumentException
     */
    private function gatewayRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = rtrim((string) config('services.gateway.url', ''), '/');
        $apiKey = (string) config('services.gateway.api_key', '');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('services.gateway.url is not configured');
        }

        if ($apiKey === '') {
            throw new InvalidArgumentException('services.gateway.api_key is not configured');
        }

        return Http::baseUrl($baseUrl)
            ->timeout(30)
            ->connectTimeout(5)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Register an audit log entry for a queue administration action.
     *
     * Logs the event name, queue/circuit identifier, action type,
     * and sanitized payload for compliance traceability.
     *
     * @param  string  $event  Nome do evento de auditoria.
     * @param  string  $queue  Identificador da fila ou circuito.
     * @param  string  $action  Tipo de ação executada.
     * @param  array<string, mixed>  $payload  Dados adicionais da operação.
     */
    private function auditQueueAction(string $event, string $queue, string $action, array $payload = []): void
    {
        /** @var \Domain\Auth\Models\AuthUser|null $user */
        $user = auth()->user();

        if (! $user instanceof \Domain\Auth\Models\AuthUser) {
            return;
        }

        $tenantId = $user->tenant_id ?? 'platform';

        $this->audit->log(
            $user,
            $tenantId,
            $event,
            new \Domain\Shared\Models\AuditLog,
            [
                'queue' => $queue,
                'action' => $action,
                'payload' => $payload,
                'gateway_endpoint' => $this->resolveGatewayEndpoint($queue, $action),
            ],
        );
    }

    /**
     * Resolve the gateway endpoint string for audit context.
     *
     * @param  string  $queue  Identificador da fila ou circuito.
     * @param  string  $action  Tipo de ação executada.
     */
    private function resolveGatewayEndpoint(string $queue, string $action): string
    {
        return match (true) {
            str_starts_with($action, 'dlq_') => "v1/admin/queues/dlq/{$queue}",
            str_starts_with($action, 'circuit_') => "v1/admin/queues/circuits/{$queue}/{$action}",
            default => "v1/admin/queues/{$queue}/{$action}",
        };
    }
}
