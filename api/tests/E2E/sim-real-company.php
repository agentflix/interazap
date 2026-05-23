<?php

/**
 * Simulação no tenant REAL (AGENTFLX) — mensagens e CRM visíveis na empresa
 *
 * Execução:
 *   cd api && php artisan tinker --execute="require base_path('tests/E2E/sim-real-company.php');"
 */

declare(strict_types=1);

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Str;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function rc_dispatch(string $tool, array $params, array $ctx): object
{
    return app(\Domain\Ai\Services\ToolDispatcherService::class)->dispatch($tool, $params, $ctx);
}

function rc_line(string $msg = ''): void
{
    echo $msg."\n";
}
function rc_section(string $title): void
{
    rc_line();
    rc_line("\033[1;33m━━━ {$title} ━━━\033[0m");
}
function rc_ok(string $label, string $detail = ''): void
{
    echo "  \033[32m✓\033[0m {$label}".($detail !== '' && $detail !== '0' ? " → \033[36m{$detail}\033[0m" : '')."\n";
}
function rc_fail(string $label, string $detail = ''): void
{
    echo "  \033[31m✗\033[0m {$label}".($detail !== '' && $detail !== '0' ? " → \033[31m{$detail}\033[0m" : '')."\n";
}
function rc_info(string $label, string $detail): void
{
    echo "  \033[90m·\033[0m {$label}: \033[33m{$detail}\033[0m\n";
}
function rc_msg(string $direction, string $text): void
{
    $arrow = $direction === 'in' ? "\033[36m◀\033[0m" : "\033[32m▶\033[0m";
    echo "  {$arrow} ".substr($text, 0, 100)."\n";
}
function rc_assert(object $r, string $label): bool
{
    if ($r->success) {
        $detail = $r->data['id'] ?? $r->data['name'] ?? $r->message ?? '';
        rc_ok($label, (string) $detail);

        return true;
    }
    rc_fail($label, $r->message ?? 'sem mensagem de erro');

    return false;
}

// ─── Banner ───────────────────────────────────────────────────────────────────

rc_line();
rc_line("\033[1;32m╔══════════════════════════════════════════════════════════════╗\033[0m");
rc_line("\033[1;32m║   SIMULAÇÃO TENANT REAL (AGENTFLX) — VISÍVEL NA EMPRESA     ║\033[0m");
rc_line("\033[1;32m╚══════════════════════════════════════════════════════════════╝\033[0m");
rc_line('  Data: '.date('Y-m-d H:i:s'));

// ═══════════════════════════════════════════════════════════════════════════
// IDs REAIS DO TENANT AGENTFLX
// ═══════════════════════════════════════════════════════════════════════════

$TENANT_ID = '3453efd7-1344-4551-999b-340b37b8d501';
$AGENT_ATEND = 'a1ce7572-c5b8-48d2-8d20-334361db6b8f'; // Atendimento
$AGENT_VENDAS = 'a1ce7572-d3a1-4bf1-a719-9107339c9cc3'; // Vendas
$STEP_LEAD = 'a1ce7572-a003-498a-92e1-050a15e62c26'; // Novo Lead
$STEP_QUAL = 'a1ce7572-a17c-4bf0-bcf1-89ad89b03313'; // Qualificação
$STEP_PROP = 'a1ce7572-a20e-4f33-9119-257de4df6715'; // Proposta
$STEP_GANHO = 'a1ce7572-a290-4f24-894a-e6015909c2c7'; // Fechado-Ganho
$PLAYBOOK_ID = 'a1d01980-43a5-473e-8279-0a49c340f1b1';
$SELLER_ID = '019e397e-95bd-70db-a734-7a320689f815'; // Rosa Lopes Pontes

$ALL_TOOLS = [
    'send_message', 'read_ticket', 'transfer_to_human', 'close_ticket',
    'create_contact', 'get_contact_info', 'update_contact', 'update_contact_tags',
    'search_contacts', 'link_contact_to_company',
    'create_company', 'update_company',
    'create_negotiation', 'get_negotiation_info', 'move_pipeline',
    'update_lead_score', 'qualify_lead', 'close_negotiation', 'add_product_to_negotiation',
    'create_proposal', 'list_products', 'list_funnel_steps',
    'search_knowledge',
    'check_availability', 'schedule_event',
    'create_task', 'create_note',
    'notify_seller',
    'delegate_to_agent',
];

// ═══════════════════════════════════════════════════════════════════════════
// SYNC TOOLS
// ═══════════════════════════════════════════════════════════════════════════

rc_section('SYNC TOOLS');

$permSvc = app(AiAgentToolPermissionService::class);
$permSvc->syncAgentTools($TENANT_ID, $AGENT_ATEND, $ALL_TOOLS);
$permSvc->syncAgentTools($TENANT_ID, $AGENT_VENDAS, $ALL_TOOLS);

rc_ok('Atendimento + Vendas: 29 tools cada');

// ═══════════════════════════════════════════════════════════════════════════
// CONTATO — Carlos Demo
// ═══════════════════════════════════════════════════════════════════════════

rc_section('CONTATO');

$carlos = CRMContact::query()
    ->where('tenant_id', $TENANT_ID)
    ->where('email', 'carlos.demo@simulacao.real')
    ->first();

if (! $carlos) {
    $carlos = CRMContact::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $TENANT_ID,
        'name' => 'Carlos Demo',
        'phone' => '+5511977776666',
        'email' => 'carlos.demo@simulacao.real',
        'is_active' => true,
    ]);
    rc_ok('CRMContact criado', $carlos->id);
} else {
    rc_ok('CRMContact reutilizado', $carlos->id);
}

// ═══════════════════════════════════════════════════════════════════════════
// TICKET — canal web (aparece na UI)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('TICKET');

$ticket = ChatTicket::query()->create([
    'id' => (string) Str::orderedUuid(),
    'tenant_id' => $TENANT_ID,
    'contact_id' => $carlos->id,
    'instance_id' => null,
    'channel' => 'web',
    'phone' => '+5511977776666',
    'phone_e164' => '+5511977776666',
    'push_name' => 'Carlos Demo',
    'status' => 'pending',
    'priority' => 'normal',
    'is_bot_active' => true,
    'current_ai_agent_id' => $AGENT_ATEND,
    'started_at' => now(),
]);
rc_ok('ChatTicket criado', $ticket->id);

$run = AiAutopilotRun::query()->create([
    'id' => (string) Str::orderedUuid(),
    'tenant_id' => $TENANT_ID,
    'playbook_id' => $PLAYBOOK_ID,
    'status' => 'running',
    'input_context' => ['ticket_id' => $ticket->id, 'agent_id' => $AGENT_ATEND, 'messages' => []],
    'started_at' => now(),
]);
rc_ok('AiAutopilotRun', $run->id);

// ─── Contextos ────────────────────────────────────────────────────────────

$atendCtx = [
    'tenant_id' => $TENANT_ID,
    'agent_id' => $AGENT_ATEND,
    'ticket_id' => $ticket->id,
    'contact_id' => $carlos->id,
    'playbook_id' => $PLAYBOOK_ID,
    'current_run_id' => $run->id,
];

$vendasCtx = array_merge($atendCtx, ['agent_id' => $AGENT_VENDAS]);

// ─── Incoming messages ────────────────────────────────────────────────────

$msgCount = 0;
$incoming = function (string $text) use ($ticket, $carlos, $TENANT_ID, &$msgCount): void {
    $msgCount++;
    ChatMessage::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $TENANT_ID,
        'ticket_id' => $ticket->id,
        'contact_id' => $carlos->id,
        'content' => $text,
        'type' => 'text',
        'direction' => 'incoming',
        'is_from_contact' => true,
        'source' => 'web',
        'status' => 'read',
        'sent_at' => now(),
    ]);
    rc_msg('in', $text);
};

// ═══════════════════════════════════════════════════════════════════════════
// FASE 1 — ABERTURA (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 1 — ABERTURA');

$incoming('Oi, preciso de ajuda com meu pedido!');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Olá, Carlos! Aqui é o Atendimento InteraZap. Como posso ajudar?',
], $atendCtx), 'send_message #1');
$msgCount++;

$r = rc_dispatch('read_ticket', ['ticket_id' => $ticket->id], $atendCtx);
rc_ok('read_ticket', 'status='.($r->data['ticket']['status'] ?? $r->data['status'] ?? 'N/A'));

$incoming('Quero comprar um plano maior.');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Ótimo, Carlos! Posso te informar melhor sobre os planos. Vou te conectar com nosso especialista Lucas.',
], $atendCtx), 'send_message #2');
$msgCount++;

$r = rc_dispatch('get_contact_info', ['identifier' => $carlos->id], $atendCtx);
rc_ok('get_contact_info', 'name='.($r->data['name'] ?? 'N/A'));

$r = rc_dispatch('search_knowledge', ['query' => 'planos preços Enterprise Professional'], $atendCtx);
rc_ok('search_knowledge #1', count($r->data ?? []).' artigos');

$incoming('Ok, aguardando!');

// ═══════════════════════════════════════════════════════════════════════════
// FASE 2 — CRIAÇÃO DA NEGOCIAÇÃO (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 2 — CRIAÇÃO DA NEGOCIAÇÃO');

// create_negotiation: step_id é OBRIGATÓRIO (não funnel_id)
$r = rc_dispatch('create_negotiation', [
    'title' => 'Upgrade Plano — Carlos Demo',
    'step_id' => $STEP_LEAD,   // ← step_id correto
    'contact_id' => $carlos->id,
    'amount' => 1200.00,
], $atendCtx);
$negId = $r->data['id'] ?? $r->data['negotiation_id'] ?? null;
rc_assert($r, 'create_negotiation');
if ($negId) {
    rc_info('Negociação ID', $negId);
}

$r = rc_dispatch('get_negotiation_info', ['negotiation_id' => $negId], $atendCtx);
rc_assert($r, 'get_negotiation_info');

// qualify_lead: opera sobre negotiation_id, não contact_id
$r = rc_dispatch('qualify_lead', [
    'negotiation_id' => $negId,
    'score' => 75,
    'step_id' => $STEP_QUAL,
    'tags' => ['lead', 'interessado', 'upgrade'],
], $atendCtx);
rc_assert($r, 'qualify_lead');

// update_lead_score: opera sobre negotiation_id
$r = rc_dispatch('update_lead_score', [
    'negotiation_id' => $negId,
    'score' => 85,
    'reason' => 'Cliente ativo querendo upgrade',
], $atendCtx);
rc_assert($r, 'update_lead_score → 85');

// update_contact_tags: contact_id é correto aqui
$r = rc_dispatch('update_contact_tags', [
    'contact_id' => $carlos->id,
    'tags' => ['lead', 'interessado', 'upgrade'],
    'action' => 'add',
], $atendCtx);
rc_assert($r, 'update_contact_tags');

$r = rc_dispatch('list_funnel_steps', ['funnel_id' => 'a1ce7572-9dfd-49a1-b2e4-272dd39f68f2'], $atendCtx);
rc_ok('list_funnel_steps', count($r->data ?? []).' steps');

$incoming('Quero o plano Enterprise.');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Perfeito, Carlos! O Enterprise é o nosso plano mais completo. Deixa eu checar as informações para você.',
], $atendCtx), 'send_message #3');
$msgCount++;

// ═══════════════════════════════════════════════════════════════════════════
// FASE 3 — PROPOSTA COMERCIAL (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 3 — PROPOSTA COMERCIAL');

$r = rc_dispatch('list_products', [], $atendCtx);
$products = $r->data ?? [];
rc_ok('list_products', count($products).' produtos');

// add_product_to_negotiation: usa negotiation_id
if ($negId) {
    if (count($products) > 0) {
        $prod = array_values($products)[0];
        $prodId = $prod['id'] ?? null;
        if ($prodId) {
            $r = rc_dispatch('add_product_to_negotiation', [
                'negotiation_id' => $negId,
                'product_id' => $prodId,
                'qty' => 1,
                'unit_price' => 1200.00,
            ], $atendCtx);
            rc_assert($r, 'add_product_to_negotiation');
        }
    } else {
        // Sem produtos cadastrados — adiciona item livre pelo nome
        $r = rc_dispatch('add_product_to_negotiation', [
            'negotiation_id' => $negId,
            'name' => 'Plano Enterprise',
            'qty' => 1,
            'unit_price' => 1200.00,
        ], $atendCtx);
        rc_assert($r, 'add_product_to_negotiation (item livre)');
    }
}

// move_pipeline: negotiation_id, não contact_id
$r = rc_dispatch('move_pipeline', [
    'negotiation_id' => $negId,
    'step_id' => $STEP_PROP,
], $atendCtx);
rc_assert($r, 'move_pipeline → Proposta');

// create_proposal: usa negotiation_id
$r = rc_dispatch('create_proposal', [
    'negotiation_id' => $negId,
    'title' => 'Proposta Enterprise — Carlos Demo',
    'items' => [['name' => 'Plano Enterprise', 'quantity' => 1, 'unit_price' => 1200.00]],
], $atendCtx);
rc_assert($r, 'create_proposal');

$incoming('Quanto custa exatamente?');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Carlos, o Plano Enterprise está R$ 1.200/mês. Com pagamento anual tem 10% off (R$ 12.960/ano). Posso enviar a proposta formal?',
], $atendCtx), 'send_message #4');
$msgCount++;

$incoming('Sim, pode enviar!');

// create_note: entity_type + entity_id (não ticket_id)
$r = rc_dispatch('create_note', [
    'entity_type' => 'negotiation',
    'entity_id' => $negId,
    'content' => 'Cliente interessado em Plano Enterprise. Proposta enviada. Score=85, lead quente.',
], $atendCtx);
rc_assert($r, 'create_note #1 (negociação)');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Proposta enviada para carlos.demo@simulacao.real! Nosso time vai entrar em contato em até 24h.',
], $atendCtx), 'send_message #5');
$msgCount++;

// ═══════════════════════════════════════════════════════════════════════════
// FASE 4 — ESCALADA PARA VENDAS (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 4 — ESCALADA PARA VENDAS');

$incoming('Ótimo! Mas preciso de um desconto extra.');

// notify_seller: seller_id (UUID do vendedor), message, reason
$r = rc_dispatch('notify_seller', [
    'seller_id' => $SELLER_ID,
    'message' => 'Carlos Demo solicitou desconto no Enterprise (R$1.200/mês). Score 85. Prioridade ALTA.',
    'reason' => 'high_value_lead',
    'priority' => 'high',
], $atendCtx);
rc_assert($r, 'notify_seller #1 (desconto solicitado)');

// create_task: negotiation_id obrigatório (não ticket_id)
$r = rc_dispatch('create_task', [
    'negotiation_id' => $negId,
    'title' => 'Aprovar desconto Enterprise — Carlos Demo',
    'description' => 'Cliente quer desconto. Score 85. Autorizado até 15%.',
    'due_date' => now()->addDay()->toIso8601String(),
], $atendCtx);
rc_assert($r, 'create_task #1 (aprovar desconto)');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Entendo, Carlos! Vou acionar nosso especialista Lucas que tem autorização para condições especiais.',
], $atendCtx), 'send_message #6');
$msgCount++;

$incoming('Ok, quanto tempo vai demorar?');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Máximo 15 minutos! Lucas vai entrar agora mesmo na conversa.',
], $atendCtx), 'send_message #7');
$msgCount++;

// Vendas entra na conversa
rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Olá Carlos! Sou o Lucas do time comercial. Vi que você quer uma condição especial no Enterprise. Posso oferecer 12% de desconto — o que acha?',
], $vendasCtx), 'send_message #8 (Vendas→Carlos)');
$msgCount++;

$incoming('Quero 15%!');

$r = rc_dispatch('update_lead_score', [
    'negotiation_id' => $negId,
    'score' => 92,
    'reason' => 'Negociando ativamente, quer fechar hoje',
], $vendasCtx);
rc_assert($r, 'update_lead_score → 92 (hot!)');

// ═══════════════════════════════════════════════════════════════════════════
// FASE 5 — FECHAMENTO (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 5 — FECHAMENTO DO NEGÓCIO');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Carlos, consegui aprovação de 15% para você! Fica R$ 1.020/mês ou R$ 11.016/ano. Fechamos?',
], $vendasCtx), 'send_message #9');
$msgCount++;

$incoming('Sim! Vamos fechar!');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Excelente, Carlos! Processando o fechamento agora. Contrato digital segue no seu e-mail.',
], $vendasCtx), 'send_message #10');
$msgCount++;

// close_negotiation: outcome=won
$r = rc_dispatch('close_negotiation', [
    'negotiation_id' => $negId,
    'outcome' => 'won',
    'reason' => 'Fechado com 15% desconto. Plano Enterprise anual.',
], $vendasCtx);
rc_assert($r, 'close_negotiation (WON)');

// move_pipeline → Fechado-Ganho
$r = rc_dispatch('move_pipeline', [
    'negotiation_id' => $negId,
    'step_id' => $STEP_GANHO,
], $vendasCtx);
rc_assert($r, 'move_pipeline → Fechado-Ganho');

// update tags no contato (contact_id correto)
$r = rc_dispatch('update_contact_tags', [
    'contact_id' => $carlos->id,
    'tags' => ['cliente', 'enterprise', 'fechado'],
    'action' => 'add',
], $vendasCtx);
rc_assert($r, 'update_contact_tags [cliente, enterprise, fechado]');

// create_note na negociação
$r = rc_dispatch('create_note', [
    'entity_type' => 'negotiation',
    'entity_id' => $negId,
    'content' => 'VENDA FECHADA! Plano Enterprise R$1.020/mês (15% desconto). Contrato enviado para carlos.demo@simulacao.real',
], $vendasCtx);
rc_assert($r, 'create_note #2 (venda fechada)');

// notify_seller sobre fechamento
$r = rc_dispatch('notify_seller', [
    'seller_id' => $SELLER_ID,
    'message' => 'VENDA FECHADA! Carlos Demo — Enterprise R$1.020/mês. Ativar onboarding.',
    'reason' => 'general',
    'priority' => 'normal',
], $vendasCtx);
rc_assert($r, 'notify_seller #2 (venda fechada)');

// create_task para onboarding
$r = rc_dispatch('create_task', [
    'negotiation_id' => $negId,
    'title' => 'Onboarding Enterprise — Carlos Demo',
    'description' => 'Sessão de onboarding. Prazo: 48h após assinatura.',
    'due_date' => now()->addDays(2)->toIso8601String(),
], $vendasCtx);
rc_assert($r, 'create_task #2 (onboarding)');

$incoming('Perfeito! Quando começa meu acesso?');

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Carlos, acesso ativado em até 2h após confirmação do pagamento. Time de onboarding entra em contato. Muito obrigado!',
], $vendasCtx), 'send_message #11');
$msgCount++;

// ═══════════════════════════════════════════════════════════════════════════
// FASE 6 — AGENDAMENTO E EXTRAS (8 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 6 — AGENDAMENTO E EXTRAS');

$incoming('Podemos agendar reunião de kickoff?');

$r = rc_dispatch('check_availability', [
    'date_from' => now()->addDay()->format('Y-m-d').' 09:00:00',
    'date_to' => now()->addDay()->format('Y-m-d').' 18:00:00',
], $vendasCtx);
rc_ok('check_availability', $r->success ? 'ok' : ($r->message ?? 'fail'));

$r = rc_dispatch('schedule_event', [
    'contact_id' => $carlos->id,
    'title' => 'Kickoff Enterprise — Carlos Demo',
    'description' => 'Sessão de onboarding e configuração inicial.',
    'starts_at' => now()->addDays(2)->format('Y-m-d').' 10:00:00',
    'ends_at' => now()->addDays(2)->format('Y-m-d').' 11:00:00',
], $vendasCtx);
rc_assert($r, 'schedule_event');
$eventId = $r->data['id'] ?? $r->data['event_id'] ?? null;
if ($eventId) {
    rc_info('Event ID', $eventId);
}

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Kickoff agendado para '.now()->addDays(2)->format('d/m/Y').' às 10h! Link da reunião chega por e-mail.',
], $vendasCtx), 'send_message #12');
$msgCount++;

$incoming('Ótimo, obrigado!');

$r = rc_dispatch('search_knowledge', ['query' => 'onboarding enterprise configuração inicial'], $vendasCtx);
rc_ok('search_knowledge #2', count($r->data ?? []).' artigos');

// create_note no contato
$r = rc_dispatch('create_note', [
    'entity_type' => 'contact',
    'entity_id' => $carlos->id,
    'content' => 'Kickoff agendado '.now()->addDays(2)->format('d/m/Y').' 10h. Cliente satisfeito. Onboarding iniciado.',
], $vendasCtx);
rc_assert($r, 'create_note #3 (contato)');

$r = rc_dispatch('update_contact', [
    'contact_id' => $carlos->id,
    'name' => 'Carlos Demo (Enterprise)',
    'notes' => 'Cliente Enterprise fechado em '.date('Y-m-d').'. Kickoff '.now()->addDays(2)->format('d/m/Y').'.',
], $vendasCtx);
rc_assert($r, 'update_contact (nome + notas)');

$r = rc_dispatch('read_ticket', ['ticket_id' => $ticket->id, 'include_messages' => true], $vendasCtx);
$totalMsgs = count($r->data['messages'] ?? []);
rc_ok('read_ticket final', "msgs no ticket: {$totalMsgs}");

rc_assert(rc_dispatch('send_message', [
    'ticket_id' => $ticket->id,
    'content' => 'Seja bem-vindo à família InteraZap Enterprise, Carlos! Qualquer dúvida estamos aqui.',
], $vendasCtx), 'send_message #13');
$msgCount++;

// ═══════════════════════════════════════════════════════════════════════════
// FASE 7 — ENCERRAMENTO (5 interações)
// ═══════════════════════════════════════════════════════════════════════════

rc_section('FASE 7 — ENCERRAMENTO');

$incoming('Valeu! Até o kickoff.');

// search_contacts para demonstrar
$r = rc_dispatch('search_contacts', ['query' => 'Carlos Demo'], $vendasCtx);
rc_ok('search_contacts', count($r->data ?? []).' resultados');

// qualify_lead final com score máximo
$r = rc_dispatch('qualify_lead', [
    'negotiation_id' => $negId,
    'score' => 100,
    'tags' => ['cliente', 'enterprise'],
], $vendasCtx);
rc_assert($r, 'qualify_lead final (score=100)');

// transfer_to_human — encerra atendimento automatizado
$r = rc_dispatch('transfer_to_human', [
    'ticket_id' => $ticket->id,
    'reason' => 'Venda fechada. Ticket em acompanhamento pós-venda.',
], $vendasCtx);
rc_assert($r, 'transfer_to_human (bot desativado)');

$run->update(['status' => 'completed', 'completed_at' => now(), 'output' => [
    'result' => 'venda_fechada_enterprise',
    'tokens' => ['total' => 2500],
]]);
rc_ok('AiAutopilotRun → completed');

// ═══════════════════════════════════════════════════════════════════════════
// EVIDÊNCIAS
// ═══════════════════════════════════════════════════════════════════════════

rc_line();
rc_line("\033[1;36m╔══════════════════════════════════════════════════════════════╗\033[0m");
rc_line("\033[1;36m║                    EVIDÊNCIAS — IDs REAIS                   ║\033[0m");
rc_line("\033[1;36m╚══════════════════════════════════════════════════════════════╝\033[0m");

rc_info('Tenant (AGENTFLX)', $TENANT_ID);
rc_info('Contato Carlos Demo', $carlos->id);
rc_info('Ticket (webchat)', $ticket->id);
rc_info('AiAutopilotRun', $run->id);
if ($negId) {
    rc_info('CRMNegotiation (WON)', $negId);
}
if ($eventId ?? null) {
    rc_info('CRMEvent (kickoff)', $eventId);
}

rc_line();

$dbMsgs = DB::table('chat_messages')->where('ticket_id', $ticket->id)->count();
$dbNotes = DB::table('crm_notes')->where('entity_type', 'negotiation')->where('entity_id', $negId)->count();
$dbTasks = DB::table('crm_negotiation_tasks')->where('crm_negotiation_id', $negId)->count();
$negFmt = $negId ? DB::table('crm_negotiations')->where('id', $negId)->value('status') : 'N/A';
$botAtivo = DB::table('chat_tickets')->where('id', $ticket->id)->value('is_bot_active');

rc_info('Mensagens no banco (ticket)', (string) $dbMsgs);
rc_info('Notas na negociação', (string) $dbNotes);
rc_info('Tasks na negociação', (string) $dbTasks);
rc_info('Status da negociação', (string) $negFmt);
rc_info('Bot ativo?', $botAtivo ? 'sim' : 'não (transferido para humano)');
rc_info('Mensagens simuladas (incoming)', (string) $msgCount);

rc_line();
rc_line("\033[1;32m  Abra o sistema → ticket: {$ticket->id}\033[0m");
rc_line("\033[1;32m  Ou filtre: Carlos Demo / carlos.demo@simulacao.real\033[0m");
rc_line();
