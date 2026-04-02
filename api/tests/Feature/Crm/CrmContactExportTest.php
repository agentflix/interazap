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

test('can export contacts to csv', function (): void {
    CRMContact::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/contacts/export');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('name,email');
});

test('export respects filters', function (): void {
    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $response = $this->getJson('/api/crm/contacts/export?search=John');

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('John Doe');
    expect($content)->not->toContain('Jane Smith');
});

test('export includes company name', function (): void {
    $company = CRMCompany::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Corp',
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_company_id' => $company->id,
        'name' => 'John Doe',
    ]);

    $response = $this->getJson('/api/crm/contacts/export');

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('Acme Corp');
});

test('export does not leak other tenant data', function (): void {
    $otherTenant = PlatformTenant::factory()->create();

    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'My Contact',
    ]);

    CRMContact::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Contact',
    ]);

    $response = $this->getJson('/api/crm/contacts/export');

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('My Contact');
    expect($content)->not->toContain('Other Contact');
});

test('export requires authentication', function (): void {
    // Logout by creating a fresh request without authentication
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/crm/contacts/export');

    $response->assertUnauthorized();
});
