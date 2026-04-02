<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetSalesFunnelReportTest extends \Tests\Feature\Reports\ReportsTestCase
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

    public function test_returns_correct_data_for_tenant(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step1 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'name' => 'Lead',
            'order' => 1,
        ]);
        $step2 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'name' => 'Qualificação',
            'order' => 2,
        ]);

        CRMNegotiation::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step1->id,
            'amount' => 1000,
            'status' => 'open',
        ]);
        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step2->id,
            'amount' => 2000,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/reports/sales-funnel?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertArrayHasKey('steps', $data);
        $this->assertCount(2, $data['steps']);
        $this->assertSame('Lead', $data['steps'][0]['step_name']);
        $this->assertSame(3, $data['steps'][0]['count']);
        $this->assertEquals(3000.0, (float) $data['steps'][0]['total_amount']);
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
            'created_at' => '2025-06-15',
        ]);
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'created_at' => '2025-01-01',
        ]);

        $response = $this->getJson('/api/reports/sales-funnel?start_date=2025-06-01&end_date=2025-12-31');

        $response->assertStatus(200);
        $steps = $response->json('data.data.steps');
        $this->assertSame(1, $steps[0]['count']);
    }

    public function test_respects_funnel_filter(): void
    {
        $funnel1 = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel2 = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step1 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel1->id,
        ]);
        $step2 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel2->id,
        ]);

        CRMNegotiation::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel1->id,
            'crm_negotiation_funnel_step_id' => $step1->id,
        ]);
        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel2->id,
            'crm_negotiation_funnel_step_id' => $step2->id,
        ]);

        $response = $this->getJson('/api/reports/sales-funnel?start_date=2020-01-01&end_date=2030-12-31&funnel_id='.$funnel1->id);

        $response->assertStatus(200);
        $steps = $response->json('data.data.steps');
        $this->assertCount(1, $steps);
        $this->assertSame(3, $steps[0]['count']);
    }

    public function test_respects_user_filter(): void
    {
        $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
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
        ]);
        CRMNegotiation::factory()->count(5)->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'auth_user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/reports/sales-funnel?start_date=2020-01-01&end_date=2030-12-31&user_id='.$this->user->id);

        $response->assertStatus(200);
        $steps = $response->json('data.data.steps');
        $this->assertSame(2, $steps[0]['count']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        $otherTenantId = (string) $otherUser->tenant_id;

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherTenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->count(5)->create([
            'tenant_id' => $otherTenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        $response = $this->getJson('/api/reports/sales-funnel?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $steps = $response->json('data.data.steps');
        $this->assertEmpty($steps);
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/sales-funnel?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertEmpty($data['steps']);
        $this->assertEmpty($data['by_user']);
    }
}
