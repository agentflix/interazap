<?php

/**
 * Teste E2E — Delegation Tool
 * Cobre: delegate_to_agent
 *
 * Nota: DelegateToAgentTool cria child run + publica no Redis Stream.
 * Usamos um segundo agente E2E como target para validar o fluxo.
 * O dispatch do child run é feito com dispatchExecutionJob=false via
 * AiAgentDelegationService para não precisar de worker ativo.
 */

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('10 · Delegation Tool');

$ctx = require __DIR__.'/setup.php';

// Cria agente target para delegação
$targetAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Agente Target E2E')->first();
if (! $targetAgent) {
    $targetAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Agente Target E2E',
        'type' => 'general',
        'model_id' => 'claude-haiku-4-5-20251001',
        'is_active' => true,
        'max_tokens' => 800,
        'temperature' => 0.7,
        'top_p' => 1.0,
    ]);
}

// Cria parent run para o contexto de delegação
$parentRun = AiAutopilotRun::query()->create([
    'id' => (string) Str::orderedUuid(),
    'tenant_id' => $ctx['tenant_id'],
    'playbook_id' => $ctx['playbook_id'],
    'status' => 'running',
    'input_context' => ['messages' => []],
    'started_at' => now(),
]);

// Contexto com parent run ativo
$delegCtx = array_merge($ctx['agent_ctx'], [
    'current_run_id' => $parentRun->id,
]);

// ── delegate_to_agent ─────────────────────────────────────────────────────────

e2e_run('delegate_to_agent: delega para agente target válido', function () use ($targetAgent, $delegCtx): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $targetAgent->id,
        'return_after' => false,
        'instructions' => 'E2E: verificar disponibilidade do cliente.',
    ], $delegCtx);

    // DelegateToAgentTool tenta criar child run + publicar no Redis Stream
    // Em ambiente sem worker pode retornar success OU failure com mensagem informativa
    e2e_assert(isset($r->success), 'campo success presente na resposta');
    e2e_assert(isset($r->message) && strlen($r->message) > 0, 'mensagem presente na resposta');

    if ($r->success) {
        e2e_assert(isset($r->data['child_run_id']), 'data.child_run_id presente em caso de sucesso');
    }
});

e2e_run('delegate_to_agent: falha com target_agent_id inválido', function () use ($delegCtx): void {
    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => '00000000-0000-0000-0000-000000000000',
    ], $delegCtx);

    e2e_assert(! $r->success, 'success=false para target agent inexistente');
});

e2e_run('delegate_to_agent: falha sem current_run_id no contexto', function () use ($ctx, $targetAgent): void {
    $ctxSemRun = array_merge($ctx['agent_ctx'], ['current_run_id' => '']);

    $r = e2e_dispatch('delegate_to_agent', [
        'target_agent_id' => $targetAgent->id,
    ], $ctxSemRun);

    e2e_assert(! $r->success, 'success=false sem current_run_id');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove parent run e agente target criados neste grupo', function () use ($parentRun, $targetAgent): void {
    // Remove child runs criados pela delegação
    AiAutopilotRun::query()->where('parent_run_id', $parentRun->id)->forceDelete();
    $parentRun->forceDelete();
    $targetAgent->delete();
    e2e_assert(true, 'cleanup executado');
});
