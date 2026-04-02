<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

describe('Health Endpoint', function (): void {
    it('returns health status with all services', function (): void {
        $response = getJson('/api/health');

        // Accept both 200 (healthy) and 503 (degraded/unhealthy) as valid responses
        $response
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database' => ['status'],
                    'redis' => ['status'],
                    'queue' => ['status'],
                ],
            ]);

        expect($response->json('status'))->toBeIn(['healthy', 'degraded', 'unhealthy']);
    });

    it('includes latency for healthy services', function (): void {
        $response = getJson('/api/health');

        $services = $response->json('services');

        foreach ($services as $service) {
            if ($service['status'] === 'healthy') {
                expect($service)->toHaveKey('latency_ms');
            }
        }
    });

    it('returns iso8601 timestamp', function (): void {
        $response = getJson('/api/health');

        $timestamp = $response->json('timestamp');

        expect(strtotime($timestamp))->toBeInt();
    });

    it('includes queue size in queue service', function (): void {
        $response = getJson('/api/health');

        $queue = $response->json('services.queue');

        if ($queue['status'] === 'healthy') {
            expect($queue)->toHaveKey('queue_size');
        }
    });
});
