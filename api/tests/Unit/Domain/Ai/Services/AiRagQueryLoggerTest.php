<?php

declare(strict_types=1);

use Domain\Ai\Models\AiRagQueryLog;
use Domain\Ai\Services\AiRagQueryLogger;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('creates a log entry with hashed query', function (): void {
    $tenant = PlatformTenant::factory()->create();

    AiRagQueryLogger::log(
        query: '  Hello   World  ',
        tenantId: $tenant->id,
        mode: 'vector',
        resultsCount: 3,
        topScore: 0.95,
        avgScore: 0.80,
        latencyMs: 42,
    );

    $log = AiRagQueryLog::query()->first();

    expect($log)->not->toBeNull();
    expect($log->tenant_id)->toBe($tenant->id);
    expect($log->query_hash)->toBe(hash('sha256', 'hello world'));
    expect($log->query_length)->toBe(11);
    expect($log->mode)->toBe('vector');
    expect($log->results_count)->toBe(3);
    expect($log->top_score)->toEqual(0.95);
    expect($log->avg_score)->toEqual(0.80);
    expect($log->latency_ms)->toBe(42);
    expect($log->has_results)->toBeTrue();
});

it('stores has_results as false when count is zero', function (): void {
    $tenant = PlatformTenant::factory()->create();

    AiRagQueryLogger::log(
        query: 'test',
        tenantId: $tenant->id,
        mode: 'hybrid',
        resultsCount: 0,
        topScore: null,
        avgScore: null,
        latencyMs: 12,
    );

    $log = AiRagQueryLog::query()->first();

    expect($log->has_results)->toBeFalse();
    expect($log->top_score)->toBeNull();
    expect($log->avg_score)->toBeNull();
});

it('swallows exceptions without failing', function (): void {
    // Force an exception by passing an invalid tenant ID (FK violation)
    AiRagQueryLogger::log(
        query: 'test',
        tenantId: 'invalid-uuid',
        mode: 'vector',
        resultsCount: 0,
        topScore: null,
        avgScore: null,
        latencyMs: 10,
    );

    expect(true)->toBeTrue();
});
