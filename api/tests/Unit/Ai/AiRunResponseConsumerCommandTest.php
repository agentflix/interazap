<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Console\Commands\ConsumeAiRunResponsesCommand;
use Domain\Ai\Jobs\AiRunTrackerJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class AiRunResponseConsumerCommandTest extends TestCase
{
    private function mockGatewayConnection(?array $xreadgroupReturn = null): \Mockery\MockInterface
    {
        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('xgroup')->andReturnTrue();
        $connectionMock->shouldReceive('xreadgroup')->once()->andReturn($xreadgroupReturn);
        $connectionMock->shouldReceive('xack')->zeroOrMoreTimes();

        Redis::shouldReceive('connection')->with('gateway')->andReturn($connectionMock);

        return $connectionMock;
    }

    public function test_consumes_ai_run_response_and_dispatches_tracker_job(): void
    {
        Bus::fake();

        $streamName = (string) config('gateway.streams.ai_response', 'ai.run.response');

        $this->mockGatewayConnection([
            $streamName => [
                '1-0' => [
                    'run_id' => 'run-1',
                    'tenant_id' => 'tenant-1',
                    'status' => 'completed',
                    'output' => json_encode(['answer' => 'ok'], JSON_THROW_ON_ERROR),
                ],
            ],
        ]);

        Artisan::call(ConsumeAiRunResponsesCommand::class, ['--once' => true]);

        Bus::assertDispatched(AiRunTrackerJob::class);
    }
}
