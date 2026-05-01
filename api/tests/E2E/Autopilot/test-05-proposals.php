<?php

/**
 * Teste E2E — Proposal & Product Tools
 * Cobre: create_proposal, list_products, list_funnel_steps
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMProposal;

require_once __DIR__ . '/helpers.php';

e2e_group('05 · Proposal & Product Tools');

$ctx = require __DIR__ . '/setup.php';

// ── list_products ─────────────────────────────────────────────────────────────

e2e_run('list_products: retorna catálogo de produtos do tenant', function () use ($ctx): void {
    $r = e2e_dispatch('list_products', [], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['products']), 'data.products presente');
    e2e_assert(is_array($r->data['products']), 'data.products é array');
    e2e_assert(count($r->data['products']) >= 1, 'pelo menos 1 produto (Produto E2E)');
});

// ── list_funnel_steps ─────────────────────────────────────────────────────────

e2e_run('list_funnel_steps: retorna todos os funnels e steps', function () use ($ctx): void {
    $r = e2e_dispatch('list_funnel_steps', [], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['funnels']), 'data.funnels presente');
    e2e_assert(count($r->data['funnels']) >= 1, 'pelo menos 1 funil (E2E Funil de Vendas)');
});

e2e_run('list_funnel_steps: filtra por funnel_id específico', function () use ($ctx): void {
    $r = e2e_dispatch('list_funnel_steps', [
        'funnel_id' => $ctx['funnel_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(count($r->data['funnels']) === 1, '1 funil retornado ao filtrar por ID');
    e2e_assert(count($r->data['funnels'][0]['steps']) >= 2, 'pelo menos 2 steps (Prospecção + Qualificação)');
});

// ── create_proposal ───────────────────────────────────────────────────────────

$proposalId = null;

e2e_run('create_proposal: cria proposta com produto', function () use ($ctx, &$proposalId): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => $ctx['negotiation_id'],
        'title'          => 'Proposta E2E',
        'items'          => [
            [
                'product_id'  => $ctx['product_id'],
                'quantity'    => 1,
                'unit_price'  => 99.90,
                'discount'    => 0,
                'description' => 'Item E2E',
            ],
        ],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['proposal_id']), 'data.proposal_id presente');
    e2e_assert(isset($r->data['items_count']), 'data.items_count presente');
    e2e_assert((int) $r->data['items_count'] >= 1, 'pelo menos 1 item na proposta');

    $proposalId = $r->data['proposal_id'];
});

e2e_run('create_proposal: falha sem items', function () use ($ctx): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => $ctx['negotiation_id'],
        'title'          => 'Proposta Inválida',
        'items'          => [],
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem items');
});

e2e_run('create_proposal: falha com negotiation inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('create_proposal', [
        'negotiation_id' => '00000000-0000-0000-0000-000000000000',
        'title'          => 'Proposta Inválida',
        'items'          => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para negotiation inválida');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove proposta criada neste grupo', function () use (&$proposalId): void {
    if ($proposalId) {
        CRMProposal::query()->where('id', $proposalId)->delete();
    }
    e2e_assert(true, 'cleanup executado');
});
