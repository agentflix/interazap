<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMNegotiationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_cria_e_atualiza_negociacao(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenantId]);

        $payload = [
            'title' => 'Negociação X',
            'amount' => 1500,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
        ];

        $create = $this->postJson('/api/crm/negotiations', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('Negociação X', $create['title']);

        $this->putJson('/api/crm/negotiations/'.$create['id'], [
            'title' => 'Negociação Y',
            'amount' => 2000,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
        ])->assertStatus(200);

        $list = $this->getJson('/api/crm/negotiations')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));
    }

    public function test_cria_tarefa_para_negociacao(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/tasks', [
            'title' => 'Ligar para cliente',
        ])->assertStatus(201);
    }

    public function test_perde_negociacao_com_motivo(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenantId]);

        $payload = [
            'title' => 'Negociação Perdida',
            'amount' => 500,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
        ];

        $negotiationId = $this->postJson('/api/crm/negotiations', $payload)
            ->assertStatus(201)
            ->json('data.id');

        $this->putJson('/api/crm/negotiations/'.$negotiationId, $payload + ['status' => 'lost'])
            ->assertStatus(422);

        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $tenantId]);

        $this->putJson(
            '/api/crm/negotiations/'.$negotiationId,
            $payload + ['status' => 'lost', 'crm_reason_loss_id' => $reason->id]
        )->assertStatus(200);

        $negotiation = \Domain\CRM\Models\CRMNegotiation::query()->find($negotiationId);
        $this->assertSame('lost', $negotiation?->status?->value);
        $this->assertNotNull($negotiation?->closed_at);
        $this->assertSame($reason->id, $negotiation?->crm_reason_loss_id);
    }

    public function test_move_negociacao_entre_etapas_reordena_coluna(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 2,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
            'position' => 1,
        ]);

        $firstTarget = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
            'position' => 1,
        ]);
        $secondTarget = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
            'position' => 2,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/move', [
            'crm_negotiation_funnel_step_id' => $stepB->id,
            'position' => 2,
        ])->assertStatus(200);

        $ordered = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_step_id', $stepB->id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $this->assertSame([$firstTarget->id, $negotiation->id, $secondTarget->id], $ordered);
    }

    public function test_marca_ganha_e_reabre_negociacao_via_rotas(): void
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
            'status' => 'open',
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/won')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'won');

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/reopen')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open');
    }

    public function test_kanban_endpoint_retorna_negociacoes_por_etapa(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 2,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
            'status' => 'open',
            'amount' => 0,
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
            'status' => 'open',
            'amount' => 0,
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
            'status' => 'lost',
            'amount' => 0,
        ]);

        $response = $this->getJson('/api/crm/negotiations-kanban?funnel_id='.$funnel->id)
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($funnel->id, $response['funnel']['id'] ?? null);

        $steps = collect($response['steps'] ?? []);
        $stepAData = $steps->firstWhere('id', $stepA->id);
        $stepBData = $steps->firstWhere('id', $stepB->id);

        $this->assertIsArray($stepAData);
        $this->assertIsArray($stepBData);
        $this->assertCount(2, $stepAData['negotiations'] ?? []);
        $this->assertCount(1, $stepBData['negotiations'] ?? []);
    }

    public function test_nao_cria_negociacao_sem_contato(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);

        $this->postJson('/api/crm/negotiations', [
            'title' => 'Sem contato',
            'amount' => 100,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['crm_contact_id']);
    }
}
