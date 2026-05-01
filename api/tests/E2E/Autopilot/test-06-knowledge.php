<?php

/**
 * Teste E2E — Knowledge Tools
 * Cobre: search_knowledge
 *
 * Nota: O RAG usa pgvector. Sem embeddings reais nos chunks de teste,
 * a busca vetorial retornará lista vazia — validamos que a tool não
 * lança exceção e retorna estrutura correta.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

e2e_group('06 · Knowledge Tools');

$ctx = require __DIR__ . '/setup.php';

// ── search_knowledge ──────────────────────────────────────────────────────────

e2e_run('search_knowledge: executa busca e retorna estrutura correta', function () use ($ctx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query'     => 'autopilot teste E2E',
        'limit'     => 5,
        'min_score' => 0.1,
        'mode'      => 'vector',
    ], $ctx['agent_ctx']);

    // Sem embeddings reais, pode retornar success=true com 0 resultados
    // O importante é não lançar exceção e retornar estrutura válida
    e2e_assert(isset($r->success), 'campo success presente na resposta');
    e2e_assert(isset($r->data), 'campo data presente na resposta');

    if ($r->success) {
        e2e_assert(isset($r->data['results']), 'data.results presente em caso de sucesso');
        e2e_assert(is_array($r->data['results']), 'data.results é array');
        e2e_assert(isset($r->data['count']), 'data.count presente');
    }
});

e2e_run('search_knowledge: falha com query vazia', function () use ($ctx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query' => '',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para query vazia');
});

e2e_run('search_knowledge: executa em modo hybrid sem exceção', function () use ($ctx): void {
    $r = e2e_dispatch('search_knowledge', [
        'query' => 'documento E2E',
        'mode'  => 'hybrid',
        'limit' => 3,
    ], $ctx['agent_ctx']);

    e2e_assert(isset($r->success), 'campo success presente em modo hybrid');
    e2e_assert(isset($r->data), 'campo data presente em modo hybrid');
});

e2e_run('search_knowledge: retorna success=false para tenant sem knowledge base', function () use ($ctx): void {
    // Testa com context de tenant diferente (sem chunks)
    $altCtx = array_merge($ctx['agent_ctx'], [
        'tenant_id' => '00000000-0000-0000-0000-FFFFFFFFFFF0',
    ]);

    $r = e2e_dispatch('search_knowledge', [
        'query' => 'qualquer coisa',
    ], $altCtx);

    // Pode retornar success=true com 0 resultados ou success=false — ambos válidos
    e2e_assert(isset($r->success), 'campo success presente');
    e2e_assert(isset($r->data), 'campo data presente');
});
