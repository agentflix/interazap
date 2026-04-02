<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetTeamActivityReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.admin.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_user_list(): void
    {
        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $this->assertNotEmpty($members);
        $this->assertArrayHasKey('user_name', $members[0]);
        $this->assertArrayHasKey('is_inactive', $members[0]);
    }

    public function test_returns_ticket_stats(): void
    {
        ChatTicket::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $currentUser = collect($members)->firstWhere('user_name', $this->user->name);
        $this->assertSame(3, $currentUser['tickets_created']);
        $this->assertSame(3, $currentUser['tickets_resolved']);
    }

    public function test_returns_negotiation_stats(): void
    {
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
        ]);

        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $currentUser = collect($members)->firstWhere('user_name', $this->user->name);
        $this->assertSame(2, $currentUser['negotiations_created']);
        $this->assertSame(2, $currentUser['negotiations_won']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        ChatTicket::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
            'assigned_to' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $names = collect($members)->pluck('user_name')->all();
        $this->assertNotContains($otherUser->name, $names);
    }

    public function test_filters_by_user_id(): void
    {
        AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31&user_id='.$this->user->id);

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $this->assertCount(1, $members);
        $this->assertSame($this->user->name, $members[0]['user_name']);
    }

    public function test_returns_users_with_zero_activity(): void
    {
        $response = $this->getJson('/api/reports/team-activity?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $members = $response->json('data.data.members');
        $currentUser = collect($members)->firstWhere('user_name', $this->user->name);
        $this->assertSame(0, $currentUser['tickets_created']);
        $this->assertSame(0, $currentUser['negotiations_created']);
    }
}
