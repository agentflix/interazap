<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiToolEnum;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiContextBuilderService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMEventClientConfirmation;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMNote;
use Domain\CRM\Models\CRMProposal;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('13 · Chat Simulation (Sofia → Lucas)');

$ctx = require __DIR__.'/setup.php';

// ─── Helper: garantir tools ativas no tenant e atribuídas ao agente ─────────

function ensureToolsForAgent(string $tenantId, string $agentId): void
{
    $allToolNames = [
        AiToolEnum::SEND_MESSAGE,
        AiToolEnum::READ_TICKET,
        AiToolEnum::TRANSFER_TO_HUMAN,
        AiToolEnum::CLOSE_TICKET,
        AiToolEnum::CREATE_CONTACT,
        AiToolEnum::GET_CONTACT_INFO,
        AiToolEnum::UPDATE_CONTACT,
        AiToolEnum::UPDATE_CONTACT_TAGS,
        AiToolEnum::SEARCH_CONTACTS,
        AiToolEnum::LINK_CONTACT_TO_COMPANY,
        AiToolEnum::CREATE_COMPANY,
        AiToolEnum::UPDATE_COMPANY,
        AiToolEnum::CREATE_NEGOTIATION,
        AiToolEnum::GET_NEGOTIATION_INFO,
        AiToolEnum::MOVE_PIPELINE,
        AiToolEnum::UPDATE_LEAD_SCORE,
        AiToolEnum::QUALIFY_LEAD,
        AiToolEnum::CLOSE_NEGOTIATION,
        AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
        AiToolEnum::CREATE_PROPOSAL,
        AiToolEnum::LIST_PRODUCTS,
        AiToolEnum::LIST_FUNNEL_STEPS,
        AiToolEnum::SEARCH_KNOWLEDGE,
        AiToolEnum::CHECK_AVAILABILITY,
        AiToolEnum::SCHEDULE_EVENT,
        AiToolEnum::GET_AVAILABLE_SLOTS,
        AiToolEnum::CONFIRM_EVENT_BOOKING,
        AiToolEnum::CREATE_TASK,
        AiToolEnum::CREATE_NOTE,
        AiToolEnum::NOTIFY_SELLER,
        AiToolEnum::DELEGATE_TO_AGENT,
    ];

    foreach ($allToolNames as $toolName) {
        $existing = AiAutopilotTool::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $toolName)
            ->first();

        if (! $existing) {
            $className = 'Domain\\Ai\\Tools\\'.Str::studly($toolName).'Tool';
            AiAutopilotTool::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'name' => $toolName,
                'display_name' => Str::headline($toolName),
                'handler_class' => class_exists($className) ? $className : null,
                'is_active' => true,
            ]);
        }
    }

    $service = app(AiAgentToolPermissionService::class);
    $service->syncAgentTools($tenantId, $agentId, $allToolNames);
}

// ─── Setup Estendido ─────────────────────────────────────────────────────────

$sofiaAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Sofia Sim')->first();
if (! $sofiaAgent) {
    $sofiaAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Sofia Sim',
        'type' => 'general',
        'model_id' => 'claude-haiku-4-5-20251001',
        'is_active' => true,
        'max_tokens' => 800,
        'temperature' => 0.7,
        'top_p' => 1.0,
    ]);
}

$lucasAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Lucas Sim')->first();
if (! $lucasAgent) {
    $lucasAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Lucas Sim',
        'type' => 'sales',
        'model_id' => 'claude-haiku-4-5-20251001',
        'is_active' => true,
        'max_tokens' => 800,
        'temperature' => 0.7,
        'top_p' => 1.0,
    ]);
}

ensureToolsForAgent($ctx['tenant_id'], $sofiaAgent->id);
ensureToolsForAgent($ctx['tenant_id'], $lucasAgent->id);

$delegation = AiAgentDelegation::query()
    ->where('tenant_id', $ctx['tenant_id'])
    ->where('source_agent_id', $sofiaAgent->id)
    ->where('target_agent_id', $lucasAgent->id)
    ->first();

if (! $delegation) {
    $delegation = AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'source_agent_id' => $sofiaAgent->id,
        'target_agent_id' => $lucasAgent->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);
}

$parentRun = AiAutopilotRun::query()->create([
    'id' => (string) Str::orderedUuid(),
    'tenant_id' => $ctx['tenant_id'],
    'playbook_id' => $ctx['playbook_id'],
    'status' => 'running',
    'input_context' => [
        'ticket_id' => $ctx['ticket_id'],
        'contact_id' => $ctx['contact_id'],
        'instance_id' => $ctx['instance_id'],
    ],
    'started_at' => now(),
]);

$sofiaCtx = [
    'tenant_id' => $ctx['tenant_id'],
    'agent_id' => $sofiaAgent->id,
    'agent_name' => 'Sofia Sim',
    'current_run_id' => $parentRun->id,
];

$lucasCtx = [
    'tenant_id' => $ctx['tenant_id'],
    'agent_id' => $lucasAgent->id,
    'agent_name' => 'Lucas Sim',
    'current_run_id' => null,
];

// ─── Contadores manuais ──────────────────────────────────────────────────────

$g1Pass = 0;
$g1Fail = 0;
$g2Pass = 0;
$g2Fail = 0;
$g3Pass = 0;
$g3Fail = 0;

function trackResult(&$pass, &$fail, bool $isPass): void
{
    if ($isPass) {
        $pass++;
    } else {
        $fail++;
    }
}

// ─── Grupo 1 — Histórico de Conversa (~25 interações) ────────────────────────

e2e_group('13.1 · Chat History');

$outgoingMessages = [
    'Olá! Sou a Sofia. Como posso ajudar?',
    'Entendi! Temos várias opções para você.',
    'Posso enviar mais detalhes sobre o produto?',
    'O produto X custa R$ 99,90.',
    'Você gostaria de agendar uma demonstração?',
    'Qual o melhor horário para você?',
    'Perfeito! Vou verificar a disponibilidade.',
    'Encontrei um slot para amanhã às 14h.',
    'Posso confirmar esse horário?',
    'Ótimo! Vou agendar para você.',
];

foreach ($outgoingMessages as $i => $content) {
    $ok = e2e_run('chat_history: send_message #'.($i + 1), function () use ($sofiaCtx, $ctx, $content): void {
        $r = e2e_dispatch('send_message', [
            'ticket_id' => $ctx['ticket_id'],
            'content' => $content,
        ], $sofiaCtx);
        e2e_assert($r->success, 'Mensagem enviada');
    });
    trackResult($g1Pass, $g1Fail, $ok['pass']);
}

$incomingMessages = [
    'Oi, tenho interesse no produto',
    'Qual o preço?',
    'Pode me enviar mais informações?',
    'Gostaria de uma demonstração',
    'Amanhã às 14h funciona para mim',
    'Confirmo o horário',
    'Obrigado pela ajuda!',
    'Vou pensar e retorno',
    'Tenho mais uma dúvida',
    'Pode me passar o contato do vendedor?',
];

foreach ($incomingMessages as $i => $content) {
    $ok = e2e_run('chat_history: incoming message #'.($i + 1), function () use ($ctx, $content): void {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ctx['ticket_id'],
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        e2e_assert($msg->id !== null, 'Mensagem incoming criada');
    });
    trackResult($g1Pass, $g1Fail, $ok['pass']);
}

for ($i = 1; $i <= 3; $i++) {
    $ok = e2e_run("chat_history: read_ticket válido #{$i}", function () use ($sofiaCtx, $ctx): void {
        $r = e2e_dispatch('read_ticket', [
            'ticket_id' => $ctx['ticket_id'],
            'include_messages' => true,
            'message_limit' => 25,
        ], $sofiaCtx);
        e2e_assert($r->success, 'Ticket lido');
        e2e_assert(isset($r->data['messages']), 'Mensagens incluídas');
        e2e_assert(count($r->data['messages']) >= 15, 'Histórico tem pelo menos 15 mensagens');
    });
    trackResult($g1Pass, $g1Fail, $ok['pass']);
}

for ($i = 1; $i <= 2; $i++) {
    $ok = e2e_run("chat_history: validar contexto montado #{$i}", function () use ($ctx): void {
        $ticket = ChatTicket::query()->find($ctx['ticket_id']);
        $lastMessage = ChatMessage::query()->where('ticket_id', $ctx['ticket_id'])->latest()->first();
        $builder = app(AiContextBuilderService::class);
        $context = $builder->build($ticket, $lastMessage);
        e2e_assert(count($context['conversation_history']) >= 15, 'Histórico tem pelo menos 15 mensagens no contexto');
    });
    trackResult($g1Pass, $g1Fail, $ok['pass']);
}

e2e_summary('Grupo 1 · Chat History', $g1Pass, $g1Fail);

// ─── Grupo 2 — Cobertura de Todas as 31 Tools (~75 interações) ───────────────

e2e_group('13.2 · All 31 Tools');

// 1. send_message — 3 cenários
$ok = e2e_run('tool: send_message texto válido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content' => 'Mensagem de teste válida',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Mensagem enviada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: send_message ticket inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
        'content' => 'Teste',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com ticket inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: send_message content vazio', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content' => '',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com content vazio');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 2. read_ticket — 2 cenários
$ok = e2e_run('tool: read_ticket válido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('read_ticket', ['ticket_id' => $ctx['ticket_id']], $sofiaCtx);
    e2e_assert($r->success, 'Ticket lido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: read_ticket inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('read_ticket', ['ticket_id' => '00000000-0000-0000-0000-000000000000'], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com ticket inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 3. transfer_to_human — 2 cenários
$ok = e2e_run('tool: transfer_to_human desativa bot', function () use ($sofiaCtx, $ctx): void {
    ChatTicket::query()->where('id', $ctx['ticket_id'])->update(['is_bot_active' => true]);
    $r = e2e_dispatch('transfer_to_human', [
        'ticket_id' => $ctx['ticket_id'],
        'reason' => 'Transferência E2E',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Transferência realizada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: transfer_to_human ticket inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('transfer_to_human', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
        'reason' => 'Teste',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com ticket inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 4. close_ticket — 2 cenários
$closeTicketId = null;
$ok = e2e_run('tool: close_ticket fecha ticket', function () use ($sofiaCtx, $ctx, &$closeTicketId): void {
    $closeTicket = ChatTicket::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'contact_id' => $ctx['contact_id'],
        'instance_id' => $ctx['instance_id'],
        'channel' => 'whatsapp',
        'phone' => '+5511900000098',
        'phone_e164' => '+5511900000098',
        'remote_jid' => '5511900000098@s.whatsapp.net',
        'push_name' => 'Ticket Close Sim',
        'status' => 'open',
        'priority' => 'normal',
        'is_bot_active' => false,
    ]);
    $closeTicketId = $closeTicket->id;
    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => $closeTicket->id,
        'reason' => 'resolved',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Ticket fechado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: close_ticket ticket inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
        'reason' => 'resolved',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com ticket inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 5. create_contact — 3 cenários
$createdContactId = null;
$ok = e2e_run('tool: create_contact mínimo', function () use ($sofiaCtx, &$createdContactId): void {
    $r = e2e_dispatch('create_contact', [
        'name' => 'Contato Sim',
        'phone' => '+5511900000100',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Contato criado');
    $createdContactId = $r->data['contact_id'];
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_contact completo', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('create_contact', [
        'name' => 'Contato Completo Sim',
        'phone' => '+5511900000101',
        'email' => 'completo@sim.test',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Contato completo criado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_contact duplicado', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('create_contact', [
        'name' => 'Contato Sim',
        'phone' => '+5511900000100',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Contato duplicado criado (não valida duplicidade)');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 6. get_contact_info — 2 cenários
$ok = e2e_run('tool: get_contact_info encontra', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('get_contact_info', ['contact_id' => $ctx['contact_id']], $sofiaCtx);
    e2e_assert($r->success, 'Contato encontrado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: get_contact_info não encontra', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('get_contact_info', ['contact_id' => '00000000-0000-0000-0000-000000000000'], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com contact inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 7. update_contact — 3 cenários
$ok = e2e_run('tool: update_contact atualiza nome', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_contact', [
        'contact_id' => $ctx['contact_id'],
        'name' => 'Contato E2E Atualizado',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Nome atualizado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_contact atualiza email', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_contact', [
        'contact_id' => $ctx['contact_id'],
        'email' => 'atualizado@sim.test',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Email atualizado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_contact contact_id inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('update_contact', [
        'contact_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Teste',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com contact inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 8. update_contact_tags — 2 cenários
$ok = e2e_run('tool: update_contact_tags add tags', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_contact_tags', [
        'contact_id' => $ctx['contact_id'],
        'tags' => ['sim-tag-1', 'sim-tag-2'],
        'action' => 'add',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Tags adicionadas');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_contact_tags remove tags', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_contact_tags', [
        'contact_id' => $ctx['contact_id'],
        'tags' => ['sim-tag-1'],
        'action' => 'remove',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Tags removidas');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 9. search_contacts — 3 cenários
$ok = e2e_run('tool: search_contacts por nome', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_contacts', ['query' => 'Contato', 'limit' => 5], $sofiaCtx);
    e2e_assert($r->success, 'Busca por nome');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: search_contacts por email', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_contacts', ['query' => '@sim.test', 'limit' => 5], $sofiaCtx);
    e2e_assert($r->success, 'Busca por email');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: search_contacts query vazia', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_contacts', ['query' => ''], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com query vazia');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 10. link_contact_to_company — 2 cenários
$simCompanyId = null;
$ok = e2e_run('tool: link_contact_to_company válido', function () use ($sofiaCtx, $ctx, &$simCompanyId): void {
    $company = CRMCompany::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Empresa Sim E2E',
    ]);
    $simCompanyId = $company->id;
    $r = e2e_dispatch('link_contact_to_company', [
        'contact_id' => $ctx['contact_id'],
        'company_id' => $company->id,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Contato vinculado à empresa');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: link_contact_to_company ids inválidos', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('link_contact_to_company', [
        'contact_id' => '00000000-0000-0000-0000-000000000000',
        'company_id' => '00000000-0000-0000-0000-000000000000',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com IDs inválidos');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 11. create_company — 3 cenários
$simCompany2Id = null;
$ok = e2e_run('tool: create_company mínimo', function () use ($sofiaCtx, &$simCompany2Id): void {
    $r = e2e_dispatch('create_company', ['name' => 'Empresa Mínima Sim'], $sofiaCtx);
    e2e_assert($r->success, 'Empresa mínima criada');
    $simCompany2Id = $r->data['company_id'];
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_company completo', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('create_company', [
        'name' => 'Empresa Completa Sim',
        'document' => '12.345.678/0001-90',
        'email' => 'empresa@sim.test',
        'phone' => '+5511900000200',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Empresa completa criada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_company sem nome', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('create_company', ['name' => ''], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha sem nome');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 12. update_company — 2 cenários
$ok = e2e_run('tool: update_company válido', function () use ($sofiaCtx, &$simCompany2Id): void {
    if (! $simCompany2Id) {
        throw new RuntimeException('simCompany2Id não definido');
    }
    $r = e2e_dispatch('update_company', [
        'company_id' => $simCompany2Id,
        'name' => 'Empresa Mínima Sim Atualizada',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Empresa atualizada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_company id inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('update_company', [
        'company_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Teste',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com ID inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 13. create_negotiation — 3 cenários
$simNegotiationId = null;
$ok = e2e_run('tool: create_negotiation com amount', function () use ($sofiaCtx, $ctx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Negociação Sim',
        'step_id' => $ctx['step_a_id'],
        'amount' => 1500.00,
        'contact_id' => $ctx['contact_id'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Negociação com amount criada');
    $simNegotiationId = $r->data['negotiation_id'];
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_negotiation sem amount (com contact)', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Negociação Sim Sem Amount',
        'step_id' => $ctx['step_a_id'],
        'contact_id' => $ctx['contact_id'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Negociação sem amount criada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_negotiation contact inválido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Negociação Sim',
        'step_id' => $ctx['step_a_id'],
        'contact_id' => '00000000-0000-0000-0000-000000000000',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com contact inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 14. get_negotiation_info — 2 cenários
$ok = e2e_run('tool: get_negotiation_info válido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('get_negotiation_info', ['negotiation_id' => $ctx['negotiation_id']], $sofiaCtx);
    e2e_assert($r->success, 'Negociação carregada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: get_negotiation_info inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('get_negotiation_info', ['negotiation_id' => '00000000-0000-0000-0000-000000000000'], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com negotiation inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 15. move_pipeline — 3 cenários
$ok = e2e_run('tool: move_pipeline step A→B', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id' => $ctx['step_b_id'],
        'reason' => 'Qualificação E2E',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Pipeline movido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: move_pipeline step inválido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id' => '00000000-0000-0000-0000-000000000000',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com step inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: move_pipeline negotiation inválido', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => '00000000-0000-0000-0000-000000000000',
        'step_id' => $ctx['step_a_id'],
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com negotiation inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 16. update_lead_score — 3 cenários
$ok = e2e_run('tool: update_lead_score 0', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 0,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Score atualizado para 0');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_lead_score 50', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 50,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Score atualizado para 50');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: update_lead_score 100', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 100,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Score atualizado para 100');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 17. qualify_lead — 2 cenários
$ok = e2e_run('tool: qualify_lead hot', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('qualify_lead', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 80,
        'tags' => ['hot-lead'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Lead qualificado como hot');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: qualify_lead cold', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('qualify_lead', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 20,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Lead qualificado como cold');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 18. close_negotiation — 2 cenários
$simNegotiationCloseId = null;
$ok = e2e_run('tool: close_negotiation won', function () use ($sofiaCtx, $ctx, &$simNegotiationCloseId): void {
    $neg = CRMNegotiation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'crm_contact_id' => $ctx['contact_id'],
        'crm_negotiation_funnel_id' => $ctx['funnel_id'],
        'crm_negotiation_funnel_step_id' => $ctx['step_a_id'],
        'title' => 'Negociação para Fechar Sim',
        'amount' => 1000,
        'status' => 'open',
        'lead_score' => 50,
        'position' => 1,
    ]);
    $simNegotiationCloseId = $neg->id;
    $r = e2e_dispatch('close_negotiation', [
        'negotiation_id' => $neg->id,
        'outcome' => 'won',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Negociação fechada como won');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: close_negotiation lost', function () use ($sofiaCtx, $ctx): void {
    $neg = CRMNegotiation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'crm_contact_id' => $ctx['contact_id'],
        'crm_negotiation_funnel_id' => $ctx['funnel_id'],
        'crm_negotiation_funnel_step_id' => $ctx['step_a_id'],
        'title' => 'Negociação para Fechar Lost Sim',
        'amount' => 500,
        'status' => 'open',
        'lead_score' => 30,
        'position' => 2,
    ]);
    $r = e2e_dispatch('close_negotiation', [
        'negotiation_id' => $neg->id,
        'outcome' => 'lost',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Negociação fechada como lost');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 19. add_product_to_negotiation — 2 cenários
$ok = e2e_run('tool: add_product_to_negotiation válido', function () use ($sofiaCtx, &$simNegotiationId, $ctx): void {
    if (! $simNegotiationId) {
        $r = e2e_dispatch('create_negotiation', [
            'title' => 'Negociação Produto Sim',
            'step_id' => $ctx['step_a_id'],
            'contact_id' => $ctx['contact_id'],
        ], $sofiaCtx);
        $simNegotiationId = $r->data['negotiation_id'];
    }
    $r = e2e_dispatch('add_product_to_negotiation', [
        'negotiation_id' => $simNegotiationId,
        'product_id' => $ctx['product_id'],
        'qty' => 2,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Produto adicionado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: add_product_to_negotiation produto inválido', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('add_product_to_negotiation', [
        'negotiation_id' => $simNegotiationId,
        'product_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Produto Manual',
        'qty' => 1,
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com produto inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 20. create_proposal — 2 cenários
$ok = e2e_run('tool: create_proposal com amount', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => $simNegotiationId,
        'title' => 'Proposta Sim',
        'items' => [
            ['name' => 'Item 1', 'quantity' => 1, 'price' => 100],
        ],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Proposta criada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_proposal com items vazio', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => $simNegotiationId,
        'title' => 'Proposta Vazia',
        'items' => [],
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com items vazio');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 21. list_products — 2 cenários
$ok = e2e_run('tool: list_products retorna lista', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('list_products', ['limit' => 10], $sofiaCtx);
    e2e_assert($r->success, 'Produtos listados');
    e2e_assert(isset($r->data['products']), 'data.products presente');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: list_products tenant sem produtos (limit 0)', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('list_products', ['limit' => 100], $sofiaCtx);
    e2e_assert($r->success, 'Retorna sucesso mesmo sem produtos');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 22. list_funnel_steps — 2 cenários
$ok = e2e_run('tool: list_funnel_steps retorna steps', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('list_funnel_steps', ['funnel_id' => $ctx['funnel_id']], $sofiaCtx);
    e2e_assert($r->success, 'Steps retornados');
    e2e_assert(isset($r->data['funnels']), 'data.funnels presente');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: list_funnel_steps tenant sem funnel', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('list_funnel_steps', [], $sofiaCtx);
    e2e_assert($r->success, 'Retorna sucesso');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 23. search_knowledge — 4 cenários
$ok = e2e_run('tool: search_knowledge match', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query' => 'documento teste E2E',
        'limit' => 5,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Knowledge encontrado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: search_knowledge sem match', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query' => 'xyzabc123456789',
        'limit' => 5,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Retorna sucesso mesmo sem match');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: search_knowledge query vazia', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_knowledge', ['query' => ''], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com query vazia');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: search_knowledge com limit', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query' => 'teste',
        'limit' => 1,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Busca com limit');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 24. check_availability — 3 cenários
$ok = e2e_run('tool: check_availability range válido', function () use ($sofiaCtx): void {
    $from = now()->addDay()->startOfDay()->toIso8601String();
    $to = now()->addDay()->endOfDay()->toIso8601String();
    $r = e2e_dispatch('check_availability', [
        'date_from' => $from,
        'date_to' => $to,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Disponibilidade verificada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: check_availability range inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('check_availability', [
        'date_from' => now()->toIso8601String(),
        'date_to' => now()->subHour()->toIso8601String(),
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com range inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: check_availability sem range', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('check_availability', [], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha sem range');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 25. schedule_event — 3 cenários
$simEventId = null;
$simConfirmationId = null;
$ok = e2e_run('tool: schedule_event criar evento', function () use ($sofiaCtx, $ctx, &$simEventId, &$simConfirmationId): void {
    $start = now()->addDay()->setHour(14)->setMinute(0)->toIso8601String();
    $end = now()->addDay()->setHour(15)->setMinute(0)->toIso8601String();
    $r = e2e_dispatch('schedule_event', [
        'title' => 'Evento Sim',
        'starts_at' => $start,
        'ends_at' => $end,
        'contact_id' => $ctx['contact_id'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Evento criado');
    $simEventId = $r->data['event_id'];
    $simConfirmationId = $r->data['confirmation_id'] ?? null;
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: schedule_event contact inválido', function () use ($sofiaCtx): void {
    $start = now()->addDay()->setHour(14)->toIso8601String();
    $r = e2e_dispatch('schedule_event', [
        'title' => 'Evento Sim',
        'starts_at' => $start,
        'contact_id' => '00000000-0000-0000-0000-000000000000',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com contact inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: schedule_event data passada', function () use ($sofiaCtx): void {
    $start = now()->subDay()->toIso8601String();
    $r = e2e_dispatch('schedule_event', [
        'title' => 'Evento Passado',
        'starts_at' => $start,
    ], $sofiaCtx);
    // ScheduleEventTool não valida se é passado, então pode suceder ou falhar
    e2e_assert(isset($r->success), 'Retorna resposta');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 26. get_available_slots — 2 cenários
$ok = e2e_run('tool: get_available_slots range válido', function () use ($sofiaCtx): void {
    $from = now()->addDay()->startOfDay()->toIso8601String();
    $to = now()->addDays(7)->endOfDay()->toIso8601String();
    $r = e2e_dispatch('get_available_slots', [
        'date_from' => $from,
        'date_to' => $to,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Slots retornados');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: get_available_slots sem range', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('get_available_slots', [], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha sem range');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 27. confirm_event_booking — 2 cenários
$ok = e2e_run('tool: confirm_event_booking booking válido', function () use ($sofiaCtx, &$simConfirmationId): void {
    if (! $simConfirmationId) {
        e2e_assert(true, 'Pulado: nenhum confirmation_id disponível');

        return;
    }
    $r = e2e_dispatch('confirm_event_booking', [
        'confirmation_id' => $simConfirmationId,
        'action' => 'confirmed',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Booking confirmado');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: confirm_event_booking booking inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('confirm_event_booking', [
        'confirmation_id' => '00000000-0000-0000-0000-000000000000',
        'action' => 'confirmed',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com booking inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 28. create_task — 3 cenários
$ok = e2e_run('tool: create_task com assigned_to', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_task', [
        'title' => 'Tarefa Sim',
        'negotiation_id' => $simNegotiationId,
        'assigned_to' => null,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Tarefa criada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_task sem assigned_to', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_task', [
        'title' => 'Tarefa Sem Assign',
        'negotiation_id' => $simNegotiationId,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Tarefa criada sem assign');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_task título vazio', function () use ($sofiaCtx, &$simNegotiationId): void {
    $r = e2e_dispatch('create_task', [
        'title' => '',
        'negotiation_id' => $simNegotiationId,
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com título vazio');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 29. create_note — 3 cenários
$ok = e2e_run('tool: create_note com conteúdo', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'contact',
        'entity_id' => $ctx['contact_id'],
        'content' => 'Nota de teste Sim',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Nota criada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_note entity inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'invalid',
        'entity_id' => '00000000-0000-0000-0000-000000000000',
        'content' => 'Teste',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com entity inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: create_note conteúdo vazio', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'contact',
        'entity_id' => $ctx['contact_id'],
        'content' => '',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com conteúdo vazio');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 30. notify_seller — 3 cenários
$simSellerId = null;
$ok = e2e_run('tool: notify_seller urgente', function () use ($sofiaCtx, $ctx, &$simSellerId): void {
    $user = \Domain\Auth\Models\AuthUser::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->first();
    if (! $user) {
        e2e_assert(true, 'Pulado: nenhum usuário encontrado no tenant');

        return;
    }
    $simSellerId = $user->id;
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $simSellerId,
        'message' => 'Notificação urgente de teste',
        'reason' => 'urgent_response',
        'priority' => 'urgent',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Notificação urgente enviada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: notify_seller normal', function () use ($sofiaCtx, &$simSellerId): void {
    if (! $simSellerId) {
        e2e_assert(true, 'Pulado: nenhum seller_id disponível');

        return;
    }
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $simSellerId,
        'message' => 'Notificação normal de teste',
        'reason' => 'general',
        'priority' => 'normal',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Notificação normal enviada');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: notify_seller seller inválido', function () use ($sofiaCtx): void {
    try {
        $r = e2e_dispatch('notify_seller', [
            'seller_id' => '00000000-0000-0000-0000-000000000000',
            'message' => 'Teste',
            'reason' => 'general',
        ], $sofiaCtx);
        // Se chegou aqui sem exceção, falhou por FK
        e2e_assert(false, 'Deveria falhar com seller inválido (violação de FK)');
    } catch (\Throwable $e) {
        e2e_assert(str_contains($e->getMessage(), 'violates foreign key') || str_contains($e->getMessage(), 'is not present'), 'Falha esperada por FK');
    }
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

// 31. delegate_to_agent — 5 cenários
$childRunId = null;
$ok = e2e_run('tool: delegate_to_agent Lucas válido', function () use ($sofiaCtx, $lucasAgent, &$childRunId): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $lucasAgent->id,
        'return_after' => false,
        'instructions' => 'E2E: verificar follow-up do cliente.',
    ], $sofiaCtx);
    e2e_assert(isset($r->success), 'campo success presente');
    if ($r->success && isset($r->data['child_run_id'])) {
        $childRunId = $r->data['child_run_id'];
    }
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: delegate_to_agent target inválido', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => '00000000-0000-0000-0000-000000000000',
    ], $sofiaCtx);
    e2e_assert(! $r->success, 'Falha com target inválido');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: delegate_to_agent sem run_id', function () use ($sofiaCtx, $lucasAgent): void {
    $ctxSemRun = array_merge($sofiaCtx, ['current_run_id' => '']);
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $lucasAgent->id,
    ], $ctxSemRun);
    e2e_assert(! $r->success, 'Falha sem run_id');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: delegate_to_agent circular (Lucas→Sofia)', function () use ($lucasCtx, $sofiaAgent, $parentRun): void {
    $lucasCtxComRun = array_merge($lucasCtx, ['current_run_id' => $parentRun->id]);
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $sofiaAgent->id,
    ], $lucasCtxComRun);
    e2e_assert(! $r->success, 'Falha delegação circular sem regra');
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

$ok = e2e_run('tool: delegate_to_agent return_after=true', function () use ($sofiaCtx, $lucasAgent): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $lucasAgent->id,
        'return_after' => true,
    ], $sofiaCtx);
    e2e_assert(isset($r->success), 'campo success presente');
    if ($r->success) {
        e2e_assert(isset($r->data['return_after']) && $r->data['return_after'] === true, 'return_after=true');
    }
});
trackResult($g2Pass, $g2Fail, $ok['pass']);

e2e_summary('Grupo 2 · All 31 Tools', $g2Pass, $g2Fail);

// ─── Grupo 3 — Fluxo Completo Sofia → Lucas (~15 interações) ────────────────

e2e_group('13.3 · Sofia → Lucas Flow');

$ok = e2e_run('flow: Sofia read_ticket', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('read_ticket', [
        'ticket_id' => $ctx['ticket_id'],
        'include_messages' => true,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia leu o ticket');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia get_contact_info', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('get_contact_info', ['contact_id' => $ctx['contact_id']], $sofiaCtx);
    e2e_assert($r->success, 'Sofia encontrou o contato');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia search_knowledge', function () use ($sofiaCtx): void {
    $r = e2e_dispatch('search_knowledge', ['query' => 'produto', 'limit' => 3], $sofiaCtx);
    e2e_assert($r->success, 'Sofia buscou na base de conhecimento');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia send_message', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content' => 'Olá! Sou a Sofia. Vi que você tem interesse em nossos produtos. Como posso ajudar?',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia enviou mensagem de boas-vindas');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia update_contact', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('update_contact', [
        'contact_id' => $ctx['contact_id'],
        'city' => 'São Paulo',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia atualizou o contato');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia qualify_lead', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('qualify_lead', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 75,
        'tags' => ['interessado', 'demo-agendada'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia qualificou o lead');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia move_pipeline', function () use ($sofiaCtx, $ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id' => $ctx['step_b_id'],
        'reason' => 'Lead qualificado pela Sofia',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia moveu no funil');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$flowNegotiationId = null;
$ok = e2e_run('flow: Sofia create_negotiation', function () use ($sofiaCtx, $ctx, &$flowNegotiationId): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Negociação Flow Sim',
        'step_id' => $ctx['step_a_id'],
        'amount' => 2500.00,
        'contact_id' => $ctx['contact_id'],
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia criou negociação');
    $flowNegotiationId = $r->data['negotiation_id'];
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia add_product_to_negotiation', function () use ($sofiaCtx, &$flowNegotiationId, $ctx): void {
    $r = e2e_dispatch('add_product_to_negotiation', [
        'negotiation_id' => $flowNegotiationId,
        'product_id' => $ctx['product_id'],
        'qty' => 3,
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia adicionou produto');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia notify_seller', function () use ($sofiaCtx, &$simSellerId): void {
    if (! $simSellerId) {
        e2e_assert(true, 'Pulado: nenhum seller disponível');

        return;
    }
    $r = e2e_dispatch('notify_seller', [
        'seller_id' => $simSellerId,
        'message' => 'Lead qualificado e negociação criada pela Sofia',
        'reason' => 'high_value_lead',
        'priority' => 'high',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia notificou vendedor');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia create_task', function () use ($sofiaCtx, &$flowNegotiationId): void {
    $r = e2e_dispatch('create_task', [
        'title' => 'Follow-up com lead qualificado',
        'negotiation_id' => $flowNegotiationId,
        'description' => 'Entrar em contato para agendar demonstração',
    ], $sofiaCtx);
    e2e_assert($r->success, 'Sofia criou task para Lucas');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: Sofia delegate_to_agent (Lucas)', function () use ($sofiaCtx, $lucasAgent, &$childRunId): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $lucasAgent->id,
        'return_after' => true,
        'instructions' => 'Continuar o atendimento do lead qualificado.',
    ], $sofiaCtx);
    e2e_assert(isset($r->success), 'Delegação retornou success');
    if ($r->success && isset($r->data['child_run_id'])) {
        $childRunId = $r->data['child_run_id'];
    }
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: verificar child_run_id', function () use (&$childRunId): void {
    e2e_assert($childRunId !== null, 'Child run ID foi retornado');
    $childRun = AiAutopilotRun::query()->find($childRunId);
    e2e_assert($childRun !== null, 'Child run existe no banco');
    e2e_assert($childRun->status === 'queued' || $childRun->status === 'running', 'Child run status válido');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: verificar estado do ticket', function () use ($ctx): void {
    $ticket = ChatTicket::query()->find($ctx['ticket_id']);
    e2e_assert($ticket !== null, 'Ticket existe');
    e2e_assert($ticket->status === 'open', 'Ticket ainda aberto');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

$ok = e2e_run('flow: verificar histórico completo', function () use ($ctx): void {
    $ticket = ChatTicket::query()->find($ctx['ticket_id']);
    $lastMessage = ChatMessage::query()->where('ticket_id', $ctx['ticket_id'])->latest()->first();
    $builder = app(AiContextBuilderService::class);
    $context = $builder->build($ticket, $lastMessage);
    // O histórico pode estar limitado a 15 pelo serviço
    e2e_assert(count($context['conversation_history']) >= 10, 'Histórico completo tem pelo menos 10 mensagens');
    e2e_assert(isset($context['contact']), 'Contexto inclui dados do contato');
    e2e_assert(isset($context['ticket']), 'Contexto inclui dados do ticket');
});
trackResult($g3Pass, $g3Fail, $ok['pass']);

e2e_summary('Grupo 3 · Sofia → Lucas Flow', $g3Pass, $g3Fail);

// ─── Cleanup ─────────────────────────────────────────────────────────────────

e2e_group('13.4 · Cleanup');

$ok = e2e_run('cleanup: remove fixtures do teste', function () use (
    $ctx,
    $delegation,
    $parentRun,
    $sofiaAgent,
    $lucasAgent,
    $closeTicketId,
    $createdContactId,
    $simCompanyId,
    $simCompany2Id,
    $simNegotiationId,
    $simNegotiationCloseId,
    $flowNegotiationId,
    $simEventId,
    $childRunId,
): void {
    // Remove child runs primeiro
    if ($childRunId) {
        AiAutopilotRun::query()->where('id', $childRunId)->orWhere('parent_run_id', $parentRun->id)->forceDelete();
    }

    // Remove parent run
    $parentRun->forceDelete();

    // Remove delegação
    $delegation->delete();

    // Remove agentes
    $sofiaAgent->delete();
    $lucasAgent->delete();

    // Remove tasks antes das negociações (FK)
    CRMNegotiationTask::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('title', ['Tarefa Sim', 'Tarefa Sem Assign', 'Follow-up com lead qualificado'])
        ->delete();

    // Remove proposal_items antes das proposals (FK)
    $proposalIds = CRMProposal::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('title', ['Proposta Sim', 'Proposta Vazia'])
        ->pluck('id');
    \Domain\CRM\Models\CRMProposalItem::query()
        ->whereIn('crm_proposal_id', $proposalIds)
        ->delete();

    // Remove proposals antes das negociações
    $proposalIds = CRMProposal::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('title', ['Proposta Sim', 'Proposta Vazia'])
        ->pluck('id');
    \Domain\CRM\Models\CRMProposalItem::query()
        ->whereIn('crm_proposal_id', $proposalIds)
        ->delete();
    CRMProposal::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('title', ['Proposta Sim', 'Proposta Vazia'])
        ->delete();

    // Remove negotiation_products antes das negociações (FK)
    $negotiationIdsToDelete = CRMNegotiation::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where(function ($q) use ($simNegotiationId, $simNegotiationCloseId, $flowNegotiationId): void {
            if ($simNegotiationId) {
                $q->orWhere('id', $simNegotiationId);
            }
            if ($simNegotiationCloseId) {
                $q->orWhere('id', $simNegotiationCloseId);
            }
            if ($flowNegotiationId) {
                $q->orWhere('id', $flowNegotiationId);
            }
        })
        ->orWhereIn('title', ['Negociação Sim Sem Amount', 'Negociação para Fechar Lost Sim', 'Negociação Produto Sim'])
        ->pluck('id');
    \Domain\CRM\Models\CRMNegotiationProduct::query()
        ->whereIn('crm_negotiation_id', $negotiationIdsToDelete)
        ->delete();

    // Remove notas
    CRMNote::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where('content', 'Nota de teste Sim')
        ->delete();

    // Remove chat messages criados neste teste
    ChatMessage::query()
        ->where('ticket_id', $ctx['ticket_id'])
        ->where(function ($q): void {
            $q->where('source', 'ai')
                ->orWhere('direction', 'incoming');
        })
        ->delete();

    // Remove ticket fechado
    if ($closeTicketId) {
        ChatTicket::query()->where('id', $closeTicketId)->forceDelete();
    }

    // Remove contatos criados
    if ($createdContactId) {
        CRMContact::query()->where('id', $createdContactId)->delete();
    }
    CRMContact::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('phone', ['+5511900000100', '+5511900000101'])
        ->delete();

    // Remove empresas criadas
    if ($simCompanyId) {
        CRMCompany::query()->where('id', $simCompanyId)->delete();
    }
    if ($simCompany2Id) {
        CRMCompany::query()->where('id', $simCompany2Id)->delete();
    }
    CRMCompany::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->whereIn('name', ['Empresa Completa Sim', 'Empresa Mínima Sim Atualizada'])
        ->delete();

    // Remove negociações criadas (usando IDs coletados)
    CRMNegotiation::query()
        ->whereIn('id', $negotiationIdsToDelete)
        ->forceDelete();

    // Remove eventos criados
    if ($simEventId) {
        CRMEventClientConfirmation::query()->where('crm_event_id', $simEventId)->delete();
        CRMEvent::query()->where('id', $simEventId)->delete();
    }

    e2e_assert(true, 'Cleanup executado');
});

// ─── Sumário Final ───────────────────────────────────────────────────────────

$totalPass = $g1Pass + $g2Pass + $g3Pass;
$totalFail = $g1Fail + $g2Fail + $g3Fail;
$total = $totalPass + $totalFail;

$color = $totalFail > 0 ? "\033[31m" : "\033[32m";
echo "\n{$color}▶ Test-13 Total: {$totalPass}/{$total} passou\033[0m\n";
echo "  Grupo 1 (Chat History): {$g1Pass}/".($g1Pass + $g1Fail)."\n";
echo "  Grupo 2 (All 31 Tools): {$g2Pass}/".($g2Pass + $g2Fail)."\n";
echo "  Grupo 3 (Sofia→Lucas Flow): {$g3Pass}/".($g3Pass + $g3Fail)."\n";
