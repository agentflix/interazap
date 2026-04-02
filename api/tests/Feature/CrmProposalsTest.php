<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMProposalsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_cria_e_atualiza_proposta(): void
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

        $product = CRMProduct::factory()->create(['tenant_id' => $tenantId]);

        $payload = [
            'title' => 'Proposta A',
            'items' => [
                [
                    'name' => 'Linha 1',
                    'quantity' => 2,
                    'unit_price' => 50,
                    'crm_product_id' => $product->id,
                ],
            ],
        ];

        $created = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/proposals', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame(100.0, (float) $created['total']);

        $this->getJson('/api/crm/negotiations/'.$negotiation->id.'/proposals')
            ->assertStatus(200);

        $this->putJson('/api/crm/proposals/'.$created['id'], [
            'title' => 'Proposta A',
            'status' => 'accepted',
            'items' => $payload['items'],
        ])->assertStatus(200);

        $negotiation->refresh();
        $this->assertSame('won', $negotiation->status->value);
        $this->assertNotNull($negotiation->closed_at);
    }
}
