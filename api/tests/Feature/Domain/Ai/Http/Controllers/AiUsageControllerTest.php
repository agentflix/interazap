<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Models\AiUsageLog;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @group ai
 * @group controllers
 */
class AiUsageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permission);
    }

    public function test_summary_returns_usage_stats(): void
    {
        Sanctum::actingAs($this->user);

        // Create some usage logs
        AiUsageLog::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.01,
            'output_cost' => 0.005,
        ]);

        $response = $this->getJson('/api/ai/usage/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_start',
                    'period_end',
                    'total_requests',
                    'total_input_tokens',
                    'total_output_tokens',
                    'total_tokens',
                    'total_cost',
                    'formatted_cost',
                    'avg_latency_ms',
                    'cost_change_percent',
                    'cost_trend',
                ],
            ]);

        $data = $response->json('data');
        expect($data['total_requests'])->toBe(5);
        expect($data['total_input_tokens'])->toBe(500);
        expect($data['total_output_tokens'])->toBe(250);
    }

    public function test_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/usage/summary');

        $response->assertUnauthorized();
    }

    public function test_summary_returns_empty_for_no_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/usage/summary');

        $response->assertOk();

        $data = $response->json('data');
        expect($data['total_requests'])->toBe(0);
        expect((float) $data['total_cost'])->toBe(0.0);
    }

    public function test_daily_returns_daily_breakdown(): void
    {
        Sanctum::actingAs($this->user);

        // Create logs for different days
        AiUsageLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(2),
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.01,
            'output_cost' => 0.005,
        ]);
        AiUsageLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
            'input_tokens' => 200,
            'output_tokens' => 100,
            'input_cost' => 0.02,
            'output_cost' => 0.01,
        ]);

        $response = $this->getJson('/api/ai/usage/daily?days=7');

        $response->assertOk();

        $data = $response->json('data');
        expect(count($data))->toBeGreaterThanOrEqual(2);
    }

    public function test_daily_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/usage/daily');

        $response->assertUnauthorized();
    }

    public function test_top_agents_returns_agent_stats(): void
    {
        Sanctum::actingAs($this->user);

        // Create logs for different agents
        AiUsageLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'feature' => 'sales_qualifier',
            'input_cost' => 0.05,
            'output_cost' => 0.025,
        ]);
        AiUsageLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'feature' => 'support_agent',
            'input_cost' => 0.03,
            'output_cost' => 0.015,
        ]);

        $response = $this->getJson('/api/ai/usage/top-agents?limit=10');

        $response->assertOk();

        $data = $response->json('data');
        expect(count($data))->toBe(2);
        // First should be the one with highest cost
        expect($data[0]['agent_name'])->toBe('sales_qualifier');
        expect($data[0]['total_requests'])->toBe(3);
    }

    public function test_top_agents_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/usage/top-agents');

        $response->assertUnauthorized();
    }

    public function test_monthly_history_returns_monthly_stats(): void
    {
        Sanctum::actingAs($this->user);

        // Create logs for different months
        AiUsageLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subMonth(),
            'input_cost' => 0.10,
            'output_cost' => 0.05,
        ]);
        AiUsageLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
            'input_cost' => 0.20,
            'output_cost' => 0.10,
        ]);

        $response = $this->getJson('/api/ai/usage/monthly?months=3');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_monthly_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/usage/monthly');

        $response->assertUnauthorized();
    }

    public function test_summary_isolates_by_tenant(): void
    {
        Sanctum::actingAs($this->user);

        // Create logs for this tenant (in current month)
        AiUsageLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
        ]);

        // Create logs for another tenant with much higher costs
        $otherTenant = PlatformTenant::factory()->create();
        AiUsageLog::factory()->count(10)->create([
            'tenant_id' => $otherTenant->id,
            'input_cost' => 100.00,
            'output_cost' => 50.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/ai/usage/summary');

        $response->assertOk();

        $data = $response->json('data');
        // Should only see 1 request from our tenant
        expect($data['total_requests'])->toBe(1);
        // Cost should be less than what other tenant has (1500 total)
        // This validates tenant isolation without needing exact value
        expect((float) $data['total_cost'])->toBeLessThan(100.0);
    }
}
