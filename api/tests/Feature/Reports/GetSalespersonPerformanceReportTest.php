<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetSalespersonPerformanceReportTest extends \Tests\Feature\Reports\ReportsTestCase
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

    public function test_returns_salesperson_metrics(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $this->user->id,
            'status' => 'won',
            'amount' => 2000,
            'closed_at' => now(),
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $this->user->id,
            'status' => 'lost',
            'amount' => 1000,
        ]);

        $response = $this->getJson('/api/reports/salesperson-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $salespersons = $response->json('data.data.salespersons');
        $this->assertCount(1, $salespersons);
        $this->assertSame(3, $salespersons[0]['won_count']);
        $this->assertSame(1, $salespersons[0]['lost_count']);
        $this->assertSame(6000.0, (float) $salespersons[0]['total_revenue']);
        $this->assertSame(75.0, (float) $salespersons[0]['win_rate']);
    }

    public function test_returns_multiple_salespersons_ordered_by_revenue(): void
    {
        $user2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $this->user->id,
            'status' => 'won',
            'amount' => 1000,
            'closed_at' => now(),
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $user2->id,
            'status' => 'won',
            'amount' => 5000,
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/salesperson-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $salespersons = $response->json('data.data.salespersons');
        $this->assertCount(2, $salespersons);
        $this->assertSame(5000.0, (float) $salespersons[0]['total_revenue']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/reports/salesperson-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.salespersons'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/salesperson-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.salespersons'));
    }
}
