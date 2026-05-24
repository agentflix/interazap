<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can list contacts', function (): void {
    CRMContact::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/contacts');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can create contact', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'crm_company_id' => $company->id,
        'is_active' => true,
    ];

    $response = $this->postJson('/api/crm/contacts', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'John Doe']);

    $this->assertDatabaseHas('crm_contacts', [
        'tenant_id' => $this->tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

test('can show contact', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/crm/contacts/{$contact->id}");

    $response->assertOk()
        ->assertJsonFragment(['name' => $contact->name]);
});

test('can update contact', function (): void {
    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Old Name',
    ]);

    $payload = [
        'name' => 'New Name',
        'email' => $contact->email,
    ];

    $response = $this->putJson("/api/crm/contacts/{$contact->id}", $payload);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    $this->assertDatabaseHas('crm_contacts', [
        'id' => $contact->id,
        'name' => 'New Name',
    ]);
});

test('can delete contact', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/contacts/{$contact->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);
});

test('cannot access other tenant contacts', function (): void {
    $otherTenant = PlatformTenant::factory()->create();
    $otherContact = CRMContact::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson("/api/crm/contacts/{$otherContact->id}");

    $response->assertNotFound();
});

test('listing contacts filters by tenant', function (): void {
    CRMContact::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $otherTenant = PlatformTenant::factory()->create();
    CRMContact::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson('/api/crm/contacts');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter contacts by search term', function (): void {
    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'John Searchable',
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mary Hidden',
    ]);

    $response = $this->getJson('/api/crm/contacts?search=John');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'John Searchable'])
        ->assertJsonMissing(['name' => 'Mary Hidden']);
});

test('can filter contacts by active status', function (): void {
    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Active Contact',
        'is_active' => true,
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Inactive Contact',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/crm/contacts?is_active=active');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Active Contact'])
        ->assertJsonMissing(['name' => 'Inactive Contact']);
});

test('can filter contacts by crm_company_id', function (): void {
    $companyA = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $companyB = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Contato Empresa A',
        'crm_company_id' => $companyA->id,
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Contato Empresa B',
        'crm_company_id' => $companyB->id,
    ]);

    $response = $this->getJson('/api/crm/contacts?crm_company_id='.$companyA->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Contato Empresa A'])
        ->assertJsonMissing(['name' => 'Contato Empresa B']);
});

test('can create and update contact notes and custom fields', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    $createResponse = $this->postJson('/api/crm/contacts', [
        'name' => 'Contact With Notes',
        'phone' => '11999999999',
        'crm_company_id' => $company->id,
        'notes' => 'Primeira observação',
        'custom_fields' => [
            'apelido' => 'João',
            'origem' => 'Campanha X',
        ],
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.notes', 'Primeira observação')
        ->assertJsonPath('data.custom_fields.apelido', 'João');

    $contactId = (string) $createResponse->json('data.id');

    $updateResponse = $this->putJson("/api/crm/contacts/{$contactId}", [
        'name' => 'Contact With Notes Updated',
        'phone' => '11988888888',
        'crm_company_id' => $company->id,
        'notes' => 'Observação atualizada',
        'custom_fields' => [
            'apelido' => 'Maria',
            'segmento' => 'SaaS',
        ],
    ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.notes', 'Observação atualizada')
        ->assertJsonPath('data.custom_fields.apelido', 'Maria')
        ->assertJsonPath('data.custom_fields.segmento', 'SaaS');

    $this->assertDatabaseHas('crm_contacts', [
        'id' => $contactId,
        'tenant_id' => $this->tenant->id,
        'notes' => 'Observação atualizada',
    ]);
});

test('validates contact notes max length as 500 characters', function (): void {
    $payload = [
        'name' => 'Too Long Notes',
        'phone' => '+5511999999999',
        'notes' => str_repeat('a', 501),
    ];

    $response = $this->postJson('/api/crm/contacts', $payload);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['notes']);
});
