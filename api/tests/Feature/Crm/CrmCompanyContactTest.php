<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('contact belongs to company', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $company->contacts()->attach($contact->id, [
        'tenant_id' => $this->tenant->id,
        'id' => (string) Str::orderedUuid(),
    ]);

    $company->refresh();

    expect($company->contacts)->toHaveCount(1);
    expect($company->contacts->first()->id)->toBe($contact->id);
});

test('company has many contacts', function (): void {
    $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
    $contacts = CRMContact::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    foreach ($contacts as $contact) {
        $company->contacts()->attach($contact->id, [
            'tenant_id' => $this->tenant->id,
            'id' => (string) Str::orderedUuid(),
        ]);
    }

    $company->refresh();

    expect($company->contacts)->toHaveCount(2);
});
