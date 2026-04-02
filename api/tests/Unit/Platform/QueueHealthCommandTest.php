<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

describe('QueueHealthCommand', function (): void {
    describe('basic execution', function (): void {
        it('runs without errors', function (): void {
            $exitCode = Artisan::call('queue:health');

            // May return 0 (healthy) or 1 (unhealthy) depending on worker state
            expect($exitCode)->toBeIn([0, 1]);
        });

        it('supports json output option', function (): void {
            Artisan::call('queue:health', ['--json' => true]);

            $output = Artisan::output();

            // Should be valid JSON
            $decoded = json_decode($output, true);
            expect($decoded)->toBeArray();
            expect($decoded)->toHaveKeys(['healthy', 'queues', 'workers']);
        });

        it('supports filtering by specific queue', function (): void {
            Artisan::call('queue:health', ['--queue' => 'default', '--json' => true]);

            $output = Artisan::output();
            $decoded = json_decode($output, true);

            expect($decoded['queues'])->toHaveCount(1);
            expect($decoded['queues'][0]['name'])->toBe('default');
        });
    });

    describe('output formatting', function (): void {
        it('displays queue statistics table in human readable format', function (): void {
            Artisan::call('queue:health');

            $output = Artisan::output();

            expect($output)
                ->toContain('Queue Statistics')
                ->toContain('Queue')
                ->toContain('Size')
                ->toContain('Delayed');
        });

        it('shows worker count in output', function (): void {
            Artisan::call('queue:health');

            $output = Artisan::output();

            expect($output)->toContain('Workers:');
        });

        it('shows stuck jobs count in output', function (): void {
            Artisan::call('queue:health');

            $output = Artisan::output();

            expect($output)->toContain('Stuck Jobs:');
        });

        it('shows checked_at timestamp in output', function (): void {
            Artisan::call('queue:health');

            $output = Artisan::output();

            expect($output)->toContain('Checked At:');
        });
    });

    describe('exit codes', function (): void {
        it('returns success when healthy', function (): void {
            // Mock a healthy state
            $this->mock(Domain\Platform\Services\QueueHealthService::class)
                ->shouldReceive('setQueues')
                ->andReturnSelf()
                ->shouldReceive('getHealthStatus')
                ->andReturn([
                    'healthy' => true,
                    'issues' => [],
                    'queues' => [['name' => 'default', 'size' => 0, 'delayed' => 0]],
                    'workers' => 2,
                    'stuck_jobs' => 0,
                    'thresholds' => ['max_queue_size' => 1000, 'max_stuck_jobs' => 10],
                    'checked_at' => now()->toIso8601String(),
                ]);

            $exitCode = Artisan::call('queue:health');

            expect($exitCode)->toBe(0);
        });

        it('returns failure when unhealthy', function (): void {
            $this->mock(Domain\Platform\Services\QueueHealthService::class)
                ->shouldReceive('setQueues')
                ->andReturnSelf()
                ->shouldReceive('getHealthStatus')
                ->andReturn([
                    'healthy' => false,
                    'issues' => ['No active workers detected'],
                    'queues' => [['name' => 'default', 'size' => 0, 'delayed' => 0]],
                    'workers' => 0,
                    'stuck_jobs' => 0,
                    'thresholds' => ['max_queue_size' => 1000, 'max_stuck_jobs' => 10],
                    'checked_at' => now()->toIso8601String(),
                ]);

            $exitCode = Artisan::call('queue:health');

            expect($exitCode)->toBe(1);
        });
    });
});
