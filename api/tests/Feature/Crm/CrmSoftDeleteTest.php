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

test('soft delete hides from listing', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/crm/contacts/{$contact->id}");
    $this->deleteJson("/api/crm/companies/{$company->id}");

    $contactsResponse = $this->getJson('/api/crm/contacts');
    $companiesResponse = $this->getJson('/api/crm/companies');

    $contactsResponse->assertOk()->assertJsonCount(0, 'data');
    $companiesResponse->assertOk()->assertJsonCount(0, 'data');
});

test('soft delete preserves data', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/crm/contacts/{$contact->id}");

    $this->assertDatabaseHas('crm_contacts', [
        'id' => $contact->id,
        'name' => $contact->name,
    ]);

    $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);
});

test('can restore soft deleted', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/crm/contacts/{$contact->id}");

    $response = $this->postJson("/api/crm/contacts/{$contact->id}/restore");

    $response->assertOk();

    $this->assertDatabaseHas('crm_contacts', [
        'id' => $contact->id,
        'deleted_at' => null,
    ]);
});
