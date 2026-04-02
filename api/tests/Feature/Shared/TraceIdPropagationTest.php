<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

describe('TraceId Propagation', function (): void {
    it('generates trace id when not provided', function (): void {
        $response = getJson('/api/health');

        // Response can be 200 or 503 depending on service availability
        // but trace ID should always be present
        $traceId = $response->headers->get('X-Trace-ID');

        expect($traceId)->not->toBeNull();
        expect($traceId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    });

    it('uses existing trace id from request', function (): void {
        $existingTraceId = 'test-trace-id-12345';

        $response = getJson('/api/health', [
            'X-Trace-ID' => $existingTraceId,
        ]);

        $responseTraceId = $response->headers->get('X-Trace-ID');

        expect($responseTraceId)->toBe($existingTraceId);
    });

    it('propagates trace id in all responses', function (): void {
        // Test multiple endpoints
        $endpoints = [
            '/api/health',
            '/api/metrics',
        ];

        foreach ($endpoints as $endpoint) {
            $response = getJson($endpoint);

            expect($response->headers->has('X-Trace-ID'))->toBeTrue();
        }
    });
});
