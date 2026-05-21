<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Events\AiRunFailed;
use Domain\Ai\Jobs\AutopilotZombieRunCleanupJob;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class AutopilotZombieRunCleanupJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_marks_only_stale_running_runs_as_failed(): void
    {
        config(['ai.stale_run_threshold_minutes' => 6]);
        Event::fake([AiRunFailed::class]);

        $staleRun = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'started_at' => now()->subMinutes(12),
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-zombie-1',
                'correlation_id' => 'corr-zombie-1',
            ],
        ]);

        $freshRun = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'started_at' => now()->subMinutes(2),
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-zombie-2',
                'correlation_id' => 'corr-zombie-2',
            ],
        ]);

        (new AutopilotZombieRunCleanupJob)->handle();

        $staleRun->refresh();
        $freshRun->refresh();

        $this->assertSame('failed', $staleRun->status);
        $this->assertSame('watchdog_zombie_cleanup', data_get($staleRun->output, 'error_code'));
        $this->assertNotNull($staleRun->completed_at);

        $this->assertSame('running', $freshRun->status);
        $this->assertNull($freshRun->completed_at);

        Event::assertDispatched(AiRunFailed::class, fn (AiRunFailed $event): bool => $event->runId === (string) $staleRun->id
            && $event->tenantId === (string) $staleRun->tenant_id
            && $event->ticketId === 'ticket-zombie-1'
            && $event->correlationId === 'corr-zombie-1'
            && $event->errorCode === 'watchdog_zombie_cleanup');
    }
}
