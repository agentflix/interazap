<?php

/**
 * Setup de fixtures E2E para o módulo Autopilot.
 *
 * Retorna $ctx com todos os IDs necessários para os testes.
 * Idempotente: se o tenant já existe, reutiliza os dados.
 */

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Str;

// ─── Tenant ─────────────────────────────────────────────────────────────────

$tenant = PlatformTenant::query()->where('tenant_code', 'E2EAUTO')->first();

if (! $tenant) {
    // plan_id é NOT NULL — reutiliza o primeiro plano existente
    $planId = \Domain\Platform\Models\PlatformPlan::query()->value('id');

    $tenant = PlatformTenant::query()->create([
        'id' => (string) Str::orderedUuid(),
        'name' => 'E2E Autopilot Test Tenant',
        'tenant_code' => 'E2EAUTO',
        'primary_email' => 'e2e-autopilot@interazap.test',
        'plan_id' => $planId,
        'is_active' => true,
    ]);
}

$tenantId = $tenant->id;

// ─── ChatInstance ────────────────────────────────────────────────────────────

$instance = ChatInstance::query()->where('tenant_id', $tenantId)->first();

if (! $instance) {
    $instance = ChatInstance::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'provider' => 'uazapi',
        'name' => 'E2E Instance',
        'mode' => 'whatsapp',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => Str::random(32),
        'settings_json' => [],
    ]);
}

// ─── CRM Funnel + Steps ──────────────────────────────────────────────────────

$funnel = CRMNegotiationFunnel::query()->where('tenant_id', $tenantId)->first();

if (! $funnel) {
    $funnel = CRMNegotiationFunnel::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'E2E Funil de Vendas',
        'is_active' => true,
    ]);
}

$stepA = CRMNegotiationFunnelStep::query()
    ->where('tenant_id', $tenantId)
    ->where('crm_negotiation_funnel_id', $funnel->id)
    ->where('name', 'Prospecção E2E')
    ->first();

if (! $stepA) {
    $stepA = CRMNegotiationFunnelStep::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'crm_negotiation_funnel_id' => $funnel->id,
        'name' => 'Prospecção E2E',
        'order' => 1,
        'color' => '#3B82F6',
    ]);
}

$stepB = CRMNegotiationFunnelStep::query()
    ->where('tenant_id', $tenantId)
    ->where('crm_negotiation_funnel_id', $funnel->id)
    ->where('name', 'Qualificação E2E')
    ->first();

if (! $stepB) {
    $stepB = CRMNegotiationFunnelStep::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'crm_negotiation_funnel_id' => $funnel->id,
        'name' => 'Qualificação E2E',
        'order' => 2,
        'color' => '#10B981',
    ]);
}

// ─── CRM Products ────────────────────────────────────────────────────────────

$product = CRMProduct::query()->where('tenant_id', $tenantId)->where('name', 'Produto E2E')->first();

if (! $product) {
    $product = CRMProduct::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Produto E2E',
        'price' => 99.90,
        'is_active' => true,
    ]);
}

// ─── CRM Contact ─────────────────────────────────────────────────────────────

$contact = CRMContact::query()->where('tenant_id', $tenantId)->where('email', 'e2e-contact@test.com')->first();

if (! $contact) {
    $contact = CRMContact::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Contato E2E',
        'phone' => '+5511900000001',
        'email' => 'e2e-contact@test.com',
        'is_active' => true,
    ]);
}

// ─── CRM Negotiation ─────────────────────────────────────────────────────────

$negotiation = CRMNegotiation::query()
    ->where('tenant_id', $tenantId)
    ->where('title', 'Negociação E2E')
    ->first();

if (! $negotiation) {
    $negotiation = CRMNegotiation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'crm_contact_id' => $contact->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $stepA->id,
        'title' => 'Negociação E2E',
        'amount' => 500.00,
        'status' => 'open',
        'lead_score' => 50,
        'position' => 1,
    ]);
}

// ─── Chat Ticket (open) ───────────────────────────────────────────────────────

$ticket = ChatTicket::query()
    ->where('tenant_id', $tenantId)
    ->where('status', 'open')
    ->where('phone', '+5511900000001')
    ->first();

if (! $ticket) {
    $ticket = ChatTicket::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'contact_id' => $contact->id,
        'instance_id' => $instance->id,
        'channel' => 'whatsapp',
        'phone' => '+5511900000001',
        'phone_e164' => '+5511900000001',
        'remote_jid' => '5511900000001@s.whatsapp.net',
        'push_name' => 'Contato E2E',
        'status' => 'open',
        'priority' => 'normal',
        'is_bot_active' => false,
    ]);
}

// ─── AI Agent ────────────────────────────────────────────────────────────────

$agent = AiAgent::query()->where('tenant_id', $tenantId)->where('name', 'Agente E2E')->first();

if (! $agent) {
    $agent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agente E2E',
        'type' => 'general',
        'model_id' => 'claude-haiku-4-5-20251001',
        'is_active' => true,
        'max_tokens' => 800,
        'temperature' => 0.7,
        'top_p' => 1.0,
    ]);
}

// ─── AI Autopilot Playbook ────────────────────────────────────────────────────

$playbook = AiAutopilotPlaybook::query()->where('tenant_id', $tenantId)->where('name', 'Playbook E2E')->first();

if (! $playbook) {
    $playbook = AiAutopilotPlaybook::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Playbook E2E',
        'objective' => 'Playbook criado para testes E2E',
        'is_active' => true,
    ]);
}

// ─── Knowledge Document + Chunk (para search_knowledge) ─────────────────────

$kbDoc = AiKnowledgeDocument::query()
    ->where('tenant_id', $tenantId)
    ->where('name', 'Doc E2E')
    ->first();

if (! $kbDoc) {
    $kbDoc = AiKnowledgeDocument::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Doc E2E',
        'original_filename' => 'doc-e2e.txt',
        'file_path' => 'e2e/doc-e2e.txt',
        'file_size_bytes' => 100,
        'file_type' => 'txt',
        'version' => 1,
        'chunk_count' => 1,
        'embedding_status' => 'ready',
        'is_active' => true,
    ]);
}

$kbChunk = AiKnowledgeChunk::query()
    ->where('tenant_id', $tenantId)
    ->where('document_id', $kbDoc->id)
    ->first();

if (! $kbChunk) {
    $kbChunk = AiKnowledgeChunk::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'document_id' => $kbDoc->id,
        'chunk_index' => 0,
        'content' => 'Este é um documento de teste E2E para validação do módulo autopilot.',
        'token_count' => 20,
        'content_hash' => hash('sha256', 'Este é um documento de teste E2E para validação do módulo autopilot.'),
        'created_at' => now(),
    ]);
}

// ─── Contexto padrão do agente ────────────────────────────────────────────────

$agentCtx = [
    'tenant_id' => $tenantId,
    'agent_id' => $agent->id,
    'agent_name' => 'Agente E2E',
    'agent_role' => 'general',
    'current_run_id' => (string) Str::orderedUuid(),
];

// ─── Retorno ─────────────────────────────────────────────────────────────────

return [
    'tenant_id' => $tenantId,
    'instance_id' => $instance->id,
    'funnel_id' => $funnel->id,
    'step_a_id' => $stepA->id,
    'step_b_id' => $stepB->id,
    'product_id' => $product->id,
    'contact_id' => $contact->id,
    'negotiation_id' => $negotiation->id,
    'ticket_id' => $ticket->id,
    'agent_id' => $agent->id,
    'playbook_id' => $playbook->id,
    'kb_doc_id' => $kbDoc->id,
    'kb_chunk_id' => $kbChunk->id,
    'agent_ctx' => $agentCtx,
];
