<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMNotesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_notas_em_contato_e_negociacao(): void
    {
        [$user, $tenantId] = $this->acting();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenantId]);

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

        $this->postJson('/api/crm/contacts/'.$contact->id.'/notes', [
            'content' => 'Primeira nota',
        ])->assertStatus(201);

        $list = $this->getJson('/api/crm/contacts/'.$contact->id.'/notes')->assertStatus(200)->json('data');
        $this->assertCount(1, $list);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/notes', [
            'content' => 'Nota deal',
        ])->assertStatus(201);

        $listDeal = $this->getJson('/api/crm/negotiations/'.$negotiation->id.'/notes')->assertStatus(200)->json('data');
        $this->assertCount(1, $listDeal);
    }
}
