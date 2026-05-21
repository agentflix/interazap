<?php

declare(strict_types=1);

use Domain\Shared\Services\MetricsService;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

describe('MetricsService Autopilot extensions', function (): void {
    it('records run duration, tool iterations, approval wait, and lock contention metrics', function (): void {
        $service = new MetricsService(
            new CollectorRegistry(new InMemory),
            new RenderTextFormat,
        );

        $labels = ['tenant_id' => 'tenant-1', 'status' => 'ok'];

        $service->recordAutopilotRunDuration(1.5, $labels);
        $service->recordAutopilotToolIterations(3, $labels);
        $service->recordAutopilotApprovalWaitTime(120.0, $labels);
        $service->recordAutopilotLockContention(1, $labels);

        $output = $service->collect();

        expect($output)
            ->toContain('autopilot_run_duration_seconds')
            ->toContain('autopilot_tool_iterations')
            ->toContain('autopilot_approval_wait_time_seconds')
            ->toContain('autopilot_lock_contention_total')
            ->toContain('tenant_id="tenant-1"');
    });
});
