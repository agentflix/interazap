<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Models\AiModelPricing;
use Domain\Ai\Models\AiUsageLog;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group usage
 */
class AiUsageLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_be_created_with_factory(): void
    {
        $log = AiUsageLog::factory()->create();

        expect($log)->toBeInstanceOf(AiUsageLog::class);
        expect($log->id)->toBeString();
        expect($log->tenant_id)->toBeString();
    }

    public function test_it_has_correct_table_name(): void
    {
        $log = new AiUsageLog;
        expect($log->getTable())->toBe('ai_usage_logs');
    }

    public function test_it_belongs_to_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $log = AiUsageLog::factory()->create(['tenant_id' => $tenant->id]);

        expect($log->tenant)->toBeInstanceOf(PlatformTenant::class);
        expect($log->tenant->id)->toBe($tenant->id);
    }

    public function test_it_optionally_belongs_to_user(): void
    {
        $user = AuthUser::factory()->create();
        $log = AiUsageLog::factory()->create(['user_id' => $user->id]);

        expect($log->user)->toBeInstanceOf(AuthUser::class);
        expect($log->user->id)->toBe($user->id);
    }

    public function test_it_optionally_belongs_to_pricing(): void
    {
        $pricing = AiModelPricing::factory()->create();
        $log = AiUsageLog::factory()->create([
            'ai_model_pricing_id' => $pricing->id,
        ]);

        expect($log->pricing)->toBeInstanceOf(AiModelPricing::class);
        expect($log->pricing->id)->toBe($pricing->id);
    }

    public function test_it_calculates_cost_from_tokens(): void
    {
        $log = AiUsageLog::factory()->create([
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'input_cost' => 0.003,
            'output_cost' => 0.0075,
        ]);

        expect($log->total_cost)->toBeGreaterThan(0.0104);
        expect($log->total_cost)->toBeLessThan(0.0106);
    }

    public function test_it_scopes_by_date_range(): void
    {
        AiUsageLog::factory()->create(['created_at' => now()->subDays(5)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(15)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(100)]);

        $recent = AiUsageLog::withinDays(10)->get();

        expect($recent)->toHaveCount(1);
    }

    public function test_it_scopes_older_than_days(): void
    {
        AiUsageLog::factory()->create(['created_at' => now()->subDays(5)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(95)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(100)]);

        $old = AiUsageLog::olderThanDays(90)->get();

        expect($old)->toHaveCount(2);
    }

    public function test_it_aggregates_usage_by_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'input_cost' => 0.003,
            'output_cost' => 0.0075,
        ]);

        AiUsageLog::factory()->create([
            'tenant_id' => $tenant->id,
            'input_tokens' => 2000,
            'output_tokens' => 1000,
            'input_cost' => 0.006,
            'output_cost' => 0.015,
        ]);

        $stats = \Domain\Ai\Models\AiUsageLog::query()->where('tenant_id', $tenant->id)
            ->selectRaw('SUM(input_tokens) as total_input, SUM(output_tokens) as total_output')
            ->selectRaw('SUM(input_cost + output_cost) as total_cost')
            ->first();

        expect((int) $stats->total_input)->toBe(3000);
        expect((int) $stats->total_output)->toBe(1500);
    }

    public function test_it_stores_model_name(): void
    {
        $log = AiUsageLog::factory()->create([
            'model_name' => 'gpt-4o',
        ]);

        expect($log->model_name)->toBe('gpt-4o');
    }

    public function test_it_can_have_request_id_for_tracing(): void
    {
        $requestId = 'req_abc123xyz';
        $log = AiUsageLog::factory()->create([
            'request_id' => $requestId,
        ]);

        expect($log->request_id)->toBe($requestId);
    }
}
