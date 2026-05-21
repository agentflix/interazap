<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Jobs\AutopilotApprovalExpiryJob;
use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class AutopilotApprovalExpiryJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expires_pending_approval_and_fails_associated_run(): void
    {
        $runToExpire = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
        ]);

        $approvalToExpire = AiAutopilotApproval::factory()->create([
            'tenant_id' => (string) $runToExpire->tenant_id,
            'run_id' => (string) $runToExpire->id,
            'status' => 'pending',
            'expires_at' => now()->subHour(),
            'expired_at' => null,
        ]);

        $freshRun = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
        ]);

        $freshApproval = AiAutopilotApproval::factory()->create([
            'tenant_id' => (string) $freshRun->tenant_id,
            'run_id' => (string) $freshRun->id,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'expired_at' => null,
        ]);

        (new AutopilotApprovalExpiryJob)->handle();

        $approvalToExpire->refresh();
        $runToExpire->refresh();
        $freshApproval->refresh();
        $freshRun->refresh();

        $this->assertSame('expired', $approvalToExpire->status);
        $this->assertNotNull($approvalToExpire->expired_at);
        $this->assertSame('failed', $runToExpire->status);
        $this->assertNotNull($runToExpire->completed_at);

        $this->assertSame('pending', $freshApproval->status);
        $this->assertNull($freshApproval->expired_at);
        $this->assertSame('running', $freshRun->status);
    }

    public function test_does_not_overwrite_terminal_run_status_when_approval_expires(): void
    {
        $completedRun = AiAutopilotRun::factory()->create([
            'status' => 'completed',
            'completed_at' => now()->subMinutes(10),
            'output' => ['message' => 'already done'],
        ]);

        $approval = AiAutopilotApproval::factory()->create([
            'tenant_id' => (string) $completedRun->tenant_id,
            'run_id' => (string) $completedRun->id,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(5),
            'expired_at' => null,
        ]);

        (new AutopilotApprovalExpiryJob)->handle();

        $approval->refresh();
        $completedRun->refresh();

        $this->assertSame('expired', $approval->status);
        $this->assertNotNull($approval->expired_at);
        $this->assertSame('completed', $completedRun->status);
        $this->assertSame('already done', data_get($completedRun->output, 'message'));
    }
}
