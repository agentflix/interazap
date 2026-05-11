<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! function_exists('assertCrmNPlusOneQueryBudget')) {
    function assertCrmNPlusOneQueryBudget(int $queryCount, int $threshold, string $endpoint): void
    {
        test()->assertLessThanOrEqual(
            $threshold,
            $queryCount,
            sprintf(
                'N+1 query budget exceeded for endpoint [%s]: queryCount=%d, threshold=%d.',
                $endpoint,
                $queryCount,
                $threshold,
            ),
        );
    }
}

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->loadMissing('permissions', 'roles.permissions');
});

test('contact list does not have N+1 queries', function (): void {
    // Criar custom fields para testar eager loading
    $customField1 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_1',
    ]);

    $customField2 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_2',
    ]);

    // Criar 10 contatos com relacionamentos complexos
    foreach (range(1, 10) as $i) {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Adicionar telefones
        CRMContactPhone::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'crm_contact_id' => $contact->id,
        ]);

        // Adicionar custom field values (um para cada field)
        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField1->id,
            'entity_type' => 'contact',
            'entity_id' => $contact->id,
        ]);

        CRMCustomFieldValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_custom_field_id' => $customField2->id,
            'entity_type' => 'contact',
            'entity_id' => $contact->id,
        ]);

        // Adicionar tags
        $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
        $contact->tags()->attach($tag->id, [
            'id' => Str::orderedUuid()->toString(),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // Resetar query log
    DB::flushQueryLog();
    DB::enableQueryLog();

    // Fazer request
    $response = $this->actingAs($this->user)
        ->getJson('/api/crm/contacts?tenant_id='.$this->tenant->id);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($queryCount > 10 && env('DEBUG_N_PLUS1')) {
        info('Query count: '.$queryCount);
        info('Queries: '.json_encode(array_map(fn (array $q) => $q['query'], $queries)));
    }

    // Deveria ter no máximo:
    // 1. Select contacts (com tenant_id)
    // 2. Count para paginação
    // 3. Eager load companies
    // 4. Eager load phones
    // 5. Eager load customFieldValues
    // 6. Eager load customFieldValues.field
    // 7. Eager load tags (pivot)
    // Baseline atual: 11 queries constantes com paginação, policies e eager loads.
    assertCrmNPlusOneQueryBudget($queryCount, 11, 'GET /api/crm/contacts');
    expect($response->status())->toBe(200);

    DB::disableQueryLog();
});

test('contact show does not have N+1 queries', function (): void {
    // Criar custom fields
    $customField1 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_show_1',
    ]);

    $customField2 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_show_2',
    ]);

    $customField3 = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_show_3',
    ]);

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Adicionar relacionamentos
    CRMContactPhone::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField1->id,
        'entity_type' => 'contact',
        'entity_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField2->id,
        'entity_type' => 'contact',
        'entity_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField3->id,
        'entity_type' => 'contact',
        'entity_id' => $contact->id,
    ]);

    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact->tags()->attach($tag->id, [
        'id' => Str::orderedUuid()->toString(),
        'tenant_id' => $this->tenant->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->getJson("/api/crm/contacts/{$contact->id}?tenant_id=".$this->tenant->id);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Debug (only when DEBUG_N_PLUS1=1)
    if ($queryCount > 8 && env('DEBUG_N_PLUS1')) {
        info('Query count: '.$queryCount);
        info('Queries: '.json_encode(array_map(fn (array $q) => $q['query'], $queries)));
    }

    // Baseline atual: 10 queries constantes para show com eager loading.
    assertCrmNPlusOneQueryBudget($queryCount, 10, 'GET /api/crm/contacts/{id}');
    expect($response->status())->toBe(200);

    DB::disableQueryLog();
});

test('contact update does not have N+1 queries on reload', function (): void {
    $customField = CRMCustomField::factory()->create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'contact',
        'name' => 'field_update',
    ]);

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    CRMContactPhone::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact->id,
    ]);

    CRMCustomFieldValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_custom_field_id' => $customField->id,
        'entity_type' => 'contact',
        'entity_id' => $contact->id,
    ]);

    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact->tags()->attach($tag->id, [
        'id' => Str::orderedUuid()->toString(),
        'tenant_id' => $this->tenant->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($this->user)
        ->putJson("/api/crm/contacts/{$contact->id}?tenant_id=".$this->tenant->id, [
            'name' => 'Updated Name',
            'email' => $contact->email,
        ]);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Update + reload com eager loading.
    assertCrmNPlusOneQueryBudget($queryCount, 18, 'PUT /api/crm/contacts/{id}');
    expect($response->status())->toBe(200);

    DB::disableQueryLog();
});
