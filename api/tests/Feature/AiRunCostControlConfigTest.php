<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Listeners\AutopilotRunDispatcherListener;
use Domain\Ai\Models\AiAgent;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class AiRunCostControlConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_autopilot_cost_control_defaults_are_loaded_from_config(): void
    {
        putenv('AI_AUTOPILOT_MAX_TOKENS');
        putenv('AI_MAX_TOOL_ITERATIONS');
        putenv('AI_RUN_TOKEN_BUDGET');
        putenv('AI_COMPACT_TOOL_RESULTS');

        config()->set('ai', require config_path('ai.php'));

        $this->assertSame(800, (int) config('ai.autopilot.max_tokens'));
        $this->assertSame(5, (int) config('ai.autopilot.max_tool_iterations'));
        $this->assertSame(32000, (int) config('ai.autopilot.run_token_budget'));
        $this->assertTrue((bool) config('ai.autopilot.compact_tool_results'));
    }

    public function test_autopilot_cost_control_overrides_are_loaded_from_env(): void
    {
        putenv('AI_AUTOPILOT_MAX_TOKENS=1024');
        putenv('AI_MAX_TOOL_ITERATIONS=7');
        putenv('AI_RUN_TOKEN_BUDGET=4500');
        putenv('AI_COMPACT_TOOL_RESULTS=false');

        config()->set('ai', require config_path('ai.php'));

        $this->assertSame(1024, (int) config('ai.autopilot.max_tokens'));
        $this->assertSame(7, (int) config('ai.autopilot.max_tool_iterations'));
        $this->assertSame(4500, (int) config('ai.autopilot.run_token_budget'));
        $this->assertFalse((bool) config('ai.autopilot.compact_tool_results'));

        putenv('AI_AUTOPILOT_MAX_TOKENS');
        putenv('AI_MAX_TOOL_ITERATIONS');
        putenv('AI_RUN_TOKEN_BUDGET');
        putenv('AI_COMPACT_TOOL_RESULTS');
        config()->set('ai', require config_path('ai.php'));
    }

    public function test_dispatcher_propagates_cost_control_fields_to_ai_run_request_payload(): void
    {
        config()->set('ai.autopilot.max_tokens', 800);
        config()->set('ai.autopilot.max_tool_iterations', 5);
        config()->set('ai.autopilot.run_token_budget', 3000);
        config()->set('ai.autopilot.compact_tool_results', true);

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Primary agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'metadata' => [],
            'is_active' => true,
        ]);

        $messageId = (string) Str::orderedUuid();

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::on(fn (array $payload): bool => $payload['tenant_id'] === (string) $tenant->id
                && $payload['ticket_id'] === (string) $ticket->id
                && $payload['agent_id'] === (string) $agent->id
                && $payload['max_tokens'] === '800'
                && $payload['max_tool_iterations'] === '5'
                && $payload['run_token_budget'] === '3000'
                && $payload['compact_tool_results'] === 'true'))
            ->andReturn('1-0');
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::type('string'))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Hello AI',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));
    }
}
