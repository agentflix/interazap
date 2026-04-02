<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Jobs\AiRunTrackerJob;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class AiRunTrackerJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_completed_run_and_emits_completed_chat_activity(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => 'ticket-1',
                'message_id' => 'message-1',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.completed', (string) $run->tenant_id, 'ticket-1', 'message-1');

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'completed',
            'output' => ['response' => 'ok'],
        ]))->handle();

        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(['response' => 'ok'], $run->output);
        $this->assertNotNull($run->completed_at);
    }

    public function test_updates_failed_run_and_emits_failed_chat_activity(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => 'ticket-2',
                'message_id' => 'message-2',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.failed', (string) $run->tenant_id, 'ticket-2', 'message-2', 'Gateway timeout');

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'failed',
            'output' => ['error' => 'Gateway timeout'],
        ]))->handle();

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame(['error' => 'Gateway timeout'], $run->output);
        $this->assertNotNull($run->completed_at);
    }

    public function test_maps_blocked_run_to_rejected_chat_activity(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => 'ticket-3',
                'message_id' => 'message-3',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.rejected', (string) $run->tenant_id, 'ticket-3', 'message-3', null, 'blocked');

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'blocked',
            'output' => ['error' => 'Policy blocked the run'],
        ]))->handle();

        $run->refresh();

        $this->assertSame('blocked', $run->status);
        $this->assertSame(['error' => 'Policy blocked the run'], $run->output);
        $this->assertNotNull($run->completed_at);
    }

    public function test_has_expected_retry_configuration(): void
    {
        $job = new AiRunTrackerJob([
            'run_id' => 'run-1',
            'tenant_id' => 'tenant-1',
        ]);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }

    public function test_handle_is_idempotent_when_run_is_already_terminal(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'completed',
            'output' => ['response' => 'already done'],
            'input_context' => [
                'ticket_id' => 'ticket-4',
                'message_id' => 'message-4',
            ],
            'completed_at' => now()->subMinute(),
        ]);

        Redis::shouldReceive('connection')->never();

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'failed',
            'output' => ['error' => 'must not override terminal'],
        ]))->handle();

        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(['response' => 'already done'], $run->output);
    }

    public function test_failed_marks_run_with_tracker_exhausted_flag(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'output' => null,
        ]);

        $job = new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
        ]);

        $job->failed(new \RuntimeException('Tracker retries exhausted'));

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame('tracker_exhausted', data_get($run->output, 'error_code'));
        $this->assertSame('Tracker retries exhausted', data_get($run->output, 'error'));
        $this->assertNotNull($run->completed_at);
    }

    private function expectChatActivityPublish(string $type, string $tenantId, string $ticketId, string $messageId, ?string $error = null, ?string $sourceStatus = null): void
    {
        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($type, $tenantId, $ticketId, $messageId, $error, $sourceStatus): bool {
                $decoded = json_decode($payload, true);

                if (! is_array($decoded)) {
                    return false;
                }

                if (($decoded['event'] ?? null) !== 'chat.activity' || ($decoded['tenant_id'] ?? null) !== $tenantId) {
                    return false;
                }

                if (data_get($decoded, 'data.ticketId') !== $ticketId || data_get($decoded, 'data.subevents.0.type') !== $type) {
                    return false;
                }

                if (data_get($decoded, 'data.subevents.0.data.message_id') !== $messageId) {
                    return false;
                }

                if ($error !== null && data_get($decoded, 'data.subevents.0.data.error') !== $error) {
                    return false;
                }

                if ($sourceStatus !== null && data_get($decoded, 'data.subevents.0.data.source_status') !== $sourceStatus) {
                    return false;
                }

                return true;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with('gateway')
            ->andReturn($redisConnection);
    }
}
