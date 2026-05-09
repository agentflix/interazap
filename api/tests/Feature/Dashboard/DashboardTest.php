<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        if (! \Domain\Platform\Models\PlatformPlan::query()->exists()) {
            \Domain\Platform\Models\PlatformPlan::factory()->create(['is_active' => true]);
        }

        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'dashboard.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()],
        );
        $user->givePermissionTo($permission);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    private function seedDashboardData(string $tenantId): array
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
            'color' => '#0ea5e9',
        ]);

        $won = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
            'amount' => 1200,
            'closed_at' => now(),
            'created_at' => now(),
        ]);

        $open = CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'open',
            'amount' => 450,
            'created_at' => now(),
        ]);

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'status' => 'open',
            'priority' => 'high',
            'created_at' => now(),
        ]);

        ChatTicketEvaluation::query()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'token' => (string) Str::orderedUuid(),
            'rating' => 4,
            'comment' => 'Great service',
            'submitted_at' => now(),
        ]);

        CRMReasonLoss::factory()->create(['tenant_id' => $tenantId]);

        CRMProposal::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_id' => $open->id,
            'title' => 'Proposal A',
            'status' => 'sent',
            'created_at' => now(),
        ]);

        return [$won, $open];
    }

    public function test_returns_dashboard_data_for_authenticated_user(): void
    {
        [$user, $tenantId] = $this->acting();
        $this->seedDashboardData($tenantId);

        $response = $this->getJson('/api/dashboard')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals(1200.0, $response['summary']['total_revenue_won']);
        $this->assertEquals(450.0, $response['summary']['pipeline_open_value']);
        $this->assertSame(1, $response['summary']['active_tickets_count']);
        $this->assertEquals(4.0, $response['summary']['csat_average']);
        $this->assertNotEmpty($response['funnel']);
        $this->assertNotEmpty($response['revenue']);
        $this->assertNotEmpty($response['activities']);
    }

    public function test_filters_by_tenant_id(): void
    {
        [$user, $tenantId] = $this->acting();
        $this->seedDashboardData($tenantId);

        $newTenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        AuthUser::factory()->create(['tenant_id' => $newTenant->id]);
        $this->seedDashboardData($newTenant->id);

        $response = $this->getJson('/api/dashboard')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals(1200.0, $response['summary']['total_revenue_won']);
        $this->assertSame(1, $response['summary']['active_tickets_count']);
    }

    public function test_accepts_period_query_parameter(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
            'amount' => 999,
            'closed_at' => Date::now()->subDays(40),
            'created_at' => Date::now()->subDays(40),
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
            'amount' => 300,
            'closed_at' => Date::now()->subDays(2),
            'created_at' => Date::now()->subDays(2),
        ]);

        $response = $this->getJson('/api/dashboard?period=5')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals(300.0, $response['summary']['total_revenue_won']);
    }

    public function test_returns_401_for_unauthenticated_user(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }

    public function test_returns_correct_summary_structure(): void
    {
        [$user, $tenantId] = $this->acting();
        $this->seedDashboardData($tenantId);

        $this->getJson('/api/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_revenue_won',
                        'pipeline_open_value',
                        'active_tickets_count',
                        'csat_average',
                    ],
                ],
            ]);
    }
}
