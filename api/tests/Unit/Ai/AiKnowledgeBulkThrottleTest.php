<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AiKnowledgeBulkThrottleTest extends TestCase
{
    public function test_bulk_routes_use_ai_knowledge_bulk_throttle_middleware(): void
    {
        $routes = app('router')->getRoutes();

        $bulkDeleteRoute = $routes->getByName('ai.knowledge.bulk.delete');
        $this->assertNotNull($bulkDeleteRoute);
        $this->assertContains('throttle:ai-knowledge-bulk', $bulkDeleteRoute->gatherMiddleware());

        $bulkReindexRoute = $routes->getByName('ai.knowledge.bulk.reindex');
        $this->assertNotNull($bulkReindexRoute);
        $this->assertContains('throttle:ai-knowledge-bulk', $bulkReindexRoute->gatherMiddleware());
    }

    public function test_ai_knowledge_bulk_rate_limiter_is_30_requests_per_minute_by_ip(): void
    {
        $limiter = RateLimiter::limiter('ai-knowledge-bulk');

        $this->assertNotNull($limiter);

        $request = Request::create('/api/ai/knowledge/bulk', 'DELETE', server: [
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $limit = $limiter($request);

        if (is_array($limit)) {
            $limit = $limit[0];
        }

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(30, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
        $this->assertSame('203.0.113.10', $limit->key);
    }
}
