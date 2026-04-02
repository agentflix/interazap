<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can add phone to contact', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $payload = [
        'phone_e164' => '+5511999999999',
        'label' => 'Mobile',
    ];

    $response = $this->postJson("/api/crm/contacts/{$contact->id}/phones", $payload);

    $response->assertCreated();

    $this->assertDatabaseHas('crm_contact_phones', [
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact->id,
        'phone_e164' => '+5511999999999',
        'valid_to' => null,
    ]);
});

test('duplicate active phone fails', function (): void {
    $contact1 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact2 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    CRMContactPhone::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact1->id,
        'phone_e164' => '+5511999999999',
        'valid_from' => now(),
        'valid_to' => null,
    ]);

    $payload = [
        'phone_e164' => '+5511999999999',
        'label' => 'Mobile',
    ];

    $response = $this->postJson("/api/crm/contacts/{$contact2->id}/phones", $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone_e164']);
});

test('reassigning phone invalidates previous', function (): void {
    $contact1 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact2 = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $phone = CRMContactPhone::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact1->id,
        'phone_e164' => '+5511999999999',
        'valid_from' => now()->subDays(10),
        'valid_to' => null,
    ]);

    $payload = [
        'phone_e164' => '+5511999999999',
        'label' => 'Mobile',
        'force_reassign' => true,
    ];

    $response = $this->postJson("/api/crm/contacts/{$contact2->id}/phones", $payload);

    $response->assertCreated();

    $phone->refresh();
    expect($phone->valid_to)->not->toBeNull();

    $this->assertDatabaseHas('crm_contact_phones', [
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact2->id,
        'phone_e164' => '+5511999999999',
        'valid_to' => null,
    ]);
});

test('phone history is preserved', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    CRMContactPhone::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact->id,
        'phone_e164' => '+5511888888888',
        'valid_from' => now()->subDays(30),
        'valid_to' => now()->subDays(10),
    ]);

    CRMContactPhone::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $contact->id,
        'phone_e164' => '+5511999999999',
        'valid_from' => now()->subDays(10),
        'valid_to' => null,
    ]);

    $phones = \Domain\CRM\Models\CRMContactPhone::query()->where('crm_contact_id', $contact->id)->get();

    expect($phones)->toHaveCount(2);
    expect($phones->whereNull('valid_to')->first()->phone_e164)->toBe('+5511999999999');
});
