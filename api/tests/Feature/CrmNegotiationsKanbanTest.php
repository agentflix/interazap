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

class CRMNegotiationsKanbanTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_move_won_lost_reopen_and_kanban(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step1 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $step2 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 2,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step1->id,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/move', [
            'crm_negotiation_funnel_step_id' => $step2->id,
        ])->assertStatus(200);

        $kanban = $this->getJson('/api/crm/negotiations-kanban?funnel_id='.$funnel->id)
            ->assertStatus(200)
            ->json('data');
        $this->assertSame($funnel->id, $kanban['funnel']['id'] ?? null);
        $steps = collect($kanban['steps'] ?? []);
        $this->assertTrue($steps->contains(fn (array $item): bool => ($item['id'] ?? null) === $step2->id));

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/won')->assertStatus(200);
        $negotiation->refresh();
        $this->assertSame('won', $negotiation->status->value);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/reopen')->assertStatus(200);
        $negotiation->refresh();
        $this->assertSame('open', $negotiation->status->value);
    }

    public function test_cannot_move_negotiation_of_another_tenant(): void
    {
        [$userA, $tenantA] = $this->acting();
        $userB = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($userB, abilities: ['*']);

        $funnelA = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantA]);
        $stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantA,
            'crm_negotiation_funnel_id' => $funnelA->id,
        ]);
        $negotiationA = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantA,
            'crm_negotiation_funnel_id' => $funnelA->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiationA->id.'/move', [
            'crm_negotiation_funnel_step_id' => $stepA->id,
        ])->assertStatus(404);
    }

    public function test_move_requires_step_validation(): void
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

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/move', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['crm_negotiation_funnel_step_id']);
    }

    public function test_kanban_requires_funnel_id(): void
    {
        $this->acting();

        $this->getJson('/api/crm/negotiations-kanban')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['funnel_id']);
    }
}
