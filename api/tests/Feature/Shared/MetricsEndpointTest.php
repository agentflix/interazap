<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('Metrics Endpoint', function (): void {
    it('returns prometheus formatted metrics', function (): void {
        $response = get('/api/metrics');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $content = $response->getContent();

        // Check prometheus format
        expect($content)->toContain('# HELP');
        expect($content)->toContain('# TYPE');
    });

    it('includes app_info metric', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('app_info');
        expect($content)->toContain('version=');
        expect($content)->toContain('env=');
    });

    it('includes http_requests_total metric', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('http_requests_total');
        expect($content)->toContain('# TYPE http_requests_total counter');
    });

    it('includes queue metrics', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('queue_jobs_total');
        expect($content)->toContain('queue_jobs_pending');
        expect($content)->toContain('queue_jobs_failed_total');
    });

    it('includes php memory metrics', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('php_memory_usage_bytes');
        expect($content)->toContain('php_memory_peak_bytes');
    });

    it('includes redis metrics', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('redis_connected');
        expect($content)->toContain('redis_memory_used_bytes');
    });

    it('includes database metrics', function (): void {
        $response = get('/api/metrics');

        $content = $response->getContent();

        expect($content)->toContain('database_connections_active');
    });
});
