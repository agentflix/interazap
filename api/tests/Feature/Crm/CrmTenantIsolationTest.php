<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProposal;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('tenant cannot access other tenant contacts', function (): void {
    $tenantA = PlatformTenant::factory()->create();
    $tenantB = PlatformTenant::factory()->create();

    $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = AuthUser::factory()->create(['tenant_id' => $tenantB->id]);

    $contact = CRMContact::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($userB);
    $response = $this->getJson("/api/crm/contacts/{$contact->id}");

    $response->assertNotFound();
});

test('tenant cannot access other tenant negotiations', function (): void {
    $tenantA = PlatformTenant::factory()->create();
    $tenantB = PlatformTenant::factory()->create();

    $userB = AuthUser::factory()->create(['tenant_id' => $tenantB->id]);

    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantA->id]);
    $step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $tenantA->id,
        'crm_negotiation_funnel_id' => $funnel->id,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $tenantA->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $step->id,
    ]);

    $this->actingAs($userB);
    $response = $this->getJson("/api/crm/negotiations/{$negotiation->id}");

    $response->assertNotFound();
});

test('tenant cannot access other tenant proposals', function (): void {
    $tenantA = PlatformTenant::factory()->create();
    $tenantB = PlatformTenant::factory()->create();

    $userB = AuthUser::factory()->create(['tenant_id' => $tenantB->id]);

    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantA->id]);
    $step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $tenantA->id,
        'crm_negotiation_funnel_id' => $funnel->id,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $tenantA->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $step->id,
    ]);

    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $tenantA->id,
        'crm_negotiation_id' => $negotiation->id,
    ]);

    $this->actingAs($userB);
    $response = $this->getJson("/api/crm/proposals/{$proposal->id}");

    $response->assertNotFound();
});
