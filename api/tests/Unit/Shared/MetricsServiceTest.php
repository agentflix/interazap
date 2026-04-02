<?php

declare(strict_types=1);

use Domain\Shared\Services\MetricsService;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

beforeEach(function (): void {
    $this->registry = new CollectorRegistry(new InMemory);
    $this->renderer = new RenderTextFormat;
});

afterEach(function (): void {
    Mockery::close();
});

function fakeMetricsService(CollectorRegistry $registry, RenderTextFormat $renderer): MetricsService
{
    $service = Mockery::mock(MetricsService::class, [$registry, $renderer])->makePartial();
    $service->shouldAllowMockingProtectedMethods();

    $service->shouldReceive('getQueueMetrics')->andReturn([
        'jobs_total' => 5,
        'jobs_pending' => 3,
        'jobs_failed' => 2,
    ]);

    $service->shouldReceive('getDatabaseMetrics')->andReturn([
        'connections' => 4,
    ]);

    $service->shouldReceive('getRedisMetrics')->andReturn([
        'connected' => 1,
        'memory_used' => 2048,
    ]);

    $service->shouldReceive('getBusinessMetrics')->andReturn([
        'tickets' => [
            'open' => ['status' => 'open', 'count' => 10],
        ],
        'messages' => [
            'inbound' => ['direction' => 'inbound', 'count' => 25],
        ],
        'negotiations_value' => 1000.50,
        'negotiations_count' => 7,
    ]);

    return $service;
}

describe('MetricsService', function (): void {
    describe('collect()', function (): void {
        it('renders prometheus formatted metrics from snapshot data', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $output = $service->collect();

            expect($output)
                ->toBeString()
                ->toContain('# HELP')
                ->toContain('# TYPE')
                ->toContain('app_info')
                ->toContain('queue_jobs_total')
                ->toContain('database_connections_active')
                ->toContain('redis_memory_used_bytes')
                ->toContain('chat_tickets_total{status="open"} 10');
        });
    });

    describe('incrementCounter()', function (): void {
        it('stores counter samples inside the registry', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->incrementCounter('custom_counter');

            $output = $service->collect();

            expect($output)
                ->toContain('# TYPE custom_counter counter')
                ->toContain('custom_counter 1');
        });
    });

    describe('setGauge()', function (): void {
        it('sets gauge values with labels', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->setGauge('custom_gauge', 42.5, ['type' => 'tests']);

            $output = $service->collect();

            expect($output)
                ->toContain('# TYPE custom_gauge gauge')
                ->toContain('custom_gauge{type="tests"} 42.5');
        });
    });

    describe('observeHistogram()', function (): void {
        it('records histogram buckets for http durations', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->observeHistogram('http_request_duration_seconds', 0.2, [
                'method' => 'GET',
                'path' => '/',
                'status' => '200',
            ]);

            $output = $service->collect();

            expect($output)
                ->toContain('http_request_duration_seconds_bucket{method="GET",path="/",status="200",le="0.25"} 1')
                ->toContain('http_request_duration_seconds_count{method="GET",path="/",status="200"} 1');
        });
    });

    // Autopilot metrics tests (Phase 4)
    describe('recordAutopilotWebhookDuration()', function (): void {
        it('records webhook processing duration', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->recordAutopilotWebhookDuration(0.5, [
                'tenant_id' => 'tenant-123',
                'status' => 'success',
            ]);

            $output = $service->collect();

            expect($output)
                ->toContain('autopilot_webhook_duration_seconds')
                ->toContain('tenant_id="tenant-123"')
                ->toContain('status="success"');
        });
    });

    describe('recordAutopilotGuardrailBlock()', function (): void {
        it('increments guardrail block counter', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->recordAutopilotGuardrailBlock([
                'tenant_id' => 'tenant-123',
                'category' => 'prompt_injection',
            ]);

            $output = $service->collect();

            expect($output)
                ->toContain('autopilot_guardrail_blocks_total')
                ->toContain('tenant_id="tenant-123"')
                ->toContain('category="prompt_injection"');
        });
    });

    describe('setAutopilotBudgetUsageRatio()', function (): void {
        it('sets budget usage ratio gauge', function (): void {
            $service = fakeMetricsService($this->registry, $this->renderer);

            $service->setAutopilotBudgetUsageRatio(0.75, [
                'tenant_id' => 'tenant-123',
                'period' => 'monthly',
            ]);

            $output = $service->collect();

            expect($output)
                ->toContain('autopilot_budget_usage_ratio')
                ->toContain('tenant_id="tenant-123"')
                ->toContain('period="monthly"')
                ->toContain('0.75');
        });
    });
});
