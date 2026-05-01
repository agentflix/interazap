<?php

/**
 * Teardown E2E — Remove todos os fixtures do tenant de teste.
 *
 * Executa em cascade: remove o tenant após limpar todos os dados.
 * Idempotente: não falha se dados já foram removidos.
 */

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Ai\Models\AiUsageLog;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNote;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\Platform\Models\PlatformTenant;

echo "\n\033[1;33m=== Teardown ===\033[0m\n";

$tenant = PlatformTenant::query()->where('tenant_code', 'E2EAUTO')->first();

if (! $tenant) {
    echo "  \033[33mTenant E2E não encontrado — nada a remover.\033[0m\n";
    return;
}

$tenantId = $tenant->id;

$cleanups = [
    'AiUsageLog'              => fn () => AiUsageLog::query()->where('tenant_id', $tenantId)->delete(),
    'AiSellerNotification'    => fn () => AiSellerNotification::query()->where('tenant_id', $tenantId)->delete(),
    'AiAutopilotRun'          => fn () => AiAutopilotRun::query()->where('tenant_id', $tenantId)->forceDelete(),
    'AiAutopilotPlaybook'     => fn () => AiAutopilotPlaybook::query()->where('tenant_id', $tenantId)->delete(),
    'AiAgent'                 => fn () => AiAgent::query()->where('tenant_id', $tenantId)->delete(),
    'AiKnowledgeChunk'        => fn () => AiKnowledgeChunk::query()->where('tenant_id', $tenantId)->delete(),
    'AiKnowledgeDocument'     => fn () => AiKnowledgeDocument::query()->where('tenant_id', $tenantId)->delete(),
    'ChatMessage'             => fn () => ChatMessage::query()->whereIn(
        'ticket_id',
        ChatTicket::query()->where('tenant_id', $tenantId)->pluck('id')
    )->delete(),
    'ChatTicket'              => fn () => ChatTicket::query()->where('tenant_id', $tenantId)->forceDelete(),
    'ChatInstance'            => fn () => ChatInstance::query()->where('tenant_id', $tenantId)->delete(),
    'CRMNote'                 => fn () => CRMNote::query()->where('tenant_id', $tenantId)->delete(),
    'CRMNegotiationTask'      => fn () => CRMNegotiationTask::query()->where('tenant_id', $tenantId)->delete(),
    'CRMProposal'             => fn () => CRMProposal::query()->where('tenant_id', $tenantId)->delete(),
    'CRMNegotiation'          => fn () => CRMNegotiation::query()->where('tenant_id', $tenantId)->forceDelete(),
    'CRMEvent'                => fn () => CRMEvent::query()->where('tenant_id', $tenantId)->delete(),
    'CRMProduct'              => fn () => CRMProduct::query()->where('tenant_id', $tenantId)->delete(),
    'CRMNegotiationFunnelStep'=> fn () => CRMNegotiationFunnelStep::query()->where('tenant_id', $tenantId)->delete(),
    'CRMNegotiationFunnel'    => fn () => CRMNegotiationFunnel::query()->where('tenant_id', $tenantId)->delete(),
    'CRMContact'              => fn () => CRMContact::query()->where('tenant_id', $tenantId)->delete(),
    'CRMCompany'              => fn () => CRMCompany::query()->where('tenant_id', $tenantId)->delete(),
    'PlatformTenant'          => fn () => $tenant->forceDelete(),
];

foreach ($cleanups as $name => $fn) {
    try {
        $fn();
        echo "  \033[32m✓\033[0m {$name} removido\n";
    } catch (\Throwable $e) {
        echo "  \033[33m⚠\033[0m {$name} — erro na limpeza: {$e->getMessage()}\n";
    }
}

echo "  \033[32m✓ Teardown concluído para tenant: {$tenantId}\033[0m\n";
