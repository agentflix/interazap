<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetSlaResolutionReportTest extends \Tests\Feature\Reports\ReportsTestCase
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

    public function test_returns_sla_summary(): void
    {
        ChatTicket::factory()->count(3)->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'first_response_at' => now()->subMinutes(5),
            'closed_at' => now(),
            'sla_first_response_breached' => false,
            'sla_resolution_breached' => false,
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'first_response_at' => now()->subMinutes(30),
            'closed_at' => now(),
            'sla_first_response_breached' => true,
            'sla_resolution_breached' => true,
        ]);

        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertSame(4, $summary['total_tickets']);
        $this->assertSame(75.0, (float) $summary['sla_first_response_rate']);
        $this->assertSame(75.0, (float) $summary['sla_resolution_rate']);
    }

    public function test_returns_by_priority(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'priority' => 'high',
            'first_response_at' => now()->subMinutes(10),
            'closed_at' => now(),
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'priority' => 'low',
            'first_response_at' => now()->subHour(),
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byPriority = $response->json('data.data.by_priority');
        $this->assertCount(2, $byPriority);
    }

    public function test_returns_by_agent(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
            'first_response_at' => now()->subMinutes(5),
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byAgent = $response->json('data.data.by_agent');
        $this->assertNotEmpty($byAgent);
        $this->assertArrayHasKey('agent_name', $byAgent[0]);
    }

    public function test_returns_overdue_tickets(): void
    {
        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $overdue = $response->json('data.data.overdue_tickets');
        $this->assertArrayHasKey('over_24h', $overdue);
        $this->assertArrayHasKey('over_48h', $overdue);
        $this->assertArrayHasKey('over_72h', $overdue);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        ChatTicket::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
            'assigned_to' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_tickets'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/sla-resolution?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_tickets'));
        $this->assertEmpty($response->json('data.data.by_priority'));
        $this->assertEmpty($response->json('data.data.by_agent'));
    }
}
