<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Services\PrometheusRegistry;
use Prometheus\CollectorRegistry;
use Tests\TestCase;

class PrometheusRegistryTest extends TestCase
{
    public function test_resolves_collector_registry_as_container_singleton(): void
    {
        $first = app(CollectorRegistry::class);
        $second = app(CollectorRegistry::class);
        $service = app(PrometheusRegistry::class);

        $this->assertInstanceOf(CollectorRegistry::class, $first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $service->get());
    }
}
