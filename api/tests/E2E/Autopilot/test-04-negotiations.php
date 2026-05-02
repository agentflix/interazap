<?php

/**
 * Teste E2E — Negotiation Tools
 * Cobre: create_negotiation, get_negotiation_info, move_pipeline,
 *         update_lead_score, qualify_lead, close_negotiation, add_product_to_negotiation
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('04 · Negotiation Tools');

$ctx = require __DIR__.'/setup.php';

// ── create_negotiation ────────────────────────────────────────────────────────

$createdNegId = null;

e2e_run('create_negotiation: cria negociação com title e step_id', function () use ($ctx, &$createdNegId): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Negociação Criada E2E',
        'step_id' => $ctx['step_a_id'],
        'contact_id' => $ctx['contact_id'],
        'amount' => 1200.00,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['negotiation_id']), 'data.negotiation_id presente');

    $createdNegId = $r->data['negotiation_id'];
});

e2e_run('create_negotiation: falha sem title', function () use ($ctx): void {
    $r = e2e_dispatch('create_negotiation', [
        'step_id' => $ctx['step_a_id'],
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem title');
});

e2e_run('create_negotiation: falha com step inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('create_negotiation', [
        'title' => 'Neg Inválida',
        'step_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para step inválido');
});

// ── get_negotiation_info ──────────────────────────────────────────────────────

e2e_run('get_negotiation_info: retorna dados da negociação', function () use ($ctx): void {
    $r = e2e_dispatch('get_negotiation_info', [
        'negotiation_id' => $ctx['negotiation_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['negotiation']['id']), 'data.negotiation.id presente');
    e2e_assert(isset($r->data['negotiation']['title']), 'data.negotiation.title presente');
});

e2e_run('get_negotiation_info: falha com ID inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('get_negotiation_info', [
        'negotiation_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para negotiation inválida');
});

// ── move_pipeline ─────────────────────────────────────────────────────────────

e2e_run('move_pipeline: move negociação para outro step', function () use ($ctx): void {
    $r = e2e_dispatch('move_pipeline', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id' => $ctx['step_b_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $neg = CRMNegotiation::find($ctx['negotiation_id']);
    e2e_assert($neg->crm_negotiation_funnel_step_id === $ctx['step_b_id'], 'step_id atualizado no banco');

    // Restaura step original
    $neg->update(['crm_negotiation_funnel_step_id' => $ctx['step_a_id']]);
});

// ── update_lead_score ─────────────────────────────────────────────────────────

e2e_run('update_lead_score: atualiza score da negociação', function () use ($ctx): void {
    $r = e2e_dispatch('update_lead_score', [
        'negotiation_id' => $ctx['negotiation_id'],
        'score' => 85,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $neg = CRMNegotiation::find($ctx['negotiation_id']);
    e2e_assert((int) $neg->lead_score === 85, "lead_score=85 (got: {$neg->lead_score})");
});

// ── qualify_lead ──────────────────────────────────────────────────────────────

e2e_run('qualify_lead: qualifica lead com step e score', function () use ($ctx): void {
    $r = e2e_dispatch('qualify_lead', [
        'negotiation_id' => $ctx['negotiation_id'],
        'step_id' => $ctx['step_b_id'],
        'lead_score' => 90,
        'tags' => ['qualificado-e2e'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    // Restaura step original
    CRMNegotiation::where('id', $ctx['negotiation_id'])
        ->update(['crm_negotiation_funnel_step_id' => $ctx['step_a_id'], 'lead_score' => 50]);
});

// ── add_product_to_negotiation ────────────────────────────────────────────────

e2e_run('add_product_to_negotiation: adiciona produto à negociação', function () use ($ctx): void {
    $r = e2e_dispatch('add_product_to_negotiation', [
        'negotiation_id' => $ctx['negotiation_id'],
        'product_id' => $ctx['product_id'],
        'qty' => 2,
        'unit_price' => 99.90,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['negotiation_product_id']), 'data.negotiation_product_id presente');
});

// ── close_negotiation ─────────────────────────────────────────────────────────

e2e_run('close_negotiation: fecha negociação recém-criada', function () use ($ctx, &$createdNegId): void {
    if (! $createdNegId) {
        // Cria fallback para este teste
        $neg = CRMNegotiation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'crm_contact_id' => $ctx['contact_id'],
            'crm_negotiation_funnel_id' => $ctx['funnel_id'],
            'crm_negotiation_funnel_step_id' => $ctx['step_a_id'],
            'title' => 'Neg Close Fallback E2E',
            'status' => 'open',
            'lead_score' => 0,
            'position' => 1,
        ]);
        $createdNegId = $neg->id;
    }

    $r = e2e_dispatch('close_negotiation', [
        'negotiation_id' => $createdNegId,
        'outcome' => 'won',
        'reason' => 'Fechado pelo teste E2E',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $neg = CRMNegotiation::find($createdNegId);
    e2e_assert($neg->status->value !== 'open', "status não é mais open (got: {$neg->status->value})");
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove negociação criada neste grupo', function () use (&$createdNegId): void {
    if ($createdNegId) {
        CRMNegotiation::query()->where('id', $createdNegId)->forceDelete();
    }
    e2e_assert(true, 'cleanup executado');
});
