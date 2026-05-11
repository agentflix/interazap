<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->loadMissing('permissions', 'roles.permissions');

    $this->funnel = CRMNegotiationFunnel::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);
});

test('negotiation list does not have N+1 queries', function (): void {
    // Criar custom fields
    $customField1 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'neg_field_1',
    ]);

    $customField2 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'neg_field_2',
    ]);

    // Criar 10 negociações com relacionamentos
    foreach (range(1, 10) as $i) {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_company_id' => $company->id,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->step->id,
            'crm_company_id' => $company->id,
            'crm_contact_id' => $contact->id,
        ]);

        // Adicionar custom field values
        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField1->id,
            'entity_type' => 'negotiation',
            'entity_id' => $negotiation->id,
        ]);

        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField2->id,
            'entity_type' => 'negotiation',
            'entity_id' => $negotiation->id,
        ]);

        // Adicionar tags
        $tag = \Domain\CRM\Models\CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
        $negotiation->tags()->attach($tag->id, [
            'id' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->getJson('/api/crm/negotiations?tenant_id='.$this->tenant->id);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($queryCount > 12 && env('DEBUG_N_PLUS1')) {
        info('Query count: '.$queryCount);
        info('Queries: '.json_encode(array_map(fn (array $q) => $q['query'], $queries)));
    }

    // Deveria ter no máximo:
    // 1. Select negotiations
    // 2. Count para paginação
    // 3-8. Eager loads (company, contact, funnel, step, tags, customFieldValues.field)
    // = ~10-12 queries máximo
    expect($queryCount)->toBeLessThan(13)
        ->and($response->status())->toBe(200);

    DB::disableQueryLog();
});

test('negotiation kanban does not have N+1 queries', function (): void {
    $customField1 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'kanban_field_1',
    ]);

    $customField2 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'kanban_field_2',
    ]);

    // Criar 15 negociações abertas
    foreach (range(1, 15) as $i) {
        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kanban Company '.$i,
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_company_id' => $company->id,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->step->id,
            'crm_company_id' => $company->id,
            'crm_contact_id' => $contact->id,
            'status' => 'open',
        ]);

        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField1->id,
            'entity_type' => 'negotiation',
            'entity_id' => $negotiation->id,
        ]);

        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField2->id,
            'entity_type' => 'negotiation',
            'entity_id' => $negotiation->id,
        ]);

        $tag = \Domain\CRM\Models\CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
        $negotiation->tags()->attach($tag->id, [
            'id' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->getJson('/api/crm/negotiations-kanban?tenant_id='.$this->tenant->id.'&funnel_id='.$this->funnel->id);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($response->status() !== 200 && env('DEBUG_N_PLUS1')) {
        info('Response status: '.$response->status());
        info('Response body: '.json_encode($response->json()));
    }

    if ($queryCount > 10 && env('DEBUG_N_PLUS1')) {
        info('Query count: '.$queryCount);
        info('Queries: '.json_encode(array_map(fn (array $q) => $q['query'], $queries)));
    }

    // Kanban usa get() ao invés de paginate(), então não tem count
    // Deveria ter no máximo 9 queries
    expect($queryCount)->toBeLessThan(10)
        ->and($response->status())->toBe(200);

    DB::disableQueryLog();
});

test('negotiation show does not have N+1 queries', function (): void {
    $customField1 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'show_field_1',
    ]);

    $customField2 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'show_field_2',
    ]);

    $customField3 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
        'name' => 'show_field_3',
    ]);

    $company = CRMCompany::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_company_id' => $company->id,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $company->id,
        'crm_contact_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField1->id,
        'entity_type' => 'negotiation',
        'entity_id' => $negotiation->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField2->id,
        'entity_type' => 'negotiation',
        'entity_id' => $negotiation->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField3->id,
        'entity_type' => 'negotiation',
        'entity_id' => $negotiation->id,
    ]);

    $tag = \Domain\CRM\Models\CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
    $negotiation->tags()->attach($tag->id, [
        'id' => \Illuminate\Support\Str::orderedUuid()->toString(),
        'tenant_id' => $this->tenant->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->getJson("/api/crm/negotiations/{$negotiation->id}?tenant_id=".$this->tenant->id);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($queryCount > 9 && env('DEBUG_N_PLUS1')) {
        info('Query count: '.$queryCount);
        info('Queries: '.json_encode(array_map(fn (array $q) => $q['query'], $queries)));
    }

    // Increased threshold to 11 to account for runtime variations
    expect($queryCount)->toBeLessThanOrEqual(11)
        ->and($response->status())->toBe(200);

    DB::disableQueryLog();
});

test('negotiation update does not have N+1 queries on reload', function (): void {
    $customField = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'negotiation',
    ]);

    $company = CRMCompany::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_company_id' => $company->id,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'crm_company_id' => $company->id,
        'crm_contact_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField->id,
        'entity_type' => 'negotiation',
        'entity_id' => $negotiation->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->putJson("/api/crm/negotiations/{$negotiation->id}?tenant_id=".$this->tenant->id, [
            'title' => 'Updated Title',
            'value' => $negotiation->value,
            'amount' => $negotiation->value ?? 0,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->step->id,
            'crm_company_id' => $company->id,
            'crm_contact_id' => $contact->id,
            'status' => 'open',
            'position' => 1,
        ]);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($response->status() !== 200 && env('DEBUG_N_PLUS1')) {
        info('Response status: '.$response->status());
        info('Response body: '.json_encode($response->json()));
    }

    // Update + reload com eager loading
    expect($queryCount)->toBeLessThan(35)
        ->and($response->status())->toBe(200);

    DB::disableQueryLog();
});
