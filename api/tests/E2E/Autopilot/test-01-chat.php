<?php

/**
 * Teste E2E — Chat Tools
 * Cobre: send_message, read_ticket, transfer_to_human, close_ticket
 */

declare(strict_types=1);

use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Str;

require_once __DIR__ . '/helpers.php';

e2e_group('01 · Chat Tools');

$ctx = require __DIR__ . '/setup.php';

// ── send_message ─────────────────────────────────────────────────────────────

e2e_run('send_message: envia mensagem em ticket aberto', function () use ($ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content'   => '[E2E] Mensagem de teste do autopilot',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['message_id']), 'data.message_id presente');
    e2e_assert(isset($r->data['ticket_id']), 'data.ticket_id presente');
});

e2e_run('send_message: falha com content vazio', function () use ($ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content'   => '',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para content vazio');
});

e2e_run('send_message: falha com ticket inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('send_message', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
        'content'   => 'X',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para ticket inválido');
});

// ── read_ticket ───────────────────────────────────────────────────────────────

e2e_run('read_ticket: retorna dados do ticket', function () use ($ctx): void {
    $r = e2e_dispatch('read_ticket', [
        'ticket_id' => $ctx['ticket_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['ticket']['id']), 'data.ticket.id presente');
    e2e_assert(isset($r->data['ticket']['status']), 'data.ticket.status presente');
    e2e_assert($r->data['ticket']['status'] === 'open', "status=open (got: {$r->data['ticket']['status']})");
});

e2e_run('read_ticket: falha com ticket inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('read_ticket', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para ticket inválido');
});

// ── transfer_to_human ─────────────────────────────────────────────────────────

e2e_run('transfer_to_human: desativa bot e registra takeover', function () use ($ctx): void {
    // Garante bot ativo antes do teste
    ChatTicket::query()->where('id', $ctx['ticket_id'])->update(['is_bot_active' => true]);

    $r = e2e_dispatch('transfer_to_human', [
        'ticket_id' => $ctx['ticket_id'],
        'reason'    => 'Transferência E2E',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $ticket = ChatTicket::find($ctx['ticket_id']);
    e2e_assert(! $ticket->is_bot_active, 'is_bot_active=false após transferência');
});

// ── close_ticket ──────────────────────────────────────────────────────────────

e2e_run('close_ticket: fecha ticket aberto', function () use ($ctx): void {
    // Cria ticket específico para fechar (evita conflito com outros testes)
    $closeTicket = ChatTicket::query()->create([
        'id'          => (string) Str::orderedUuid(),
        'tenant_id'   => $ctx['tenant_id'],
        'contact_id'  => $ctx['contact_id'],
        'instance_id' => $ctx['instance_id'],
        'channel'     => 'whatsapp',
        'phone'       => '+5511900000099',
        'phone_e164'  => '+5511900000099',
        'remote_jid'  => '5511900000099@s.whatsapp.net',
        'push_name'   => 'Ticket Close E2E',
        'status'      => 'open',
        'priority'    => 'normal',
        'is_bot_active' => false,
    ]);

    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => $closeTicket->id,
        'reason'    => 'Fechado pelo E2E',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $refreshed = ChatTicket::find($closeTicket->id);
    e2e_assert($refreshed->status === 'closed', "status=closed (got: {$refreshed->status})");

    // Cleanup
    $closeTicket->forceDelete();
});

e2e_run('close_ticket: falha com ticket inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para ticket inválido');
});
