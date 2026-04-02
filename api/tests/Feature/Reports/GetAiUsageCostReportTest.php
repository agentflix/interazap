<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetAiUsageCostReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.ai.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_usage_summary(): void
    {
        $this->createAiLog(inputTokens: 100, outputTokens: 50, inputCost: 0.001, outputCost: 0.002);
        $this->createAiLog(inputTokens: 200, outputTokens: 100, inputCost: 0.002, outputCost: 0.004);

        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertSame(450, $summary['total_tokens']);
        $this->assertSame(2, $summary['call_count']);
    }

    public function test_returns_by_feature(): void
    {
        $this->createAiLog(feature: 'chat_completion');
        $this->createAiLog(feature: 'embeddings');

        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byFeature = $response->json('data.data.by_feature');
        $this->assertCount(2, $byFeature);
    }

    public function test_returns_by_model(): void
    {
        $this->createAiLog(modelName: 'gpt-4', provider: 'openai');
        $this->createAiLog(modelName: 'claude-3', provider: 'anthropic');

        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byModel = $response->json('data.data.by_model');
        $this->assertCount(2, $byModel);
    }

    public function test_returns_top_users(): void
    {
        $this->createAiLog(inputCost: 0.01, outputCost: 0.02, userId: $this->user->id);

        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $topUsers = $response->json('data.data.top_users');
        $this->assertNotEmpty($topUsers);
        $this->assertArrayHasKey('user_name', $topUsers[0]);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        DB::table('ai_usage_logs')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $otherUser->tenant_id,
            'model_name' => 'gpt-4',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'input_cost' => 0.001,
            'output_cost' => 0.002,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.call_count'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/ai-usage-cost?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.data.summary.total_tokens'));
        $this->assertEmpty($response->json('data.data.by_feature'));
    }

    /**
     * Helper para criar registro em ai_usage_logs.
     */
    private function createAiLog(
        int $inputTokens = 100,
        int $outputTokens = 50,
        float $inputCost = 0.001,
        float $outputCost = 0.002,
        string $modelName = 'gpt-4',
        ?string $provider = 'openai',
        ?string $feature = 'chat',
        ?string $userId = null,
        int $latencyMs = 500,
    ): void {
        DB::table('ai_usage_logs')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenantId,
            'user_id' => $userId,
            'model_name' => $modelName,
            'provider' => $provider,
            'feature' => $feature,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'latency_ms' => $latencyMs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
