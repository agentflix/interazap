<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can list companies', function (): void {
    CRMCompany::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/companies');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can create company', function (): void {
    $payload = [
        'name' => 'Acme Corp',
        'email' => 'contact@acme.com',
        'phone' => '11988888888',
        'is_active' => false,
        'website' => 'https://acme.com',
    ];

    $response = $this->postJson('/api/crm/companies', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'Acme Corp'])
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('crm_companies', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Corp',
        'email' => 'contact@acme.com',
        'is_active' => false,
    ]);
});

test('can show company', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/crm/companies/{$company->id}");

    $response->assertOk()
        ->assertJsonFragment(['name' => $company->name]);
});

test('can update company', function (): void {
    $company = CRMCompany::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Old Name',
    ]);

    $payload = [
        'name' => 'New Name',
        'email' => $company->email,
        'is_active' => false,
    ];

    $response = $this->putJson("/api/crm/companies/{$company->id}", $payload);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'New Name'])
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('crm_companies', [
        'id' => $company->id,
        'name' => 'New Name',
        'is_active' => false,
    ]);
});

test('can delete company', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/companies/{$company->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('crm_companies', ['id' => $company->id]);
});

test('cannot access other tenant companies', function (): void {
    $otherTenant = PlatformTenant::factory()->create();
    $otherCompany = CRMCompany::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson("/api/crm/companies/{$otherCompany->id}");

    $response->assertNotFound();
});

test('listing companies filters by tenant', function (): void {
    CRMCompany::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $otherTenant = PlatformTenant::factory()->create();
    CRMCompany::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson('/api/crm/companies');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});
