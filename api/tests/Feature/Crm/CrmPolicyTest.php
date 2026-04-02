<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

function seedTenantPlan(PlatformTenant $tenant, PlatformPlan $plan): void
{
    BillingInvoice::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => BillingInvoiceStatus::PAID->value,
        'due_date' => now()->toDateString(),
    ]);
}

test('crm negotiation policy blocks creation when plan limit reached', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $plan = PlatformPlan::factory()->create([
        'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
        'negotiations_limit' => 1,
    ]);

    seedTenantPlan($tenant, $plan);

    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
    Sanctum::actingAs($user, abilities: ['*']);

    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
    $step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
    ]);

    CRMNegotiation::factory()->create([
        'tenant_id' => $tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $step->id,
    ]);

    $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

    $payload = [
        'title' => 'New negotiation',
        'amount' => 1200,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $step->id,
        'crm_contact_id' => $contact->id,
    ];

    $this->postJson('/api/crm/negotiations', $payload)
        ->assertStatus(403)
        ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
});
