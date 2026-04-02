<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Console\Commands\ConsumeToolRequestsCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class ConsumeToolRequestsCommandTest extends TestCase
{
    public function test_sends_failed_tool_request_to_dlq(): void
    {
        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('xgroup')->once()->andReturnTrue();
        $connectionMock->shouldReceive('xreadgroup')->once()->andReturn([
            'ai.tool.request' => [
                '1-0' => [
                    'tool_name' => 'non_existing_tool',
                    'parameters' => json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR),
                    'context' => json_encode(['tenant_id' => 'tenant-1'], JSON_THROW_ON_ERROR),
                    'reply_key' => 'ai.tool.reply:1',
                    'request_id' => "\xB1\x31",
                ],
            ],
        ]);
        $connectionMock->shouldReceive('xadd')
            ->once()
            ->with('ai.tool.dlq', '*', Mockery::on(fn (array $fields): bool => ($fields['event'] ?? null) === 'ai.tool.request.failed'
                && is_string($fields['error'] ?? null)
                && isset($fields['payload'])))
            ->andReturn('2-0');
        $connectionMock->shouldReceive('lpush')->zeroOrMoreTimes();
        $connectionMock->shouldReceive('expire')->zeroOrMoreTimes();
        $connectionMock->shouldReceive('xack')->once()->andReturn(1);

        Redis::shouldReceive('connection')->with('gateway')->andReturn($connectionMock);

        Artisan::call(ConsumeToolRequestsCommand::class, ['--once' => true]);
    }
}
