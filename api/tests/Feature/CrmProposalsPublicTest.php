<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProposal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMProposalsPublicTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_send_duplicate_and_public_accept(): void
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

        $sent = $this->postJson('/api/crm/proposals/'.$proposal['id'].'/send')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotNull($sent['public_token']);
        $this->assertSame('sent', $sent['status']);

        $duplicate = $this->postJson('/api/crm/proposals/'.$proposal['id'].'/duplicate')
            ->assertStatus(201)
            ->json('data');
        $this->assertSame('draft', $duplicate['status']);

        $view = $this->getJson('/api/crm/proposals/view/'.$sent['public_token'])->assertStatus(200)->json('data');
        $this->assertSame($sent['id'], $view['id']);

        $this->postJson('/api/crm/proposals/'.$sent['public_token'].'/accept')->assertStatus(200);
        $negotiation->refresh();
        $this->assertSame('won', $negotiation->status->value);
    }

    public function test_public_reject_marks_proposal_as_rejected(): void
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
            'title' => 'Rejeição',
            'items' => [
                ['name' => 'Implantação', 'quantity' => 1, 'unit_price' => 100],
            ],
        ])->assertStatus(201)->json('data');

        $sent = $this->postJson('/api/crm/proposals/'.$proposal['id'].'/send')
            ->assertStatus(200)
            ->json('data');

        $this->postJson('/api/crm/proposals/'.$sent['public_token'].'/reject')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $stored = CRMProposal::query()->find($proposal['id']);
        $this->assertSame('rejected', $stored?->status?->value);
        $this->assertNotNull($stored?->rejected_at);
    }
}
