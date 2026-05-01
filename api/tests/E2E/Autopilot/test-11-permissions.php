<?php

/**
 * Teste E2E — Permission Matrix
 *
 * Valida que cada role rejeita as tools proibidas.
 * Cada cenário usa ToolDispatcherService diretamente com role específico.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

e2e_group('11 · Permission Matrix');

$ctx = require __DIR__ . '/setup.php';

/**
 * Monta contexto com role específico.
 *
 * @param  array<string, mixed>  $baseCtx
 */
function ctx_with_role(array $baseCtx, string $role): array
{
    return array_merge($baseCtx, ['agent_role' => $role]);
}

// ── sales_qualifier: NÃO pode usar close_ticket ────────────────────────────────

e2e_run('sales_qualifier: rejeitada ao usar close_ticket', function () use ($ctx): void {
    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => $ctx['ticket_id'],
    ], ctx_with_role($ctx['agent_ctx'], 'sales_qualifier'));

    e2e_assert(! $r->success, 'success=false — close_ticket bloqueada para sales_qualifier');
    e2e_assert(str_contains(strtolower($r->message), 'not allowed') || str_contains(strtolower($r->message), 'allowed'), "mensagem de permission negada (got: {$r->message})");
});

// ── support_l1: NÃO pode usar update_lead_score ────────────────────────────────

e2e_run('support_l1: rejeitada ao usar update_lead_score', function () use ($ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score'          => 80,
    ], ctx_with_role($ctx['agent_ctx'], 'support_l1'));

    e2e_assert(! $r->success, 'success=false — update_lead_score bloqueada para support_l1');
});

// ── support_l1: NÃO pode usar update_contact_tags ─────────────────────────────

e2e_run('support_l1: rejeitada ao usar update_contact_tags', function () use ($ctx): void {
    $r = e2e_dispatch('update_contact_tags', [
        'contact_id' => $ctx['contact_id'],
        'tags'       => ['tag-test'],
    ], ctx_with_role($ctx['agent_ctx'], 'support_l1'));

    e2e_assert(! $r->success, 'success=false — update_contact_tags bloqueada para support_l1');
});

// ── support_l1: NÃO pode usar move_pipeline ───────────────────────────────────

e2e_run('support_l1: rejeitada ao usar move_pipeline', function () use ($ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id'        => $ctx['step_b_id'],
    ], ctx_with_role($ctx['agent_ctx'], 'support_l1'));

    e2e_assert(! $r->success, 'success=false — move_pipeline bloqueada para support_l1');
});

// ── appointment: NÃO pode usar close_ticket ───────────────────────────────────

e2e_run('appointment: rejeitada ao usar close_ticket', function () use ($ctx): void {
    $r = e2e_dispatch('close_ticket', [
        'ticket_id' => $ctx['ticket_id'],
    ], ctx_with_role($ctx['agent_ctx'], 'appointment'));

    e2e_assert(! $r->success, 'success=false — close_ticket bloqueada para appointment');
});

// ── appointment: NÃO pode usar create_negotiation ─────────────────────────────

e2e_run('appointment: rejeitada ao usar create_negotiation', function () use ($ctx): void {
    $r = e2e_dispatch('create_negotiation', [
        'title'   => 'Neg Bloqueada',
        'step_id' => $ctx['step_a_id'],
    ], ctx_with_role($ctx['agent_ctx'], 'appointment'));

    e2e_assert(! $r->success, 'success=false — create_negotiation bloqueada para appointment');
});

// ── post_sales: NÃO pode usar update_lead_score ───────────────────────────────

e2e_run('post_sales: rejeitada ao usar update_lead_score', function () use ($ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score'          => 70,
    ], ctx_with_role($ctx['agent_ctx'], 'post_sales'));

    e2e_assert(! $r->success, 'success=false — update_lead_score bloqueada para post_sales');
});

// ── post_sales: NÃO pode usar create_proposal ────────────────────────────────

e2e_run('post_sales: rejeitada ao usar create_proposal', function () use ($ctx): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => $ctx['negotiation_id'],
        'title'          => 'Proposta Bloqueada',
        'items'          => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
    ], ctx_with_role($ctx['agent_ctx'], 'post_sales'));

    e2e_assert(! $r->success, 'success=false — create_proposal bloqueada para post_sales');
});

// ── Validação positiva: cada role pode usar send_message ─────────────────────

foreach (['sales_qualifier', 'support_l1', 'cs_retention', 'post_sales', 'appointment', 'general'] as $role) {
    e2e_run("$role: autorizada a usar send_message", function () use ($ctx, $role): void {
        $r = e2e_dispatch('send_message', [
            'ticket_id' => $ctx['ticket_id'],
            'content'   => "[E2E] Teste de permissão para role {$role}",
        ], ctx_with_role($ctx['agent_ctx'], $role));

        // Pode falhar por lógica da tool (ticket não acessível), mas NÃO por permissão
        $blockedByPermission = ! $r->success && str_contains(strtolower($r->message), 'not allowed');
        e2e_assert(! $blockedByPermission, "send_message NÃO deve ser bloqueada por permissão para {$role} (got: {$r->message})");
    });
}

// ── Validação: sem tenant_id retorna failure ──────────────────────────────────

e2e_run('sem tenant_id: dispatch retorna failure sem exception', function () use ($ctx): void {
    $ctxSemTenant = array_merge($ctx['agent_ctx'], ['tenant_id' => '']);

    $r = e2e_dispatch('send_message', [
        'ticket_id' => $ctx['ticket_id'],
        'content'   => 'X',
    ], $ctxSemTenant);

    e2e_assert(! $r->success, 'success=false sem tenant_id');
});
