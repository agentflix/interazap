<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Domain\Ai\Actions\AiUsageActions;
use Domain\Ai\Models\AiUsageLog;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiUsageActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AiUsageActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new AiUsageActions;
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow();
        parent::tearDown();
    }

    public function test_daily_returns_stats_for_last_n_days(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 30));

        $tenant = PlatformTenant::factory()->create();

        // Create usage logs for last 3 days
        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.0003,
            'output_cost' => 0.00075,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 30),
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 200,
            'output_tokens' => 100,
            'input_cost' => 0.0006,
            'output_cost' => 0.0015,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 29),
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 50,
            'output_tokens' => 25,
            'input_cost' => 0.00015,
            'output_cost' => 0.000375,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 28),
        ]);

        // Old log outside range
        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 20),
        ]);

        $result = $this->actions->daily($tenant->id, 7);

        $this->assertCount(3, $result);
    }

    public function test_daily_aggregates_multiple_logs_per_day(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 30));

        $tenant = PlatformTenant::factory()->create();

        // Create 3 logs for the same day
        for ($i = 0; $i < 3; $i++) {
            AiUsageLog::factory()->create([
                'tenant_id' => $tenant->id,
                'input_tokens' => 100,
                'output_tokens' => 50,
                'input_cost' => 0.0003,
                'output_cost' => 0.00075,
                'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 30, 10 + $i),
            ]);
        }

        $result = $this->actions->daily($tenant->id, 7);

        $this->assertCount(1, $result);
        $dayData = $result->first();
        $this->assertEquals(3, $dayData->requests);
        $this->assertEquals(450, $dayData->tokens); // (100+50) * 3
    }

    public function test_daily_filters_by_tenant(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 30));

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        AiUsageLog::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 30),
        ]);

        AiUsageLog::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 30),
        ]);

        $result = $this->actions->daily($tenant->id, 7);

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result->first()->requests);
    }

    public function test_top_agents_returns_ordered_by_cost(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 15));

        $tenant = PlatformTenant::factory()->create();

        // High cost feature
        AiUsageLog::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'feature' => 'automation',
            'input_cost' => 0.01,
            'output_cost' => 0.05,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
        ]);

        // Medium cost feature
        AiUsageLog::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'feature' => 'chat',
            'input_cost' => 0.005,
            'output_cost' => 0.02,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
        ]);

        // Low cost feature
        AiUsageLog::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'feature' => 'summary',
            'input_cost' => 0.001,
            'output_cost' => 0.005,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
        ]);

        $result = $this->actions->topAgents($tenant->id, 10);

        $this->assertCount(3, $result);
        $this->assertSame('automation', $result[0]->agent_name);
        $this->assertSame('chat', $result[1]->agent_name);
        $this->assertSame('summary', $result[2]->agent_name);
    }

    public function test_top_agents_excludes_null_features(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 15));

        $tenant = PlatformTenant::factory()->create();

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'feature' => 'chat',
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'feature' => null,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
        ]);

        $result = $this->actions->topAgents($tenant->id, 10);

        $this->assertCount(1, $result);
        $this->assertSame('chat', $result[0]->agent_name);
    }

    public function test_top_agents_respects_limit(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 15));

        $tenant = PlatformTenant::factory()->create();

        $features = ['chat', 'automation', 'rag', 'summary', 'analysis'];
        foreach ($features as $i => $feature) {
            AiUsageLog::factory()->create([
                'tenant_id' => $tenant->id,
                'feature' => $feature,
                'input_cost' => 0.01 * ($i + 1),
                'output_cost' => 0.02 * ($i + 1),
                'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 10),
            ]);
        }

        $result = $this->actions->topAgents($tenant->id, 3);

        $this->assertCount(3, $result);
    }

    public function test_monthly_history_returns_formatted_months(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 3, 15));

        $tenant = PlatformTenant::factory()->create();

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.0003,
            'output_cost' => 0.00075,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 3, 10),
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 200,
            'output_tokens' => 100,
            'input_cost' => 0.0006,
            'output_cost' => 0.0015,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 2, 15),
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 300,
            'output_tokens' => 150,
            'input_cost' => 0.0009,
            'output_cost' => 0.00225,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 1, 20),
        ]);

        $result = $this->actions->monthlyHistory($tenant->id, 6);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        // Check that each month has proper format
        foreach ($result as $month) {
            $this->assertArrayHasKey('month', $month);
            $this->assertArrayHasKey('requests', $month);
            $this->assertArrayHasKey('tokens', $month);
            $this->assertArrayHasKey('cost', $month);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $month['month']);
        }
    }

    public function test_monthly_history_filters_by_tenant(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 3, 15));

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        AiUsageLog::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 3, 10),
        ]);

        AiUsageLog::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'created_at' => \Illuminate\Support\Facades\Date::create(2026, 3, 10),
        ]);

        $result = $this->actions->monthlyHistory($tenant->id, 6);

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result[0]['requests']);
    }
}
