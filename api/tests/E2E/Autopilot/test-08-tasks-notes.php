<?php

/**
 * Teste E2E — Task & Note Tools
 * Cobre: create_task, create_note
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMNote;

require_once __DIR__.'/helpers.php';

e2e_group('08 · Task & Note Tools');

$ctx = require __DIR__.'/setup.php';

$taskId = null;
$noteId = null;

// ── create_task ───────────────────────────────────────────────────────────────

e2e_run('create_task: cria tarefa em negociação', function () use ($ctx, &$taskId): void {
    $r = e2e_dispatch('create_task', [
        'title' => 'Tarefa E2E — Ligar para cliente',
        'negotiation_id' => $ctx['negotiation_id'],
        'due_date' => '2030-12-15',
        'description' => 'Criado pelo teste E2E',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['task_id']), 'data.task_id presente');

    $taskId = $r->data['task_id'];

    $task = \Domain\CRM\Models\CRMNegotiationTask::query()->find($taskId);
    e2e_assert($task !== null, 'tarefa persistida no banco');
    e2e_assert($task->title === 'Tarefa E2E — Ligar para cliente', 'title correto');
});

e2e_run('create_task: falha sem title', function () use ($ctx): void {
    $r = e2e_dispatch('create_task', [
        'negotiation_id' => $ctx['negotiation_id'],
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem title');
});

e2e_run('create_task: falha com negotiation inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('create_task', [
        'title' => 'Tarefa Inválida',
        'negotiation_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para negotiation inválida');
});

// ── create_note ───────────────────────────────────────────────────────────────

e2e_run('create_note: cria nota em negociação', function () use ($ctx, &$noteId): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'negotiation',
        'entity_id' => $ctx['negotiation_id'],
        'content' => 'Nota criada pelo teste E2E do autopilot.',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['note_id']), 'data.note_id presente');

    $noteId = $r->data['note_id'];

    $note = \Domain\CRM\Models\CRMNote::query()->find($noteId);
    e2e_assert($note !== null, 'nota persistida no banco');
});

e2e_run('create_note: cria nota em contato', function () use ($ctx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'contact',
        'entity_id' => $ctx['contact_id'],
        'content' => 'Nota de contato pelo E2E.',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['note_id']), 'data.note_id presente');

    // Cleanup inline
    if (isset($r->data['note_id'])) {
        CRMNote::query()->where('id', $r->data['note_id'])->delete();
    }
});

e2e_run('create_note: falha com entity_type inválido', function () use ($ctx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'invalid_type',
        'entity_id' => $ctx['negotiation_id'],
        'content' => 'X',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para entity_type inválido');
});

e2e_run('create_note: falha com content vazio', function () use ($ctx): void {
    $r = e2e_dispatch('create_note', [
        'entity_type' => 'negotiation',
        'entity_id' => $ctx['negotiation_id'],
        'content' => '',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para content vazio');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove task e note criados neste grupo', function () use (&$taskId, &$noteId): void {
    if ($taskId) {
        CRMNegotiationTask::query()->where('id', $taskId)->delete();
    }
    if ($noteId) {
        CRMNote::query()->where('id', $noteId)->delete();
    }
    e2e_assert(true, 'cleanup executado');
});
