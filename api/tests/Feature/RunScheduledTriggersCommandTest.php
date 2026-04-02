<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Console\Commands\RunScheduledTriggersCommand;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RunScheduledTriggersCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publishes_due_cron_trigger_with_tenant_context(): void
    {
        Date::setTestNow('2026-03-04 10:15:00');

        $tenant = PlatformTenant::factory()->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Agent Cron',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        AiAgentTrigger::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'agent_id' => $agent->id,
            'type' => 'cron',
            'config' => ['expression' => '* * * * *'],
            'status' => 'active',
        ]);

        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('xadd')->once();

        Redis::shouldReceive('connection')->with('gateway')->andReturn($connectionMock);

        Artisan::call(RunScheduledTriggersCommand::class);

        $this->assertDatabaseHas('ai_agent_triggers', [
            'tenant_id' => $tenant->id,
            'agent_id' => $agent->id,
        ]);

        Date::setTestNow();
    }

    public function test_is_idempotent_within_same_minute(): void
    {
        Date::setTestNow('2026-03-04 10:20:00');

        $tenant = PlatformTenant::factory()->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Agent Cron Idempotent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        AiAgentTrigger::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'agent_id' => $agent->id,
            'type' => 'cron',
            'config' => ['expression' => '* * * * *'],
            'status' => 'active',
        ]);

        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('xadd')->once();

        Redis::shouldReceive('connection')->with('gateway')->andReturn($connectionMock);

        Artisan::call(RunScheduledTriggersCommand::class);
        Artisan::call(RunScheduledTriggersCommand::class);

        Date::setTestNow();
    }
}
