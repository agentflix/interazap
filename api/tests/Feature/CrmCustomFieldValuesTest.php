<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMCustomFieldValuesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_upsert_custom_field_values(): void
    {
        [$user, $tenantId] = $this->acting();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenantId]);
        $company = CRMCompany::factory()->create(['tenant_id' => $tenantId]);

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $negotiation = \Domain\CRM\Models\CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        $field = CRMCustomField::factory()->create([
            'tenant_id' => $tenantId,
            'type' => 'text',
            'entity' => 'contact',
        ]);

        $this->postJson('/api/crm/contacts/'.$contact->id.'/custom-fields', [
            'crm_custom_field_id' => $field->id,
            'value' => 'VIP',
        ])->assertStatus(200);

        $fieldCompany = CRMCustomField::factory()->create([
            'tenant_id' => $tenantId,
            'type' => 'number',
            'entity' => 'company',
        ]);

        $this->postJson('/api/crm/companies/'.$company->id.'/custom-fields', [
            'crm_custom_field_id' => $fieldCompany->id,
            'value' => 123,
        ])->assertStatus(200);

        $fieldDeal = CRMCustomField::factory()->create([
            'tenant_id' => $tenantId,
            'type' => 'select',
            'entity' => 'negotiation',
            'options' => ['web', 'referral'],
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/custom-fields', [
            'crm_custom_field_id' => $fieldDeal->id,
            'value' => 'web',
        ])->assertStatus(200);
    }
}
