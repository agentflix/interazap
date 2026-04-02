<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can create reason loss', function (): void {
    $response = $this->postJson('/api/crm/reason-losses', ['name' => 'Preço alto']);

    $response->assertCreated()->assertJsonFragment(['name' => 'Preço alto']);

    $this->assertDatabaseHas('crm_reason_losses', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Preço alto',
    ]);
});

test('can list reason losses', function (): void {
    CRMReasonLoss::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/reason-losses');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('can list all active reason losses', function (): void {
    CRMReasonLoss::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    CRMReasonLoss::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/crm/reason-losses/all');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('can update reason loss', function (): void {
    $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/crm/reason-losses/{$reason->id}", ['name' => 'Concorrência']);

    $response->assertOk()->assertJsonFragment(['name' => 'Concorrência']);
});

test('can delete reason loss', function (): void {
    $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/reason-losses/{$reason->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_reason_losses', ['id' => $reason->id]);
});
