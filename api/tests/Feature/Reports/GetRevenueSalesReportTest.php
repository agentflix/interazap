<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetRevenueSalesReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        AuthPermission::query()->firstOrCreate(
            ['name' => 'reports.crm.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::uuid()]
        );

        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.crm.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_summary_with_correct_metrics(): void
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
            'status' => 'won',
            'amount' => 1000,
            'closed_at' => now(),
        ]);
        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'lost',
            'amount' => 500,
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'open',
            'amount' => 800,
        ]);

        $response = $this->getJson('/api/reports/revenue-sales?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertEquals(3000.0, (float) $summary['total_revenue']);
        $this->assertEquals(1000.0, (float) $summary['avg_ticket']);
        $this->assertSame(3, $summary['won_count']);
        $this->assertSame(2, $summary['lost_count']);
        $this->assertSame(1, $summary['open_count']);
        $this->assertEquals(60.0, (float) $summary['win_rate']);
    }

    public function test_returns_ranking_by_user(): void
    {
        $user2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $this->user->id,
            'status' => 'won',
            'amount' => 5000,
            'closed_at' => now(),
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $user2->id,
            'status' => 'won',
            'amount' => 3000,
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/revenue-sales?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $ranking = $response->json('data.data.ranking');
        $this->assertCount(2, $ranking);
        $this->assertEquals(10000.0, (float) $ranking[0]['revenue']);
    }

    public function test_returns_loss_reasons(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $reason = CRMReasonLoss::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Preço alto',
        ]);

        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'lost',
            'crm_reason_loss_id' => $reason->id,
            'amount' => 1000,
        ]);

        $response = $this->getJson('/api/reports/revenue-sales?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $lossReasons = $response->json('data.data.loss_reasons');
        $this->assertCount(1, $lossReasons);
        $this->assertSame('Preço alto', $lossReasons[0]['reason_name']);
        $this->assertSame(2, $lossReasons[0]['lost_count']);
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
            'status' => 'won',
            'amount' => 10000,
        ]);

        $response = $this->getJson('/api/reports/revenue-sales?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEquals(0.0, (float) $response->json('data.data.summary.total_revenue'));
    }

    public function test_respects_period_filter(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
            'amount' => 1000,
            'created_at' => '2025-06-15',
            'closed_at' => '2025-06-15',
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
            'amount' => 2000,
            'created_at' => '2025-01-01',
            'closed_at' => '2025-01-01',
        ]);

        $response = $this->getJson('/api/reports/revenue-sales?start_date=2025-06-01&end_date=2025-12-31');

        $response->assertStatus(200);
        $this->assertEquals(1000.0, (float) $response->json('data.data.summary.total_revenue'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/revenue-sales?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertEquals(0.0, (float) $data['summary']['total_revenue']);
        $this->assertEmpty($data['time_series']);
        $this->assertEmpty($data['ranking']);
        $this->assertEmpty($data['loss_reasons']);
    }
}
