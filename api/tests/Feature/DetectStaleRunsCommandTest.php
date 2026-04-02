<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Console\Commands\DetectStaleRunsCommand;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class DetectStaleRunsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformPlanSeeder::class);
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_marks_stale_runs_as_failed_and_ignores_recent_ones(): void
    {
        $staleRun = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-stale',
                'message_id' => 'message-stale',
            ],
            'updated_at' => now()->subMinutes(10),
        ]);

        $freshRun = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-fresh',
                'message_id' => 'message-fresh',
            ],
            'updated_at' => now()->subMinute(),
        ]);

        AiAutopilotRun::query()->whereKey((string) $staleRun->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);

        AiAutopilotRun::query()->whereKey((string) $freshRun->id)->update([
            'updated_at' => now()->subMinute(),
        ]);

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.failed'
                    && data_get($decoded, 'data.subevents.0.data.error') === 'stale_run_timeout';
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')->with('gateway')->andReturn($redisConnection);

        Artisan::call(DetectStaleRunsCommand::class);

        $staleRun->refresh();
        $freshRun->refresh();

        $this->assertSame('failed', $staleRun->status);
        $this->assertSame('stale_run_timeout', data_get($staleRun->output, 'error_code'));
        $this->assertNotNull($staleRun->completed_at);

        $this->assertSame('running', $freshRun->status);
        $this->assertNull($freshRun->completed_at);
    }

    public function test_marks_orphaned_queued_runs_as_failed_after_threshold(): void
    {
        $orphanedRun = AiAutopilotRun::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'queued',
            'completed_at' => null,
            'input_context' => [],
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $freshQueuedRun = AiAutopilotRun::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'queued',
            'completed_at' => null,
            'input_context' => [],
            'created_at' => now()->subSeconds(30),
            'updated_at' => now()->subSeconds(30),
        ]);

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($orphanedRun): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'ai.run.failed'
                    && data_get($decoded, 'data.run_id') === $orphanedRun->id
                    && data_get($decoded, 'data.tenant_id') === $orphanedRun->tenant_id
                    && data_get($decoded, 'data.status') === 'failed'
                    && data_get($decoded, 'data.error_code') === 'orphaned_queued_run'
                    && array_key_exists('event_id', $decoded['data'])
                    && array_key_exists('timestamp', $decoded['data']);
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')->with('gateway')->andReturn($redisConnection);

        Artisan::call(DetectStaleRunsCommand::class, ['--threshold' => 5]);

        $orphanedRun->refresh();
        $freshQueuedRun->refresh();

        $this->assertSame('failed', $orphanedRun->status);
        $this->assertSame('orphaned_queued_run', data_get($orphanedRun->output, 'error_code'));
        $this->assertNotNull($orphanedRun->completed_at);
        $this->assertSame('queued', $freshQueuedRun->status);
        $this->assertNull($freshQueuedRun->completed_at);
    }
}
