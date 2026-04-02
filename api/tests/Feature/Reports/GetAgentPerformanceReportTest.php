<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetAgentPerformanceReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.chat.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_agent_metrics(): void
    {
        ChatTicket::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'first_response_at' => now()->subMinutes(5),
            'closed_at' => now(),
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'first_response_at' => now()->subMinutes(10),
            'closed_at' => null,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $agents = $response->json('data.data.agents');
        $this->assertCount(1, $agents);
        $this->assertSame(4, $agents[0]['tickets_handled']);
        $this->assertSame(3, $agents[0]['tickets_resolved']);
    }

    public function test_returns_multiple_agents(): void
    {
        $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        ChatTicket::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'closed_at' => now(),
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $agent2->id,
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $agents = $response->json('data.data.agents');
        $this->assertCount(2, $agents);
    }

    public function test_tracks_sla_violations(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'sla_first_response_breached' => true,
            'sla_resolution_breached' => false,
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'sla_first_response_breached' => false,
            'sla_resolution_breached' => true,
        ]);

        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $agents = $response->json('data.data.agents');
        $this->assertSame(2, $agents[0]['sla_violations']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        ChatTicket::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
            'assigned_to' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.agents'));
    }

    public function test_filters_by_channel(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'channel' => 'whatsapp',
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'channel' => 'email',
        ]);

        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31&channel=whatsapp');

        $response->assertStatus(200);
        $agents = $response->json('data.data.agents');
        $this->assertSame(1, $agents[0]['tickets_handled']);
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/agent-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.agents'));
    }
}
