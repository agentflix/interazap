<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GetChatVolumeReportTest extends \Tests\Feature\Reports\ReportsTestCase
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

    public function test_returns_volume_summary(): void
    {
        ChatTicket::factory()->count(5)->create([
            'tenant_id' => $this->tenantId,
        ]);

        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertSame(5, $summary['total_tickets']);
        $this->assertGreaterThan(0, $summary['avg_daily']);
    }

    public function test_returns_by_channel(): void
    {
        ChatTicket::factory()->count(2)->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'whatsapp',
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'email',
        ]);

        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byChannel = $response->json('data.data.by_channel');
        $this->assertCount(2, $byChannel);
    }

    public function test_returns_heatmap(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $heatmap = $response->json('data.data.heatmap');
        $this->assertNotEmpty($heatmap);
        $this->assertArrayHasKey('day_of_week', $heatmap[0]);
        $this->assertArrayHasKey('hour', $heatmap[0]);
    }

    public function test_returns_auto_resolution_stats(): void
    {
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'closed_at' => now(),
            'human_takeover_at' => null,
        ]);
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'closed_at' => now(),
            'human_takeover_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $auto = $response->json('data.data.auto_resolution');
        $this->assertSame(2, $auto['total']);
        $this->assertSame(1, $auto['auto_resolved']);
        $this->assertSame(1, $auto['human_takeover']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        ChatTicket::factory()->count(5)->create([
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_tickets'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/chat-volume?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_tickets'));
        $this->assertEmpty($response->json('data.data.by_channel'));
    }
}
