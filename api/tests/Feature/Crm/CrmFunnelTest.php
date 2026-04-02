<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can list funnels', function (): void {
    CRMNegotiationFunnel::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/funnels');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('can create funnel', function (): void {
    $response = $this->postJson('/api/crm/funnels', [
        'name' => 'Pipeline A',
        'is_active' => true,
        'steps' => [
            [
                'name' => 'Qualificação',
                'order' => 1,
                'color' => '#3b82f6',
                'is_active' => true,
            ],
        ],
    ]);

    $response->assertCreated()->assertJsonFragment(['name' => 'Pipeline A']);

    $this->assertDatabaseHas('crm_negotiation_funnels', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Pipeline A',
    ]);
});

test('can update funnel', function (): void {
    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/crm/funnels/{$funnel->id}", [
        'name' => 'Pipeline B',
        'is_active' => true,
        'steps' => [
            [
                'name' => 'Qualificação',
                'order' => 1,
                'color' => '#3b82f6',
                'is_active' => true,
            ],
        ],
    ]);

    $response->assertOk()->assertJsonFragment(['name' => 'Pipeline B']);
});

test('can update funnel without steps', function (): void {
    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/crm/funnels/{$funnel->id}", [
        'name' => 'Pipeline C',
        'is_active' => false,
    ]);

    $response
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Pipeline C',
            'is_active' => false,
        ]);

    $this->assertDatabaseHas('crm_negotiation_funnels', [
        'id' => $funnel->id,
        'tenant_id' => $this->tenant->id,
        'name' => 'Pipeline C',
        'is_active' => false,
    ]);
});

test('can delete funnel', function (): void {
    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/funnels/{$funnel->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_negotiation_funnels', ['id' => $funnel->id]);
});

test('can add step to funnel', function (): void {
    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson("/api/crm/funnels/{$funnel->id}/steps", [
        'name' => 'Qualificação',
        'order' => 1,
    ]);

    $response->assertCreated()->assertJsonFragment(['name' => 'Qualificação']);

    $this->assertDatabaseHas('crm_negotiation_funnel_steps', [
        'crm_negotiation_funnel_id' => $funnel->id,
        'name' => 'Qualificação',
        'order' => 1,
    ]);
});

test('can reorder steps', function (): void {
    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);

    $step1 = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'order' => 1,
    ]);

    $step2 = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'order' => 2,
    ]);

    $response = $this->postJson("/api/crm/funnels/{$funnel->id}/steps/reorder", [
        'steps' => [
            ['id' => $step1->id, 'order' => 2],
            ['id' => $step2->id, 'order' => 1],
        ],
    ]);

    $response->assertOk();

    $step1->refresh();
    $step2->refresh();

    expect($step1->order)->toBe(2);
    expect($step2->order)->toBe(1);
});
