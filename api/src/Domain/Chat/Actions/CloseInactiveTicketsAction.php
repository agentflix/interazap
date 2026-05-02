<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Identifica e fecha tickets inativos por inatividade baseado nas configurações
 * de auto-close do tenant e de cada instância (canal).
 *
 * Hierarquia de configuração:
 *   1. chat_instances.auto_close_*  (se não-null)
 *   2. platform_tenants.settings_chat.auto_close_inactivity_*  (fallback)
 *
 * A action é idempotente: só fecha tickets com status open/in_progress
 * e closed_mode IS NULL, evitando re-fechamento.
 */
final class CloseInactiveTicketsAction
{
    /**
     * Executa o batch de identificação e fechamento de tickets expirados.
     *
     * @return array{closed_ids: list<string>, skipped_ids: list<string>}
     */
    public function execute(PlatformTenant $tenant): array
    {
        $chatSettings = $tenant->settings_chat ?? [];

        $tenantId = (string) $tenant->id;

        // Buscar canais ativos do tenant
        $instances = ChatInstance::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($instances->isEmpty()) {
            return ['closed_ids' => [], 'skipped_ids' => []];
        }

        // Agrupar canais por target e minutos para query otimizada
        $bothIds = [];
        $clientIds = [];
        $agentIds = [];
        $minutesByInstance = [];

        foreach ($instances as $instance) {
            $config = $instance->getEffectiveAutoCloseConfig($tenant);

            // Pular canal se auto-close desabilitado
            if (! $config['enabled']) {
                continue;
            }

            $instanceId = (string) $instance->id;
            $minutesByInstance[$instanceId] = $config['minutes'];

            match ($config['target']) {
                'client' => $clientIds[] = $instanceId,
                'agent' => $agentIds[] = $instanceId,
                default => $bothIds[] = $instanceId,
            };
        }

        $allEnabledIds = array_merge($bothIds, $clientIds, $agentIds);

        if (empty($allEnabledIds)) {
            return ['closed_ids' => [], 'skipped_ids' => []];
        }

        // Query SELECT: identificar tickets expirados
        // Cada canal pode ter minutos diferentes — aplicamos OR groups por instance_id
        $expiredIds = [];

        // Para target 'both': usa last_message_at
        if (! empty($bothIds)) {
            $bothTickets = ChatTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('instance_id', $bothIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNull('closed_mode')
                ->where(function ($query) use ($bothIds, $minutesByInstance): void {
                    foreach ($bothIds as $instanceId) {
                        $minutes = $minutesByInstance[$instanceId] ?? 30;
                        $query->orWhere(function ($inner) use ($instanceId, $minutes): void {
                            $inner->where('instance_id', $instanceId)
                                ->where('last_message_at', '<', now()->subMinutes($minutes));
                        });
                    }
                })
                ->pluck('id')
                ->toArray();

            $expiredIds = array_merge($expiredIds, $bothTickets);
        }

        // Para target 'client': usa last_customer_message_at
        if (! empty($clientIds)) {
            $clientTickets = ChatTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('instance_id', $clientIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNull('closed_mode')
                ->where(function ($query) use ($clientIds, $minutesByInstance): void {
                    foreach ($clientIds as $instanceId) {
                        $minutes = $minutesByInstance[$instanceId] ?? 30;
                        $query->orWhere(function ($inner) use ($instanceId, $minutes): void {
                            $inner->where('instance_id', $instanceId)
                                ->where('last_customer_message_at', '<', now()->subMinutes($minutes));
                        });
                    }
                })
                ->pluck('id')
                ->toArray();

            $expiredIds = array_merge($expiredIds, $clientTickets);
        }

        // Para target 'agent': usa last_agent_message_at
        if (! empty($agentIds)) {
            $agentTickets = ChatTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('instance_id', $agentIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNull('closed_mode')
                ->where(function ($query) use ($agentIds, $minutesByInstance): void {
                    foreach ($agentIds as $instanceId) {
                        $minutes = $minutesByInstance[$instanceId] ?? 30;
                        $query->orWhere(function ($inner) use ($instanceId, $minutes): void {
                            $inner->where('instance_id', $instanceId)
                                ->where('last_agent_message_at', '<', now()->subMinutes($minutes));
                        });
                    }
                })
                ->pluck('id')
                ->toArray();

            $expiredIds = array_merge($expiredIds, $agentTickets);
        }

        if (empty($expiredIds)) {
            return ['closed_ids' => [], 'skipped_ids' => []];
        }

        // Deduplicar IDs (um ticket pode ser capturado por múltiplas queries)
        $expiredIds = array_values(array_unique($expiredIds));

        // UPDATE batch em transação atômica
        $closedIds = [];

        DB::transaction(function () use ($tenant, $expiredIds, &$closedIds): void {
            $tickets = ChatTicket::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $expiredIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNull('closed_mode')
                ->get();

            foreach ($tickets as $ticket) {
                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                    'closed_mode' => 'auto_inactivity',
                ]);

                $closedIds[] = (string) $ticket->id;
            }
        });

        // Disparar TicketClosedEvent para cada ticket fechado
        // Re-fetch para garantir dados atualizados após o update
        $closedTickets = ChatTicket::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $closedIds)
            ->get();

        foreach ($closedTickets as $ticket) {
            TicketClosedEvent::dispatch(
                (string) $tenant->id,
                (string) $ticket->id,
                $ticket->assigned_to ? (string) $ticket->assigned_to : null,
                'auto_inactivity',
            );
        }

        $skippedIds = array_values(array_diff($expiredIds, $closedIds));

        Log::info('[auto-close] Tenant {tenant}: closed={closed}, skipped={skipped}', [
            'tenant' => $tenant->id,
            'closed' => count($closedIds),
            'skipped' => count($skippedIds),
        ]);

        return [
            'closed_ids' => $closedIds,
            'skipped_ids' => $skippedIds,
        ];
    }
}
