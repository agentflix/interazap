<?php

declare(strict_types=1);

namespace Tests\E2E\Autopilot;

use Domain\Ai\Jobs\AiRunTrackerJob;
use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatAiActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ApprovalRejectedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejected_approval_failure_signal_marks_run_failed_immediately(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => (string) Str::orderedUuid(),
                'message_id' => (string) Str::orderedUuid(),
            ],
        ]);

        AiAutopilotApproval::factory()->rejected('Operador rejeitou aprovação')->create([
            'tenant_id' => (string) $run->tenant_id,
            'run_id' => (string) $run->id,
        ]);

        $broadcast = Mockery::mock(ChatActivityBroadcastService::class);
        $broadcast->shouldReceive('emit')->once();
        $activity = new ChatAiActivityService($broadcast);

        $payload = [
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => 'failed',
            'output' => [
                'error' => 'approval_rejected_by_user',
                'error_code' => 'approval_rejected',
            ],
            'completed_at' => now()->toIso8601String(),
        ];

        (new AiRunTrackerJob($payload, $activity))->handle();

        $run->refresh();
        $this->assertSame('failed', (string) $run->status);
        $this->assertSame('approval_rejected', (string) data_get($run->output, 'error_code'));
        $this->assertNotNull($run->completed_at);
    }
}
