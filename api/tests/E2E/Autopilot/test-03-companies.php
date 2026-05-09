<?php

/**
 * Teste E2E — Company Tools
 * Cobre: create_company, update_company
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMCompany;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('03 · Company Tools');

$ctx = require __DIR__.'/setup.php';

$companyId = null;

// ── create_company ────────────────────────────────────────────────────────────

e2e_run('create_company: cria empresa com nome', function () use ($ctx, &$companyId): void {
    $r = e2e_dispatch('create_company', [
        'name' => 'Empresa E2E LTDA',
        'website' => 'https://e2e.interazap.test',
        'industry' => 'Tecnologia',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['company_id']), 'data.company_id presente');

    $companyId = $r->data['company_id'];
});

e2e_run('create_company: falha sem nome', function () use ($ctx): void {
    $r = e2e_dispatch('create_company', [], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem name');
});

// ── update_company ────────────────────────────────────────────────────────────

e2e_run('update_company: atualiza telefone da empresa', function () use ($ctx, &$companyId): void {
    if (! $companyId) {
        // Cria empresa fallback se create falhou
        $company = CRMCompany::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'name' => 'Empresa Update Fallback E2E',
        ]);
        $companyId = $company->id;
    }

    $r = e2e_dispatch('update_company', [
        'company_id' => $companyId,
        'phone' => '+5511999990001',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $company = \Domain\CRM\Models\CRMCompany::query()->find($companyId);
    e2e_assert($company->phone === '+5511999990001', 'phone atualizado no banco');
});

e2e_run('update_company: falha com ID inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('update_company', [
        'company_id' => '00000000-0000-0000-0000-000000000000',
        'phone' => '+5511999990001',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para company inválida');
});

// ── Cleanup ───────────────────────────────────────────────────────────────────

e2e_run('cleanup: remove empresa criada neste grupo', function () use (&$companyId): void {
    if ($companyId) {
        CRMCompany::query()->where('id', $companyId)->delete();
    }
    e2e_assert(true, 'cleanup executado');
});
