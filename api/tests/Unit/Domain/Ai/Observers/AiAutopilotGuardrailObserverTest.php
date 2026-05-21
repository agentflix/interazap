<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Observers;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Domain\Ai\Observers\AiAutopilotGuardrailObserver;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AiAutopilotGuardrailObserverTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_saved_invalidates_tenant_guardrails_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $guardrail = AiAutopilotGuardrail::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $cacheKey = "autopilot:guardrails:tenant:{$tenant->id}";
        Cache::put($cacheKey, ['cached' => true], 300);
        $this->assertTrue(Cache::has($cacheKey));

        (new AiAutopilotGuardrailObserver)->saved($guardrail);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_deleted_invalidates_tenant_guardrails_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $guardrail = AiAutopilotGuardrail::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $cacheKey = "autopilot:guardrails:tenant:{$tenant->id}";
        Cache::put($cacheKey, ['cached' => true], 300);
        $this->assertTrue(Cache::has($cacheKey));

        (new AiAutopilotGuardrailObserver)->deleted($guardrail);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
