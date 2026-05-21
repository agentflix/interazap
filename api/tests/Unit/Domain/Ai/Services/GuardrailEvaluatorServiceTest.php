<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class GuardrailEvaluatorServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('ai.autopilot.static_guardrails', []);
    }

    public function test_merges_static_and_database_guardrails_per_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();

        config()->set('ai.autopilot.static_guardrails', [
            [
                'id' => 'static-log-1',
                'name' => 'Static Log',
                'rule_type' => 'LOG',
                'conditions' => [
                    'action_type' => 'tool_call',
                ],
            ],
        ]);

        $dbGuardrail = AiAutopilotGuardrail::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'DB Block Guardrail',
            'rule_type' => 'BLOCK',
            'is_active' => true,
            'conditions' => [
                'action_type' => 'tool_call',
            ],
        ]);

        $service = app(GuardrailEvaluatorService::class);
        $result = $service->evaluate(
            tenantId: (string) $tenant->id,
            actionType: 'tool_call',
            input: ['tool' => 'TransferToHumanTool'],
            output: [],
        );

        $this->assertTrue($result->blocked);
        $this->assertCount(2, $result->evaluations);
        $this->assertSame('static-log-1', $result->evaluations[0]['guardrail_id']);
        $this->assertSame((string) $dbGuardrail->id, $result->evaluations[1]['guardrail_id']);
    }

    public function test_uses_cached_guardrails_on_second_call(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $guardrail = AiAutopilotGuardrail::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Cacheable DB Block Guardrail',
            'rule_type' => 'BLOCK',
            'is_active' => true,
            'conditions' => [
                'action_type' => 'tool_call',
            ],
        ]);

        $service = app(GuardrailEvaluatorService::class);
        $cacheKey = "autopilot:guardrails:tenant:{$tenant->id}";

        $first = $service->evaluate(
            tenantId: (string) $tenant->id,
            actionType: 'tool_call',
            input: [],
            output: [],
        );

        $this->assertTrue($first->blocked);
        $this->assertTrue(Cache::has($cacheKey));

        // Query-builder delete intentionally bypasses model events/observer cache invalidation.
        AiAutopilotGuardrail::query()
            ->where('id', (string) $guardrail->id)
            ->delete();

        $second = $service->evaluate(
            tenantId: (string) $tenant->id,
            actionType: 'tool_call',
            input: [],
            output: [],
        );

        $this->assertTrue($second->blocked);
        $this->assertCount(1, $second->evaluations);
        $this->assertSame((string) $guardrail->id, $second->evaluations[0]['guardrail_id']);
    }
}
