<?php

/**
 * Teste E2E — Contact Tools
 * Cobre: create_contact, get_contact_info, update_contact,
 *         update_contact_tags, search_contacts, link_contact_to_company
 */

declare(strict_types=1);

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('02 · Contact Tools');

$ctx = require __DIR__.'/setup.php';

// ── create_contact ────────────────────────────────────────────────────────────

$createdContactId = null;

e2e_run('create_contact: cria contato com nome e telefone', function () use ($ctx, &$createdContactId): void {
    $r = e2e_dispatch('create_contact', [
        'name' => 'Novo Contato E2E',
        'phone' => '+5511900000002',
        'email' => 'novo-e2e@test.com',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['contact_id']), 'data.contact_id presente');
    e2e_assert(isset($r->data['name']), 'data.name presente');

    $createdContactId = $r->data['contact_id'];
});

e2e_run('create_contact: falha sem nome', function () use ($ctx): void {
    $r = e2e_dispatch('create_contact', [
        'phone' => '+5511900000003',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false sem name');
});

// ── get_contact_info ──────────────────────────────────────────────────────────

e2e_run('get_contact_info: retorna dados do contato', function () use ($ctx): void {
    $r = e2e_dispatch('get_contact_info', [
        'contact_id' => $ctx['contact_id'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['contact']['id']), 'data.contact.id presente');
    e2e_assert(isset($r->data['contact']['name']), 'data.contact.name presente');
});

e2e_run('get_contact_info: falha com ID inexistente', function () use ($ctx): void {
    $r = e2e_dispatch('get_contact_info', [
        'contact_id' => '00000000-0000-0000-0000-000000000000',
    ], $ctx['agent_ctx']);

    e2e_assert(! $r->success, 'success=false para contact inválido');
});

// ── update_contact ────────────────────────────────────────────────────────────

e2e_run('update_contact: atualiza email do contato', function () use ($ctx): void {
    $r = e2e_dispatch('update_contact', [
        'contact_id' => $ctx['contact_id'],
        'email' => 'e2e-updated@test.com',
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    $contact = CRMContact::find($ctx['contact_id']);
    e2e_assert($contact->email === 'e2e-updated@test.com', 'email atualizado no banco');
});

// ── update_contact_tags ───────────────────────────────────────────────────────

e2e_run('update_contact_tags: adiciona tags ao contato', function () use ($ctx): void {
    $r = e2e_dispatch('update_contact_tags', [
        'contact_id' => $ctx['contact_id'],
        'tags' => ['lead-e2e', 'qualificado'],
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
});

// ── search_contacts ───────────────────────────────────────────────────────────

e2e_run('search_contacts: busca por nome e retorna lista', function () use ($ctx): void {
    $r = e2e_dispatch('search_contacts', [
        'query' => 'Contato E2E',
        'limit' => 5,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");
    e2e_assert(isset($r->data['contacts']), 'data.contacts presente');
    e2e_assert(is_array($r->data['contacts']), 'data.contacts é array');
});

e2e_run('search_contacts: retorna lista vazia sem erro para query sem resultados', function () use ($ctx): void {
    $r = e2e_dispatch('search_contacts', [
        'query' => 'XYZ_SEM_RESULTADO_99999',
        'limit' => 5,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true mesmo sem resultados (got: {$r->message})");
    e2e_assert($r->data['contacts'] === [] || count($r->data['contacts']) === 0, 'retornou lista vazia');
});

// ── link_contact_to_company ───────────────────────────────────────────────────

e2e_run('link_contact_to_company: vincula contato a empresa', function () use ($ctx): void {
    $company = CRMCompany::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'name' => 'Empresa Link E2E',
    ]);

    $r = e2e_dispatch('link_contact_to_company', [
        'contact_id' => $ctx['contact_id'],
        'company_id' => $company->id,
    ], $ctx['agent_ctx']);

    e2e_assert($r->success, "success=true (got: {$r->message})");

    // Cleanup
    $company->delete();
});

// ── Cleanup de contatos criados ───────────────────────────────────────────────

e2e_run('cleanup: remove contatos criados neste grupo', function () use (&$createdContactId): void {
    if ($createdContactId) {
        CRMContact::query()->where('id', $createdContactId)->delete();
    }

    e2e_assert(true, 'cleanup executado');
});
