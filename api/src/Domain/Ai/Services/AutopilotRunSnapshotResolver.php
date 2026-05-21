<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiAgent;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
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
 * Tools são resolvidas exclusivamente via `ai_agent_tools` (pivot DB) —
 * `metadata.tool_names` é ignorado.
 *
 * @category Services
 */
final class AutopilotRunSnapshotResolver
{
    private const int SNAPSHOT_CACHE_TTL_SECONDS = 60;

    private const int SNAPSHOT_INDEX_TTL_SECONDS = 3600;

    public function __construct(
        private readonly AiPromptResolverService $promptResolver,
        private readonly ToolDispatcherService $toolDispatcher,
        private readonly AiAgentToolPermissionService $agentToolPermissionService,
    ) {}

    /**
     * Resolve os snapshots para uma run prestes a ser publicada.
     *
     * @return array{prompt: ?string, context: ?array<string, mixed>, tools: ?array<int, array<string, mixed>>, hydrated_at: string}
     */
    public function resolve(string $tenantId, AiAgent $agent, string $ticketId): array
    {
        if ($ticketId === '') {
            return $this->buildSnapshot($tenantId, $agent, $ticketId);
        }

        $cacheKey = self::snapshotKey($tenantId, (string) $agent->id, $ticketId);

        return Cache::remember(
            $cacheKey,
            self::SNAPSHOT_CACHE_TTL_SECONDS,
            function () use ($tenantId, $agent, $ticketId, $cacheKey): array {
                $snapshot = $this->buildSnapshot($tenantId, $agent, $ticketId);
                $this->rememberSnapshotIndex($tenantId, (string) $agent->id, $ticketId, $cacheKey);

                return $snapshot;
            }
        );
    }

    public static function forgetForTicket(string $tenantId, string $ticketId): void
    {
        if ($tenantId === '' || $ticketId === '') {
            return;
        }

        $indexKey = self::ticketIndexKey($tenantId, $ticketId);
        $keys = Cache::get($indexKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    Cache::forget($key);
                }
            }
        }

        Cache::forget($indexKey);
    }

    public static function forgetForAgent(string $tenantId, string $agentId): void
    {
        if ($tenantId === '' || $agentId === '') {
            return;
        }

        $indexKey = self::agentIndexKey($tenantId, $agentId);
        $keys = Cache::get($indexKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    Cache::forget($key);
                }
            }
        }

        Cache::forget($indexKey);
    }

    private function buildSnapshot(string $tenantId, AiAgent $agent, string $ticketId): array
    {
        return [
            'prompt' => $this->resolvePrompt($tenantId),
            'context' => $this->resolveContext($ticketId),
            'tools' => $this->resolveTools($tenantId, $agent),
            'hydrated_at' => now()->toIso8601String(),
        ];
    }

    private static function snapshotKey(string $tenantId, string $agentId, string $ticketId): string
    {
        return sprintf('autopilot:snapshot:%s:%s:%s', $tenantId, $agentId, $ticketId);
    }

    private static function ticketIndexKey(string $tenantId, string $ticketId): string
    {
        return sprintf('autopilot:snapshot:index:ticket:%s:%s', $tenantId, $ticketId);
    }

    private static function agentIndexKey(string $tenantId, string $agentId): string
    {
        return sprintf('autopilot:snapshot:index:agent:%s:%s', $tenantId, $agentId);
    }

    private function rememberSnapshotIndex(string $tenantId, string $agentId, string $ticketId, string $cacheKey): void
    {
        $ticketIndexKey = self::ticketIndexKey($tenantId, $ticketId);
        $agentIndexKey = self::agentIndexKey($tenantId, $agentId);

        $ticketKeys = Cache::get($ticketIndexKey, []);
        $agentKeys = Cache::get($agentIndexKey, []);

        $ticketKeys = is_array($ticketKeys) ? $ticketKeys : [];
        $agentKeys = is_array($agentKeys) ? $agentKeys : [];

        if (! in_array($cacheKey, $ticketKeys, true)) {
            $ticketKeys[] = $cacheKey;
        }

        if (! in_array($cacheKey, $agentKeys, true)) {
            $agentKeys[] = $cacheKey;
        }

        Cache::put($ticketIndexKey, $ticketKeys, self::SNAPSHOT_INDEX_TTL_SECONDS);
        Cache::put($agentIndexKey, $agentKeys, self::SNAPSHOT_INDEX_TTL_SECONDS);
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
            $ticket = ChatTicket::query()->find($ticketId);

            if (! $ticket instanceof ChatTicket) {
                return null;
            }

            $context = [
                'ticket_id' => (string) $ticket->id,
                'tenant_id' => (string) $ticket->tenant_id,
                'status' => (string) $ticket->status,
                'subject' => (string) ($ticket->subject ?? ''),
            ];

            $contactId = $ticket->contact_id ? (string) $ticket->contact_id : null;
            $context['contact_id'] = $contactId;
            $context['assigned_seller'] = $this->resolveAssignedSeller($ticket);
            $context['default_seller'] = $this->resolveDefaultSeller((string) $ticket->tenant_id);
            $context['active_negotiation'] = $this->resolveActiveNegotiation(
                (string) $ticket->tenant_id,
                $contactId,
            );
            $context['available_agents'] = $this->resolveAvailableAgents((string) $ticket->tenant_id);

            return $context;
        } catch (\Throwable $e) {
            Log::warning('[AutopilotRunSnapshotResolver] Failed to resolve context', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{id: string, name: string, email: ?string}|null
     */
    private function resolveAssignedSeller(ChatTicket $ticket): ?array
    {
        if (! is_string($ticket->assigned_to) || $ticket->assigned_to === '') {
            return null;
        }

        $seller = AuthUser::query()
            ->where('tenant_id', (string) $ticket->tenant_id)
            ->where('is_active', true)
            ->find($ticket->assigned_to);

        return $seller instanceof AuthUser ? $this->formatSeller($seller) : null;
    }

    /**
     * @return array{id: string, name: string, email: ?string}|null
     */
    private function resolveDefaultSeller(string $tenantId): ?array
    {
        $seller = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN lower(name) LIKE '%rosa%' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->first();

        return $seller instanceof AuthUser ? $this->formatSeller($seller) : null;
    }

    /**
     * @return array{id: string, title: string, status: string}|null
     */
    private function resolveActiveNegotiation(string $tenantId, ?string $contactId): ?array
    {
        if ($contactId === null || $contactId === '') {
            return null;
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_contact_id', $contactId)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->first();

        if (! $negotiation instanceof CRMNegotiation) {
            return null;
        }

        return [
            'id' => (string) $negotiation->id,
            'title' => (string) $negotiation->title,
            'status' => $negotiation->status instanceof \BackedEnum
                ? (string) $negotiation->status->value
                : (string) $negotiation->status,
        ];
    }

    /**
     * @return list<array{id: string, name: string, role: ?string}>
     */
    private function resolveAvailableAgents(string $tenantId): array
    {
        return AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'metadata'])
            ->map(static fn (AiAgent $agent): array => [
                'id' => (string) $agent->id,
                'name' => (string) $agent->name,
                'role' => is_array($agent->metadata) ? ($agent->metadata['role'] ?? null) : null,
            ])
            ->all();
    }

    /**
     * @return array{id: string, name: string, email: ?string}
     */
    private function formatSeller(AuthUser $seller): array
    {
        return [
            'id' => (string) $seller->id,
            'name' => (string) $seller->name,
            'email' => $seller->email ? (string) $seller->email : null,
        ];
    }

    /**
     * Resolve tool definitions a partir da pivot `ai_agent_tools`.
     *
     * Não utiliza `metadata.tool_names` — os nomes vêm exclusivamente do
     * serviço de permissões via banco de dados.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveTools(string $tenantId, AiAgent $agent): ?array
    {
        try {
            $toolNames = $this->agentToolPermissionService->toolNamesForAgent(
                $tenantId,
                (string) $agent->id,
            );

            if ($toolNames === []) {
                return null;
            }

            $definitions = $this->toolDispatcher->getToolDefinitions(
                $tenantId,
                null,
                $toolNames,
            );

            return $definitions === [] ? null : $definitions;
        } catch (\Throwable $e) {
            Log::warning('[AutopilotRunSnapshotResolver] Failed to resolve tools', [
                'agent_id' => (string) $agent->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
