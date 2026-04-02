<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMTag;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMTagsAttachTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_vincula_tags_a_entidades(): void
    {
        [$user, $tenantId] = $this->acting();

        $tag = CRMTag::factory()->create(['tenant_id' => $tenantId]);
        $company = CRMCompany::factory()->create(['tenant_id' => $tenantId]);

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

        $this->postJson('/api/crm/companies/'.$company->id.'/tags', ['tag_id' => $tag->id])
            ->assertStatus(200);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/tags', ['tag_id' => $tag->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('crm_company_tags', [
            'tenant_id' => $tenantId,
            'crm_company_id' => $company->id,
            'crm_tag_id' => $tag->id,
        ]);

        $this->assertDatabaseHas('crm_negotiation_tags', [
            'tenant_id' => $tenantId,
            'crm_negotiation_id' => $negotiation->id,
            'crm_tag_id' => $tag->id,
        ]);
    }
}
