<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetCsatNpsReportTest extends \Tests\Feature\Reports\ReportsTestCase
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

    public function test_returns_nps_summary(): void
    {
        $ticket1 = ChatTicket::factory()->create(['tenant_id' => $this->tenantId, 'assigned_to' => $this->user->id]);
        $ticket2 = ChatTicket::factory()->create(['tenant_id' => $this->tenantId, 'assigned_to' => $this->user->id]);
        $ticket3 = ChatTicket::factory()->create(['tenant_id' => $this->tenantId, 'assigned_to' => $this->user->id]);

        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket1->id,
            'token' => Str::uuid()->toString(),
            'rating' => 5,
            'submitted_at' => now(),
        ]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket2->id,
            'token' => Str::uuid()->toString(),
            'rating' => 4,
            'submitted_at' => now(),
        ]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket3->id,
            'token' => Str::uuid()->toString(),
            'rating' => 1,
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertSame(3, $summary['total_evaluations']);
        $this->assertSame(2, $summary['promoters']);
        $this->assertSame(1, $summary['detractors']);
    }

    public function test_returns_rating_distribution(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => Str::uuid()->toString(),
            'rating' => 5,
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $distribution = $response->json('data.data.distribution');
        $this->assertNotEmpty($distribution);
        $this->assertArrayHasKey('rating', $distribution[0]);
        $this->assertArrayHasKey('count', $distribution[0]);
    }

    public function test_returns_negative_comments(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => Str::uuid()->toString(),
            'rating' => 1,
            'comment' => 'Terrible service',
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $comments = $response->json('data.data.negative_comments');
        $this->assertNotEmpty($comments);
        $this->assertSame('Terrible service', $comments[0]['comment']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $otherUser->tenant_id,
            'ticket_id' => $ticket->id,
            'token' => Str::uuid()->toString(),
            'rating' => 5,
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_evaluations'));
    }

    public function test_returns_by_agent(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'assigned_to' => $this->user->id,
        ]);
        ChatTicketEvaluation::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => Str::uuid()->toString(),
            'rating' => 4,
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byAgent = $response->json('data.data.by_agent');
        $this->assertNotEmpty($byAgent);
        $this->assertArrayHasKey('agent_name', $byAgent[0]);
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/csat-nps?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_evaluations'));
        $this->assertEmpty($response->json('data.data.distribution'));
    }
}
