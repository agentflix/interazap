<?php

declare(strict_types=1);

use Domain\Platform\Services\QueueHealthService;

describe('QueueHealthController', function (): void {
    describe('GET /api/health/queues', function (): void {
        it('returns queue health status structure', function (): void {
            $response = $this->getJson('/api/health/queues');

            // May return 200 (healthy) or 503 (unhealthy) depending on worker state
            $response->assertStatus($response->json('healthy') ? 200 : 503)
                ->assertJsonStructure([
                    'healthy',
                    'issues',
                    'queues' => [
                        '*' => ['name', 'size', 'delayed'],
                    ],
                    'workers',
                    'stuck_jobs',
                    'thresholds' => ['max_queue_size', 'max_stuck_jobs'],
                    'checked_at',
                ]);
        });

        it('returns 503 when unhealthy', function (): void {
            $this->mock(QueueHealthService::class)
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

            $response = $this->getJson('/api/health/queues');

            $response->assertStatus(503)
                ->assertJson(['healthy' => false]);
        });

        it('returns 200 when healthy', function (): void {
            $this->mock(QueueHealthService::class)
                ->shouldReceive('getHealthStatus')
                ->andReturn([
                    'healthy' => true,
                    'issues' => [],
                    'queues' => [['name' => 'default', 'size' => 5, 'delayed' => 0]],
                    'workers' => 4,
                    'stuck_jobs' => 0,
                    'thresholds' => ['max_queue_size' => 1000, 'max_stuck_jobs' => 10],
                    'checked_at' => now()->toIso8601String(),
                ]);

            $response = $this->getJson('/api/health/queues');

            $response->assertStatus(200)
                ->assertJson(['healthy' => true]);
        });

        it('is rate limited', function (): void {
            // Make many requests to trigger rate limit
            for ($i = 0; $i < 65; $i++) {
                $response = $this->getJson('/api/health/queues');

                // First 60 should succeed (observability throttle)
                if ($i < 60) {
                    $response->assertStatus(200);
                }
            }

            // After limit, should be rate limited
            $response = $this->getJson('/api/health/queues');
            $response->assertStatus(429);
        })->skip('Rate limit testing requires specific setup');
    });

    describe('GET /api/health/queues/config', function (): void {
        it('returns queue configuration', function (): void {
            $response = $this->getJson('/api/health/queues/config');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'queues' => [
                        'critical' => ['timeout', 'tries', 'backoff'],
                        'high' => ['timeout', 'tries', 'backoff'],
                        'default' => ['timeout', 'tries', 'backoff'],
                        'low' => ['timeout', 'tries', 'backoff'],
                        'ai' => ['timeout', 'tries', 'backoff'],
                    ],
                ]);
        });

        it('returns correct timeout values', function (): void {
            $response = $this->getJson('/api/health/queues/config');

            $data = $response->json('queues');

            expect($data['critical']['timeout'])->toBe(30);
            expect($data['high']['timeout'])->toBe(60);
            expect($data['default']['timeout'])->toBe(120);
            expect($data['low']['timeout'])->toBe(300);
            expect($data['ai']['timeout'])->toBe(180);
        });
    });

    describe('GET /api/health/queues/{queue}', function (): void {
        it('returns stats for a specific queue', function (): void {
            $response = $this->getJson('/api/health/queues/default');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'name',
                    'size',
                    'delayed',
                ]);
        });

        it('returns the correct queue name', function (): void {
            $response = $this->getJson('/api/health/queues/high');

            $response->assertStatus(200)
                ->assertJson(['name' => 'high']);
        });
    });
});
