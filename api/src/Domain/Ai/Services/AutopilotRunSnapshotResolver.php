<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiAgent;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hidrata snapshots (prompt + context + tools) antes de publicar a run no stream
 * `ai.run.request`, evitando que o gateway faça 2–3 round-trips HTTP de volta
 * ao Laravel para obter os mesmos dados.
 *
 * Toda hidratação é tolerante a falhas: se algo der errado, retornamos `null`
 * para o campo correspondente e o gateway aciona o fallback HTTP normalmente.
 *
 * @category Services
 */
final class AutopilotRunSnapshotResolver
{
    private const int CONTEXT_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly AiPromptResolverService $promptResolver,
        private readonly ToolDispatcherService $toolDispatcher,
    ) {}

    /**
     * Resolve os snapshots para uma run prestes a ser publicada.
     *
     * @return array{prompt: ?string, context: ?array<string, mixed>, tools: ?array<int, array<string, mixed>>, hydrated_at: string}
     */
    public function resolve(string $tenantId, AiAgent $agent, string $ticketId): array
    {
        return [
            'prompt' => $this->resolvePrompt($tenantId),
            'context' => $this->resolveContext($ticketId),
            'tools' => $this->resolveTools($agent),
            'hydrated_at' => now()->toIso8601String(),
        ];
    }

    private function resolvePrompt(string $tenantId): ?string
    {
        try {
            $tenant = PlatformTenant::query()->find($tenantId);

            if (! $tenant instanceof PlatformTenant) {
                return null;
            }

            return $this->promptResolver->resolve($tenant);
        } catch (\Throwable $e) {
            Log::warning('[AutopilotRunSnapshotResolver] Failed to resolve prompt', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveContext(string $ticketId): ?array
    {
        if ($ticketId === '') {
            return null;
        }

        try {
            $cacheKey = sprintf('autopilot:snapshot:context:%s', $ticketId);

            return Cache::remember($cacheKey, self::CONTEXT_CACHE_TTL_SECONDS, function () use ($ticketId): ?array {
                $ticket = ChatTicket::query()->find($ticketId);

                if (! $ticket instanceof ChatTicket) {
                    return null;
                }

                return [
                    'ticket_id' => (string) $ticket->id,
                    'tenant_id' => (string) $ticket->tenant_id,
                    'status' => (string) $ticket->status,
                    'subject' => (string) ($ticket->subject ?? ''),
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('[AutopilotRunSnapshotResolver] Failed to resolve context', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveTools(AiAgent $agent): ?array
    {
        try {
            $metadata = is_array($agent->metadata) ? $agent->metadata : [];
            $selected = data_get($metadata, 'tool_names');
            $selectedNames = is_array($selected)
                ? array_values(array_map(static fn (mixed $item): string => (string) $item, $selected))
                : null;

            $definitions = $this->toolDispatcher->getToolDefinitions(
                (string) $agent->tenant_id,
                (string) $agent->getAttribute('role'),
                $selectedNames,
            );

            return $definitions === [] ? null : $definitions;
        } catch (\Throwable $e) {
            Log::warning('[AutopilotRunSnapshotResolver] Failed to resolve tools', [
                'agent_id' => (string) $agent->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
