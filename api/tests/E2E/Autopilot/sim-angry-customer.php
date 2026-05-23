<?php

/**
 * Simulação: Cliente bravo → 100 mensagens → Sofia escalona → Lucas fecha
 *
 * Execução:
 *   php artisan tinker --execute="require base_path('tests/E2E/Autopilot/sim-angry-customer.php');"
 *
 * Output: IDs reais de cada registro criado + evidências no banco.
 */

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiContextBuilderService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Support\Str;

// ─── Helpers locais ───────────────────────────────────────────────────────────

function dispatch_tool(string $tool, array $params, array $ctx): object
{
    return app(\Domain\Ai\Services\ToolDispatcherService::class)->dispatch($tool, $params, $ctx);
}

function line(string $msg = ''): void
{
    echo $msg."\n";
}
function section(string $title): void
{
    line();
    line("\033[1;33m━━━ {$title} ━━━\033[0m");
}
function ok(string $label, string $detail = ''): void
{
    echo "  \033[32m✓\033[0m {$label}".($detail !== '' && $detail !== '0' ? " → \033[36m{$detail}\033[0m" : '')."\n";
}
function fail(string $label, string $detail = ''): void
{
    echo "  \033[31m✗\033[0m {$label}".($detail !== '' && $detail !== '0' ? " → {$detail}" : '')."\n";
}
function simInfo(string $label, string $detail): void
{
    echo "  \033[90m·\033[0m {$label}: \033[33m{$detail}\033[0m\n";
}
function assertOk(bool $condition, string $label, string $detail = ''): void
{
    if ($condition) {
        ok($label, $detail);
    } else {
        fail($label, $detail);
    }
}

// ─── Banner ───────────────────────────────────────────────────────────────────

line();
line("\033[1;31m╔══════════════════════════════════════════════════════════════╗\033[0m");
line("\033[1;31m║   SIMULAÇÃO CLIENTE BRAVO — 100 MSGS — EVIDÊNCIAS REAIS      ║\033[0m");
line("\033[1;31m╚══════════════════════════════════════════════════════════════╝\033[0m");
line('  Data: '.date('Y-m-d H:i:s'));

// ═══════════════════════════════════════════════════════════════════════════
// SETUP — Tenant + Fixtures
// ═══════════════════════════════════════════════════════════════════════════

section('SETUP — Criando fixtures');

$ctx = require __DIR__.'/setup.php';
simInfo('Tenant ID', $ctx['tenant_id']);
simInfo('Instance ID', $ctx['instance_id']);

// Contato "Carlos Bravo" — o cliente irritado
$carlos = CRMContact::query()
    ->where('tenant_id', $ctx['tenant_id'])
    ->where('email', 'carlos.bravo@angry.test')
    ->first();

if (! $carlos) {
    $carlos = CRMContact::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Carlos Bravo',
        'phone' => '+5511988887777',
        'email' => 'carlos.bravo@angry.test',
        'is_active' => true,
    ]);
    ok('CRMContact criado', $carlos->id);
} else {
    ok('CRMContact reutilizado', $carlos->id);
}
simInfo('Contato', "Carlos Bravo ({$carlos->id})");

// Ticket dedicado para Carlos
$ticket = ChatTicket::query()
    ->where('tenant_id', $ctx['tenant_id'])
    ->where('phone', '+5511988887777')
    ->where('status', 'open')
    ->first();

if (! $ticket) {
    $ticket = ChatTicket::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'contact_id' => $carlos->id,
        'instance_id' => $ctx['instance_id'],
        'channel' => 'whatsapp',
        'phone' => '+5511988887777',
        'phone_e164' => '+5511988887777',
        'remote_jid' => '5511988887777@s.whatsapp.net',
        'push_name' => 'Carlos Bravo',
        'status' => 'open',
        'priority' => 'normal',
        'is_bot_active' => true,
    ]);
    ok('ChatTicket criado', $ticket->id);
} else {
    ChatTicket::query()->where('id', $ticket->id)->update(['is_bot_active' => true, 'status' => 'open']);
    ok('ChatTicket reutilizado', $ticket->id);
}
simInfo('Ticket', $ticket->id);

// Agentes Sofia + Lucas
$sofia = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Sofia Sim')->first();
if (! $sofia) {
    $sofia = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Sofia Sim',
        'type' => 'general',
        'model_id' => 'claude-haiku-4-5-20251001',
        'is_active' => true,
        'max_tokens' => 800,
        'temperature' => 0.5,
        'top_p' => 1.0,
    ]);
}
ok('Agente Sofia', $sofia->id);

$lucas = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Lucas Sim')->first();
if (! $lucas) {
    $lucas = AiAgent::query()->create([
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
ok('Agente Lucas', $lucas->id);

// Sync tools para ambos os agentes (obrigatório para dispatch funcionar)
$allToolEnums = [
    \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
    \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
    \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
    \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
    \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
    \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
    \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
    \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS,
    \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
    \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY,
    \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY,
    \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY,
    \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION,
    \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
    \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE,
    \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE,
    \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD,
    \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION,
    \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
    \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL,
    \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
    \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS,
    \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
    \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
    \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
    \Domain\Ai\Enums\AiToolEnum::GET_AVAILABLE_SLOTS,
    \Domain\Ai\Enums\AiToolEnum::CONFIRM_EVENT_BOOKING,
    \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
    \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
    \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
    \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
];

foreach ([$sofia->id, $lucas->id] as $agentId) {
    foreach ($allToolEnums as $toolName) {
        $exists = \Domain\Ai\Models\AiAutopilotTool::query()
            ->where('tenant_id', $ctx['tenant_id'])
            ->where('name', $toolName)
            ->exists();
        if (! $exists) {
            $cls = 'Domain\\Ai\\Tools\\'.Str::studly($toolName).'Tool';
            \Domain\Ai\Models\AiAutopilotTool::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $ctx['tenant_id'],
                'name' => $toolName,
                'display_name' => Str::headline($toolName),
                'handler_class' => class_exists($cls) ? $cls : null,
                'is_active' => true,
            ]);
        }
    }
    app(\Domain\Ai\Services\AiAgentToolPermissionService::class)
        ->syncAgentTools($ctx['tenant_id'], $agentId, $allToolEnums);
}
ok('Tools sincronizadas', 'Sofia + Lucas (31 tools cada)');

// Regra delegação Sofia → Lucas
$delegRule = AiAgentDelegation::query()
    ->where('tenant_id', $ctx['tenant_id'])
    ->where('source_agent_id', $sofia->id)
    ->where('target_agent_id', $lucas->id)
    ->first();

if (! $delegRule) {
    $delegRule = AiAgentDelegation::query()->create([
        'tenant_id' => $ctx['tenant_id'],
        'source_agent_id' => $sofia->id,
        'target_agent_id' => $lucas->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);
}
ok('Delegação Sofia→Lucas', $delegRule->id);

// Parent run
$parentRun = AiAutopilotRun::query()->create([
    'id' => (string) Str::orderedUuid(),
    'tenant_id' => $ctx['tenant_id'],
    'playbook_id' => $ctx['playbook_id'],
    'status' => 'running',
    'input_context' => ['ticket_id' => $ticket->id, 'contact_id' => $carlos->id],
    'started_at' => now(),
]);
ok('AiAutopilotRun (parent)', $parentRun->id);

$sofiaCtx = [
    'tenant_id' => $ctx['tenant_id'],
    'agent_id' => $sofia->id,
    'agent_name' => 'Sofia Sim',
    'current_run_id' => $parentRun->id,
    'ticket_id' => $ticket->id,
    'contact_id' => $carlos->id,
];

// Rastrear todos os IDs criados
$evidence = [
    'tenant_id' => $ctx['tenant_id'],
    'ticket_id' => $ticket->id,
    'contact_id' => $carlos->id,
    'agent_sofia_id' => $sofia->id,
    'agent_lucas_id' => $lucas->id,
    'delegation_rule_id' => $delegRule->id,
    'parent_run_id' => $parentRun->id,
    'messages' => [],
    'notes' => [],
    'tasks' => [],
    'negotiations' => [],
    'events' => [],
    'child_run_id' => null,
    'escalation_triggered' => false,
];

$msgCount = 0;

// ═══════════════════════════════════════════════════════════════════════════
// FASE 1 — Atendimento inicial (msgs 1-15): Carlos com problema de cobrança
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 1 — Primeiro Contato (msgs 1-15)');

$conversationScript = [
    // [direção, conteúdo, quem_envia]
    ['in',  'oi',                                                            'carlos'],
    ['out', 'Olá! Sou a Sofia da InteraZap. Em que posso ajudar?',          'sofia'],
    ['in',  'quero saber sobre meu contrato',                               'carlos'],
    ['out', 'Claro! Pode me informar seu CPF ou email para localizar?',     'sofia'],
    ['in',  'carlos.bravo@angry.test',                                      'carlos'],
    ['out', 'Localizei! Carlos Bravo. Qual a sua dúvida sobre o contrato?', 'sofia'],
    ['in',  'me cobraram errado esse mês',                                  'carlos'],
    ['out', 'Entendo. Deixa eu verificar as cobranças do seu plano.',       'sofia'],
    ['in',  'ja to esperando ha 10 minutos',                                'carlos'],
    ['out', 'Já estou verificando! Um momento por favor.',                  'sofia'],
    ['in',  'que demora isso',                                               'carlos'],
    ['out', 'Peço desculpas pela espera. Localizei 2 cobranças em aberto.', 'sofia'],
    ['in',  'que cobranças? nao autorizei nada',                            'carlos'],
    ['out', 'Uma em 05/mai R$299 e outra em 12/mai R$149. Ambas do plano.', 'sofia'],
    ['in',  'que absurdo! to cancelando agora',                             'carlos'],
];

foreach ($conversationScript as [$dir, $content]) {
    $msgNum = ++$msgCount;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $sofiaCtx);
        $msgId = $r->data['message_id'] ?? null;
        if ($msgId) {
            $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId, 'dir' => 'out', 'who' => 'Sofia'];
            ok("Msg #{$msgNum} (Sofia→Carlos)", $msgId);
        } else {
            fail("Msg #{$msgNum} (Sofia→Carlos)", $r->message);
        }
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Sofia)", $msg->id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 2 — Temperatura sobe (msgs 16-30): Carlos fica irritado
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 2 — Cliente Irritado (msgs 16-30)');

$angerScript = [
    ['in',  'QUERO FALAR COM UM HUMANO AGORA',                                       'carlos'],
    ['out', 'Carlos, entendo sua frustração. Vou verificar seu histórico completo.', 'sofia'],
    ['in',  'nao quero sofia quero humano AGORA',                                    'carlos'],
    ['out', 'Já estou escalando para nossa equipe especializada.',                   'sofia'],
    ['in',  'ja disseram isso antes e ninguem veio',                                 'carlos'],
    ['out', 'Confirmo: estou anotando urgência máxima no seu caso.',                 'sofia'],
    ['in',  'esse sistema é uma VERGONHA',                                           'carlos'],
    ['out', 'Sinto muito pela experiência. Seu ticket está marcado como urgente.',   'sofia'],
    ['in',  'quanto tempo mais vou esperar???',                                      'carlos'],
    ['out', 'Um especialista entrará em contato em até 5 minutos.',                  'sofia'],
    ['in',  'voces sempre falam isso',                                               'carlos'],
    ['out', 'Carlos, estou registrando uma nota de alta prioridade agora.',          'sofia'],
    ['in',  'quero estorno dos R$449 cobrados indevidamente',                        'carlos'],
    ['out', 'Estorno de R$449 anotado. Vou processar com urgência.',                'sofia'],
    ['in',  'e quero protocolo disso',                                               'carlos'],
];

foreach ($angerScript as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $sofiaCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Sofia'];
        ok("Msg #{$msgNum} (Sofia→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Sofia)", $msg->id);
    }
}

// ─── Sofia cria nota de ALERTA no ticket ─────────────────────────────────────

section('ALERTA — Sofia registra nota de escalada');

$r = dispatch_tool('create_note', [
    'entity_type' => 'contact',
    'entity_id' => $carlos->id,
    'content' => '🚨 ALERTA URGENTE: Carlos Bravo extremamente insatisfeito. Solicita estorno de R$449. Ameaça cancelamento. PRIORIDADE MÁXIMA. Transferir para Lucas imediatamente.',
], $sofiaCtx);

if ($r->success) {
    $evidence['notes'][] = $r->data['note_id'];
    ok('Nota de alerta criada', $r->data['note_id']);
    simInfo('Conteúdo', 'ALERTA URGENTE: estorno R$449, ameaça cancelamento');
} else {
    fail('Nota de alerta', $r->message);
}

// Sofia atualiza ticket para HIGH priority
ChatTicket::query()->where('id', $ticket->id)->update(['priority' => 'urgent']);
ok('Ticket priority → URGENT', $ticket->id);

// Sofia atualiza lead score baixo (cliente em risco)
$r = dispatch_tool('update_lead_score', [
    'negotiation_id' => $ctx['negotiation_id'],
    'score' => 10,
], $sofiaCtx);
assertOk($r->success, 'Lead score → 10 (cliente em risco)', $ctx['negotiation_id']);

// ═══════════════════════════════════════════════════════════════════════════
// FASE 3 — Sofia busca histórico e contexto (msgs 31-40)
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 3 — Sofia investiga (msgs 31-40)');

// Sofia lê o ticket completo
$r = dispatch_tool('read_ticket', ['ticket_id' => $ticket->id, 'include_messages' => true, 'message_limit' => 30], $sofiaCtx);
$ticketStatus = $r->data['ticket']['status'] ?? ($r->data['status'] ?? 'N/A');
$msgCount2 = count($r->data['messages'] ?? []);
assertOk($r->success, 'Sofia leu ticket completo', "status={$ticketStatus}");
simInfo('Ticket status', $ticketStatus);
simInfo('Mensagens no histórico', (string) $msgCount2);

// Sofia busca dados do contato
$r = dispatch_tool('get_contact_info', ['contact_id' => $carlos->id], $sofiaCtx);
assertOk($r->success, 'Sofia obteve dados de Carlos', $carlos->id);

// Sofia busca na knowledge base
$r = dispatch_tool('search_knowledge', ['query' => 'política de estorno cobrança indevida', 'limit' => 3], $sofiaCtx);
assertOk($r->success, 'Sofia buscou política de estorno', count($r->data['results']).' resultado(s)');

// Mensagens da fase 3
$phase3 = [
    ['in',  'alguem vai me responder ou nao???',                                       'carlos'],
    ['out', 'Carlos, localizei seu histórico. Você tem razão, houve duplicidade.',     'sofia'],
    ['in',  'falei que foi erro de voces',                                             'carlos'],
    ['out', 'Confirmo. Cobrança de R$149 foi indevida. Estorno em até 5 dias úteis.', 'sofia'],
    ['in',  'e os outros R$299?',                                                      'carlos'],
    ['out', 'R$299 refere-se ao seu plano mensal ativo. Esse é correto.',             'sofia'],
    ['in',  'ah, esses tao ok. mas o outro nao',                                      'carlos'],
    ['out', 'Entendido. Vou registrar o estorno de R$149 com protocolo.',             'sofia'],
    ['in',  'que protocolo?',                                                          'carlos'],
    ['out', sprintf('Protocolo: EST-%s. Guarde esse número.', strtoupper(Str::random(8))), 'sofia'],
];

foreach ($phase3 as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $sofiaCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Sofia'];
        ok("Msg #{$msgNum} (Sofia→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Sofia)", $msg->id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 4 — Sofia cria negociação de retenção + agenda reunião (msgs 41-55)
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 4 — Sofia cria negociação de retenção + agenda evento (msgs 41-55)');

// Sofia cria negociação de retenção
$r = dispatch_tool('create_negotiation', [
    'title' => 'Retenção — Carlos Bravo (estorno + upgrade)',
    'step_id' => $ctx['step_a_id'],
    'amount' => 3588.00,
    'contact_id' => $carlos->id,
], $sofiaCtx);

if ($r->success) {
    $evidence['negotiations'][] = $r->data['negotiation_id'];
    ok('Negociação de retenção criada', $r->data['negotiation_id']);
    simInfo('Título', 'Retenção — Carlos Bravo (estorno + upgrade)');
    simInfo('Valor', 'R$3.588,00/ano');
    $retentionNegId = $r->data['negotiation_id'];
} else {
    fail('Negociação de retenção', $r->message);
    $retentionNegId = $ctx['negotiation_id'];
}

// Sofia adiciona produto à negociação
$r = dispatch_tool('add_product_to_negotiation', [
    'negotiation_id' => $retentionNegId,
    'product_id' => $ctx['product_id'],
    'qty' => 1,
], $sofiaCtx);
assertOk($r->success, 'Produto adicionado à negociação', $ctx['product_id']);

// Sofia agenda reunião de retenção
$r = dispatch_tool('schedule_event', [
    'title' => 'Reunião de Retenção — Carlos Bravo',
    'starts_at' => now()->addDay()->setHour(10)->setMinute(0)->toIso8601String(),
    'ends_at' => now()->addDay()->setHour(11)->setMinute(0)->toIso8601String(),
    'contact_id' => $carlos->id,
], $sofiaCtx);

if ($r->success) {
    $evidence['events'][] = $r->data['event_id'];
    ok('Evento agendado', $r->data['event_id']);
    simInfo('Título', 'Reunião de Retenção — Carlos Bravo');
    simInfo('Data', now()->addDay()->format('d/m/Y H:i'));
    $simEventId = $r->data['event_id'];
    $simConfirmationId = $r->data['confirmation_id'] ?? null;

    // Confirmar o evento
    if ($simConfirmationId) {
        $rConfirm = dispatch_tool('confirm_event_booking', [
            'confirmation_id' => $simConfirmationId,
            'action' => 'confirmed',
        ], $sofiaCtx);
        assertOk($rConfirm->success, 'Booking confirmado', $simConfirmationId);
    }
} else {
    fail('Evento', $r->message);
    $simEventId = null;
}

// Sofia cria task para Lucas acompanhar
$r = dispatch_tool('create_task', [
    'title' => 'URGENTE: Acompanhar estorno Carlos Bravo + oferecer upgrade',
    'negotiation_id' => $retentionNegId,
    'description' => 'Cliente insatisfeito. Estorno R$149 aprovado. Oferecer upgrade com desconto para retenção.',
], $sofiaCtx);
if ($r->success) {
    $evidence['tasks'][] = $r->data['task_id'];
    ok('Task criada para Lucas', $r->data['task_id']);
}

// Mensagens fase 4
$phase4 = [
    ['in',  'ok, o estorno ta bom. mas to pensando em cancelar o plano',      'carlos'],
    ['out', 'Entendo, Carlos. Você usa o sistema há quanto tempo?',            'sofia'],
    ['in',  'uns 8 meses',                                                     'carlos'],
    ['out', 'Então você viu os resultados do sistema. Que parte decepcionou?', 'sofia'],
    ['in',  'o suporte é horrivel, fica caindo as integrações',               'carlos'],
    ['out', 'Anotei. Vou registrar um ticket técnico sobre as integrações.',   'sofia'],
    ['in',  'ja abri varios tickets e nunca resolvem',                         'carlos'],
    ['out', 'Entendo sua frustração. Você abriu tickets de integração antes?', 'sofia'],
    ['in',  'sim! protocolo TEC-2024-1892 e TEC-2024-2041',                   'carlos'],
    ['out', 'Vou referenciar esses tickets. Já escalei internamente.',         'sofia'],
    ['in',  'eu quero desconto ou vou cancelar',                               'carlos'],
    ['out', 'Posso oferecer 30% de desconto por 3 meses. Interesse?',         'sofia'],
    ['in',  'quanto fica?',                                                    'carlos'],
    ['out', 'De R$299/mês para R$209,30/mês por 3 meses.',                    'sofia'],
    ['in',  'e depois volta pra R$299?',                                       'carlos'],
];

foreach ($phase4 as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $sofiaCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Sofia'];
        ok("Msg #{$msgNum} (Sofia→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Sofia)", $msg->id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 5 — Tentativa de fechar + Sofia notifica Lucas (msgs 56-70)
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 5 — Sofia notifica Lucas (msgs 56-70)');

// Sofia notifica o vendedor Lucas (tenta buscar usuário real)
$sellerUser = \Domain\Auth\Models\AuthUser::query()->where('tenant_id', $ctx['tenant_id'])->first();
if ($sellerUser) {
    $r = dispatch_tool('notify_seller', [
        'seller_id' => $sellerUser->id,
        'message' => '🚨 URGENTE: Carlos Bravo ameaçando cancelar. Estorno R$149 aprovado. Oferecer desconto 30%/3meses. Negociação: '.$retentionNegId,
        'reason' => 'churn_risk',
        'priority' => 'urgent',
    ], $sofiaCtx);
    assertOk($r->success, 'Notificação urgente enviada ao vendedor', $sellerUser->id);
    $evidence['escalation_triggered'] = true;
    simInfo('Vendedor notificado', $sellerUser->id);
} else {
    simInfo('Notificação vendedor', 'Pulado — nenhum usuário no tenant E2E');
}

// Sofia qualifica lead como CHURN RISK
$r = dispatch_tool('qualify_lead', [
    'negotiation_id' => $retentionNegId,
    'score' => 15,
    'tags' => ['churn-risk', 'insatisfeito', 'estorno-aprovado', 'urgente'],
], $sofiaCtx);
assertOk($r->success, 'Lead qualificado como churn-risk', $retentionNegId);

// Sofia atualiza tags do contato
$r = dispatch_tool('update_contact_tags', [
    'contact_id' => $carlos->id,
    'tags' => ['churn-risk', 'reclamacao-financeira', 'estorno-pendente'],
    'action' => 'add',
], $sofiaCtx);
assertOk($r->success, 'Tags de risco adicionadas ao contato', $carlos->id);

// Mensagens fase 5
$phase5 = [
    ['out', 'Sim, após 3 meses volta para o plano normal. Aceita?',          'sofia'],
    ['in',  'precisso pensar. voce pode me dar mais informações',            'carlos'],
    ['out', 'Claro! Qual sua principal dúvida sobre o plano?',               'sofia'],
    ['in',  'quero saber exatamente o que muda no plano atual',              'carlos'],
    ['out', 'O plano Team inclui: 10 usuários, 5 integrações, IA incluída.', 'sofia'],
    ['in',  'e o meu plano atual tem isso?',                                 'carlos'],
    ['out', 'Seu plano atual tem 5 usuários e 2 integrações. Sem IA.',       'sofia'],
    ['in',  'entao o upgrade vale a pena?',                                  'carlos'],
    ['out', 'Sim! Com 30% desconto fica R$209/mês e você ganha IA nativa.', 'sofia'],
    ['in',  'mas a integração ja ta falhando no básico',                     'carlos'],
    ['out', 'Sobre isso vou conectar você com nosso especialista técnico.',  'sofia'],
    ['in',  'outro robô nao, quero humano',                                  'carlos'],
    ['out', 'Estou transferindo para o Lucas, nosso especialista.',          'sofia'],
    ['in',  'ok, mas que seja rápido',                                       'carlos'],
    ['out', 'Lucas assumirá agora. Ele tem todo o histórico.',               'sofia'],
];

foreach ($phase5 as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $sofiaCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Sofia'];
        ok("Msg #{$msgNum} (Sofia→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Sofia)", $msg->id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// DELEGAÇÃO — Sofia → Lucas
// ═══════════════════════════════════════════════════════════════════════════

section('DELEGAÇÃO — Sofia transfere para Lucas');

$r = dispatch_tool('delegate_to_agent', [
    'target_agent_id' => $lucas->id,
    'return_after' => false,
    'instructions' => implode(' | ', [
        'Cliente Carlos Bravo — CHURN RISK URGENTE',
        'Estorno R$149 APROVADO (processar imediatamente)',
        'Oferecer upgrade Team com 30% desconto por 3 meses (R$209/mês)',
        'Tickets técnicos pendentes: TEC-2024-1892 e TEC-2024-2041',
        'Reunião agendada para amanhã 10h',
        'Negociação: '.$retentionNegId,
    ]),
], $sofiaCtx);

if ($r->success) {
    $evidence['child_run_id'] = $r->data['child_run_id'];
    ok('DELEGAÇÃO SOFIA → LUCAS executada', $r->data['child_run_id']);
    simInfo('Child run ID', $r->data['child_run_id']);
    simInfo('Instrução', 'Estorno + upgrade + retenção');
} else {
    fail('DELEGAÇÃO SOFIA → LUCAS', $r->message);
    simInfo('Motivo', $r->message);
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 6 — Lucas assume (msgs 71-85)
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 6 — Lucas assume a conversa (msgs 71-85)');

// Lucas cria novo run se delegação funcionou
$lucasRunId = $evidence['child_run_id'] ?? $parentRun->id;
$lucasCtx = [
    'tenant_id' => $ctx['tenant_id'],
    'agent_id' => $lucas->id,
    'agent_name' => 'Lucas Sim',
    'current_run_id' => $lucasRunId,
    'ticket_id' => $ticket->id,
    'contact_id' => $carlos->id,
];

$phase6 = [
    ['out', 'Olá Carlos! Sou o Lucas, especialista da InteraZap. Já estou ciente de tudo!', 'lucas'],
    ['in',  'oi lucas, ja to cansado de esperar',                                           'carlos'],
    ['out', 'Entendo, Carlos. Primeiro: o estorno de R$149 já foi processado.',             'lucas'],
    ['in',  'quando vai cair na minha conta?',                                              'carlos'],
    ['out', 'Em até 5 dias úteis no seu cartão. Vou te enviar o comprovante.',             'lucas'],
    ['in',  'ok. e o desconto que a sofia falou?',                                         'carlos'],
    ['out', 'Confirmado! 30% de desconto por 3 meses → R$209,30/mês.',                    'lucas'],
    ['in',  'e as integrações que tao quebrando?',                                         'carlos'],
    ['out', 'Já abri ticket técnico urgente. Nossa equipe responde em 2h.',               'lucas'],
    ['in',  'você garante isso?',                                                          'carlos'],
    ['out', 'Garantia minha, Carlos. Meu ramal: (11) 9999-8888.',                        'lucas'],
    ['in',  'ta bom. aceito o desconto mas quero a resolução tecnica',                    'carlos'],
    ['out', 'Perfeito! Vou formalizar o upgrade + desconto agora.',                       'lucas'],
    ['in',  'manda confirmacao por email',                                                 'carlos'],
    ['out', 'E-mail enviado para carlos.bravo@angry.test. Verifique a caixa.',            'lucas'],
];

foreach ($phase6 as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    $agentCtx = $who === 'lucas' ? $lucasCtx : $sofiaCtx;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $agentCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Lucas'];
        ok("Msg #{$msgNum} (Lucas→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Lucas)", $msg->id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FASE 7 — Lucas fecha a venda + cria proposta (msgs 86-100)
// ═══════════════════════════════════════════════════════════════════════════

section('FASE 7 — Lucas fecha a venda (msgs 86-100)');

// Lucas cria proposta formal
$r = dispatch_tool('create_proposal', [
    'negotiation_id' => $retentionNegId,
    'title' => 'Proposta Retenção — Carlos Bravo (Upgrade + Desconto)',
    'items' => [
        ['name' => 'Plano Team — Desconto 30% (3 meses)', 'quantity' => 3, 'price' => 209.30],
        ['name' => 'Estorno Cobrança Indevida',            'quantity' => 1, 'price' => -149.00],
    ],
], $lucasCtx);
if ($r->success) {
    ok('Proposta criada por Lucas', $r->data['proposal_id']);
    simInfo('Itens', 'Upgrade 3×R$209,30 + Estorno -R$149');
} else {
    fail('Proposta', $r->message);
}

// Lucas move pipeline para Qualificação
$r = dispatch_tool('move_pipeline', [
    'negotiation_id' => $retentionNegId,
    'step_id' => $ctx['step_b_id'],
    'reason' => 'Carlos aceitou upgrade com desconto',
], $lucasCtx);
assertOk($r->success, 'Pipeline movido → Qualificação', $ctx['step_b_id']);

// Mensagens fase 7
$phase7 = [
    ['in',  'recebi o email, ta tudo correto',                                      'carlos'],
    ['out', 'Ótimo! Vou processar o upgrade agora.',                               'lucas'],
    ['in',  'quando ativa o novo plano?',                                          'carlos'],
    ['out', 'Em até 2 horas o plano Team já está ativo.',                          'lucas'],
    ['in',  'e a ia que voce mencionou, como usa?',                                'carlos'],
    ['out', 'A IA da InteraZap responde clientes automaticamente. Te mostro!',     'lucas'],
    ['in',  'interessante. e da pra conectar com meu crm?',                        'carlos'],
    ['out', 'Sim! Temos integração nativa com HubSpot, RD e Salesforce.',         'lucas'],
    ['in',  'uso hubspot',                                                          'carlos'],
    ['out', 'Perfeito! Integração HubSpot leva 5 minutos. Agendo onboarding?',    'lucas'],
    ['in',  'pode ser sim',                                                        'carlos'],
    ['out', 'Agendei onboarding técnico amanhã às 14h. Funcionou Carlos!',        'lucas'],
    ['in',  'tudo bem então. desculpa o estresse no começo',                      'carlos'],
    ['out', 'Sem problema! Seu feedback nos ajuda a melhorar.',                   'lucas'],
    ['in',  'ta. vou aguardar o estorno e o upgrade',                              'carlos'],
    ['out', 'Perfeito Carlos! Qualquer dúvida estou à disposição. Bom dia! 🤝',  'lucas'],
];

foreach ($phase7 as [$dir, $content, $who]) {
    $msgNum = ++$msgCount;
    $agentCtx = $who === 'lucas' ? $lucasCtx : $sofiaCtx;
    if ($dir === 'out') {
        $r = dispatch_tool('send_message', ['ticket_id' => $ticket->id, 'content' => $content], $agentCtx);
        $msgId = $r->data['message_id'] ?? null;
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msgId ?? 'FALHOU', 'dir' => 'out', 'who' => 'Lucas'];
        ok("Msg #{$msgNum} (Lucas→Carlos)", $msgId ?? 'FALHOU');
    } else {
        $msg = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'ticket_id' => $ticket->id,
            'contact_id' => $carlos->id,
            'content' => $content,
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'delivered',
        ]);
        $evidence['messages'][] = ['num' => $msgNum, 'id' => $msg->id, 'dir' => 'in', 'who' => 'Carlos'];
        ok("Msg #{$msgNum} (Carlos→Lucas)", $msg->id);
    }
}

// Lucas fecha negociação como WON
$r = dispatch_tool('close_negotiation', [
    'negotiation_id' => $retentionNegId,
    'outcome' => 'won',
], $lucasCtx);
assertOk($r->success, 'Negociação fechada como WON', $retentionNegId);

// ═══════════════════════════════════════════════════════════════════════════
// VERIFICAÇÕES FINAIS NO BANCO — Evidências
// ═══════════════════════════════════════════════════════════════════════════

section('VERIFICAÇÕES NO BANCO — Evidências Reais');

// Contar mensagens reais no ticket
$totalMsgsDb = ChatMessage::query()->where('ticket_id', $ticket->id)->count();
$incomingCount = ChatMessage::query()->where('ticket_id', $ticket->id)->where('direction', 'incoming')->count();
$outgoingCount = ChatMessage::query()->where('ticket_id', $ticket->id)->where('direction', 'outgoing')->count();

assertOk($totalMsgsDb >= 100, "Total de mensagens no banco: {$totalMsgsDb} (esperado >=100)", $ticket->id);
simInfo('Mensagens incoming (Carlos)', (string) $incomingCount);
simInfo('Mensagens outgoing (Agentes)', (string) $outgoingCount);

// Verificar ticket atualizado
$ticketDb = ChatTicket::query()->find($ticket->id);
simInfo('Ticket status', $ticketDb->status);
simInfo('Ticket priority', $ticketDb->priority);
assertOk($ticketDb->priority === 'urgent', 'Ticket priority = urgent', $ticketDb->priority);

// Verificar contato Carlos
$carlosDb = CRMContact::query()->find($carlos->id);
assertOk($carlosDb !== null, 'Contato Carlos existe no banco', $carlos->id);
$rawTags = $carlosDb->tags ?? [];
if (is_object($rawTags)) {
    $rawTags = $rawTags->toArray();
}
// flatten: tags podem ser strings ou arrays aninhados
$flatTags = [];
array_walk_recursive($rawTags, function ($v) use (&$flatTags): void {
    if (is_string($v)) {
        $flatTags[] = $v;
    }
});
simInfo('Contato tags', implode(', ', $flatTags) ?: '(sem tags)');

// Verificar negociação de retenção
$negDb = CRMNegotiation::query()->find($retentionNegId);
if ($negDb) {
    assertOk(true, 'Negociação de retenção existe', $retentionNegId);
    $negStatus = $negDb->getRawOriginal('status') ?? (is_object($negDb->status) ? $negDb->status->value ?? $negDb->status::class : (string) $negDb->status);
    simInfo('Negociação status', $negStatus);
    simInfo('Negociação título', (string) $negDb->title);
} else {
    fail('Negociação de retenção não encontrada', $retentionNegId);
}

// Verificar evento agendado
if ($simEventId) {
    $eventDb = CRMEvent::query()->find($simEventId);
    assertOk($eventDb !== null, 'Evento agendado existe', $simEventId);
    if ($eventDb) {
        simInfo('Evento título', $eventDb->title);
        simInfo('Evento data', $eventDb->starts_at->format('d/m/Y H:i'));
    }
}

// Verificar child run (delegação)
if ($evidence['child_run_id']) {
    $childRun = AiAutopilotRun::query()->find($evidence['child_run_id']);
    assertOk($childRun !== null, 'Child run da delegação existe', $evidence['child_run_id']);
    if ($childRun) {
        simInfo('Child run status', $childRun->status);
        simInfo('Child run parent', $childRun->parent_run_id ?? 'null');
    }
}

// Verificar contexto montado pelo AiContextBuilderService
$ticketFinal = ChatTicket::query()->find($ticket->id);
$lastMsg = ChatMessage::query()->where('ticket_id', $ticket->id)->latest()->first();
$builder = app(AiContextBuilderService::class);
$context = $builder->build($ticketFinal, $lastMsg);
assertOk(is_array($context), 'AiContextBuilderService monta contexto', 'OK');
$historyCount = count($context['conversation_history'] ?? []);
simInfo('Histórico no contexto (últimas 15)', (string) $historyCount);
assertOk($historyCount > 0, "Histórico não vazio: {$historyCount} msgs no contexto", 'OK');

// ═══════════════════════════════════════════════════════════════════════════
// RELATÓRIO FINAL DE EVIDÊNCIAS
// ═══════════════════════════════════════════════════════════════════════════

section('RELATÓRIO FINAL — IDs e Evidências');

line();
line("\033[1;37m  REGISTROS CRIADOS:\033[0m");
line('  ┌─────────────────────────────────────────────────────────────────');
line("  │ Tenant ID:         {$evidence['tenant_id']}");
line("  │ Ticket ID:         {$evidence['ticket_id']}");
line("  │ Contato (Carlos):  {$evidence['contact_id']}");
line("  │ Agente Sofia:      {$evidence['agent_sofia_id']}");
line("  │ Agente Lucas:      {$evidence['agent_lucas_id']}");
line("  │ Delegação Sofia→Lucas: {$evidence['delegation_rule_id']}");
line("  │ Parent Run ID:     {$evidence['parent_run_id']}");
line('  │ Child Run ID:      '.($evidence['child_run_id'] ?? 'N/A — delegação falhou'));
line("  │ Negociação:        {$retentionNegId}");
line('  │ Evento:            '.($simEventId ?? 'N/A'));
line('  │ Escalada Urgente:  '.($evidence['escalation_triggered'] ? 'SIM ✓' : 'Não (sem usuário no tenant E2E)'));
line('  ├─────────────────────────────────────────────────────────────────');
line("  │ Total msgs no script:   {$msgCount}");
line("  │ Total msgs no banco:    {$totalMsgsDb}");
line("  │   → Incoming (Carlos):  {$incomingCount}");
line("  │   → Outgoing (Agentes): {$outgoingCount}");
line('  ├─────────────────────────────────────────────────────────────────');

if (isset($evidence['notes']) && $evidence['notes'] !== []) {
    line('  │ Notas criadas:');
    foreach ($evidence['notes'] as $noteId) {
        line("  │   · {$noteId}");
    }
}
if (isset($evidence['tasks']) && $evidence['tasks'] !== []) {
    line('  │ Tasks criadas:');
    foreach ($evidence['tasks'] as $taskId) {
        line("  │   · {$taskId}");
    }
}

line('  └─────────────────────────────────────────────────────────────────');
line();

line("\033[1;37m  ÚLTIMAS 5 MENSAGENS NO BANCO (evidência do histórico):\033[0m");
$lastFive = ChatMessage::query()
    ->where('ticket_id', $ticket->id)->latest()
    ->orderByDesc('id')
    ->limit(5)
    ->get()
    ->reverse();

foreach ($lastFive as $m) {
    $dir = $m->direction === 'incoming' ? '←' : '→';
    $who = $m->direction === 'incoming' ? 'Carlos' : 'Agente';
    $preview = mb_substr($m->content ?? '', 0, 55);
    line("  {$dir} [{$m->id}] {$who}: {$preview}...");
}

line();
$msgCount >= 100
    ? line("\033[1;32m  ✓ META ATINGIDA: {$msgCount} mensagens simuladas | {$totalMsgsDb} no banco\033[0m")
    : line("\033[1;31m  ✗ Apenas {$msgCount} mensagens. Meta era 100.\033[0m");
line();
