<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Jobs\AiRunTrackerJob;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiUsageLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class AiRunTrackerJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_completed_run_and_emits_completed_chat_activity(): void
    {
        $ticketId = (string) Str::orderedUuid();

        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => $ticketId,
                'message_id' => 'message-1',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.completed', (string) $run->tenant_id, $ticketId, 'message-1');

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
        $ticketId = (string) Str::orderedUuid();

        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => $ticketId,
                'message_id' => 'message-2',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.failed', (string) $run->tenant_id, $ticketId, 'message-2', 'Gateway timeout');

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
        $ticketId = (string) Str::orderedUuid();

        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => $ticketId,
                'message_id' => 'message-3',
            ],
            'completed_at' => null,
        ]);

        $this->expectChatActivityPublish('ai.processing.rejected', (string) $run->tenant_id, $ticketId, 'message-3', null, 'blocked');

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

    public function test_creates_ai_usage_log_on_successful_run(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
            'input_context' => [
                'ticket_id' => (string) Str::orderedUuid(),
                'message_id' => 'message-5',
            ],
        ]);

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'completed',
            'prompt_tokens' => 150,
            'completion_tokens' => 300,
            'model' => 'gpt-4o-mini',
            'output' => [
                'raw' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 300,
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ]))->handle();

        $usageLog = AiUsageLog::query()->first();

        $this->assertNotNull($usageLog);
        $this->assertSame((string) $run->tenant_id, $usageLog->tenant_id);
        $this->assertSame('gpt-4o-mini', $usageLog->model_name);
        $this->assertSame('openai', $usageLog->provider);
        $this->assertSame(150, $usageLog->input_tokens);
        $this->assertSame(300, $usageLog->output_tokens);
        $this->assertSame('autopilot.run', $usageLog->feature);
        $this->assertSame(AiAutopilotRun::class, $usageLog->usable_type);
        $this->assertSame((string) $run->id, $usageLog->usable_id);
        $this->assertSame('completed', $usageLog->metadata['status']);
    }

    public function test_creates_ai_usage_log_with_output_raw_fallback(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'queued',
        ]);

        (new AiRunTrackerJob([
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'completed',
            'output' => [
                'raw' => [
                    'prompt_tokens' => 500,
                    'completion_tokens' => 200,
                    'model' => 'gpt-4o',
                    'cached_prompt_tokens' => 100,
                    'input_cost' => 0.00025,
                    'output_cost' => 0.0006,
                ],
            ],
        ]))->handle();

        $usageLog = AiUsageLog::query()->first();

        $this->assertNotNull($usageLog);
        $this->assertSame('gpt-4o', $usageLog->model_name);
        $this->assertSame(500, $usageLog->input_tokens);
        $this->assertSame(200, $usageLog->output_tokens);
        $this->assertSame(100, $usageLog->cached_prompt_tokens);
        $this->assertSame(0.00025, $usageLog->input_cost);
        $this->assertSame(0.0006, $usageLog->output_cost);
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
