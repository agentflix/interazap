<?php

declare(strict_types=1);

namespace Tests\E2E\Autopilot;

use Domain\Ai\Jobs\AutopilotApprovalExpiryJob;
use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApprovalExpiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_approval_marks_approval_and_run_as_failed(): void
    {
        $run = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
        ]);

        $approval = AiAutopilotApproval::factory()->create([
            'tenant_id' => (string) $run->tenant_id,
            'run_id' => (string) $run->id,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(30),
            'expired_at' => null,
        ]);

        (new AutopilotApprovalExpiryJob)->handle();

        $approval->refresh();
        $run->refresh();

        $this->assertSame('expired', (string) $approval->status);
        $this->assertNotNull($approval->expired_at);
        $this->assertSame('failed', (string) $run->status);
        $this->assertNotNull($run->completed_at);
    }
}
