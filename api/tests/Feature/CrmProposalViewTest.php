<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMProposalViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_mark_proposal_viewed(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        $proposal = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/proposals', [
            'title' => 'Proposta',
            'items' => [
                ['name' => 'Item', 'quantity' => 1, 'unit_price' => 10],
            ],
        ])->assertStatus(201)->json('data');

        $sent = $this->postJson('/api/crm/proposals/'.$proposal['id'].'/send')->assertStatus(200)->json('data');

        $this->getJson('/api/crm/proposals/view/'.$sent['public_token'])->assertStatus(200);

        $viewed = $this->getJson('/api/crm/proposals/view/'.$sent['public_token'])->json('data');
        $this->assertNotNull($viewed['viewed_at']);
    }
}
