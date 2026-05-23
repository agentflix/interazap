<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class AiRunExecutionJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publishes_delegation_result_when_run_has_parent(): void
    {
        $parentRun = AiAutopilotRun::factory()->create();

        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'parent_run_id' => (string) $parentRun->id,
            'output' => null,
        ]);

        $redis = Mockery::mock();
        $redis->shouldReceive('lpush')
            ->once()
            ->with(
                'ai:delegation:result:'.$parentRun->id,
                Mockery::on(function (string $payload) use ($run): bool {
                    $decoded = json_decode($payload, true);

                    return is_array($decoded)
                        && ($decoded['child_run_id'] ?? null) === (string) $run->id
                        && ($decoded['status'] ?? null) === 'blocked'
                        && is_string($decoded['output']['error'] ?? null);
                })
            )
            ->andReturn(1);
        $redis->shouldReceive('expire')
            ->once()
            ->with('ai:delegation:result:'.$parentRun->id, 30)
            ->andReturn(true);
        $redis->shouldReceive('publish')->zeroOrMoreTimes();

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($redis);

        (new AiRunExecutionJob((string) $run->id, (string) $run->tenant_id))->handle(app(\Domain\Ai\Actions\AiAutopilotRunActions::class));
    }

    public function test_does_not_publish_delegation_result_without_parent_run(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'parent_run_id' => null,
            'output' => null,
        ]);

        $redis = Mockery::mock();
        $redis->shouldReceive('lpush')->never();
        $redis->shouldReceive('expire')->never();
        $redis->shouldReceive('publish')->zeroOrMoreTimes();

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($redis);

        (new AiRunExecutionJob((string) $run->id, (string) $run->tenant_id))->handle(app(\Domain\Ai\Actions\AiAutopilotRunActions::class));
    }
}
