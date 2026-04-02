<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetLossReasonReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.crm.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_loss_reasons_with_percentages(): void
    {
        $reason1 = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenantId, 'name' => 'Price']);
        $reason2 = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenantId, 'name' => 'Competition']);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason1->id,
            'status' => 'lost',
            'amount' => 1000,
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason2->id,
            'status' => 'lost',
            'amount' => 2000,
        ]);

        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $reasons = $response->json('data.data.reasons');
        $this->assertCount(2, $reasons);
        $this->assertSame('Price', $reasons[0]['reason_name']);
        $this->assertSame(3, $reasons[0]['count']);
        $this->assertSame(75.0, (float) $reasons[0]['percentage']);
    }

    public function test_returns_cross_step_data(): void
    {
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason->id,
            'status' => 'lost',
        ]);

        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byStep = $response->json('data.data.by_step');
        $this->assertNotEmpty($byStep);
        $this->assertArrayHasKey('reason_name', $byStep[0]);
        $this->assertArrayHasKey('step_name', $byStep[0]);
    }

    public function test_returns_cross_user_data(): void
    {
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason->id,
            'auth_user_id' => $this->user->id,
            'status' => 'lost',
        ]);

        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byUser = $response->json('data.data.by_user');
        $this->assertNotEmpty($byUser);
        $this->assertArrayHasKey('user_name', $byUser[0]);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason->id,
            'status' => 'lost',
        ]);

        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.reasons'));
    }

    public function test_returns_timeline(): void
    {
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_reason_loss_id' => $reason->id,
            'status' => 'lost',
        ]);

        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $timeline = $response->json('data.data.timeline');
        $this->assertNotEmpty($timeline);
        $this->assertArrayHasKey('period', $timeline[0]);
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/loss-reasons?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.reasons'));
        $this->assertEmpty($response->json('data.data.by_step'));
        $this->assertEmpty($response->json('data.data.by_user'));
        $this->assertEmpty($response->json('data.data.timeline'));
    }
}
