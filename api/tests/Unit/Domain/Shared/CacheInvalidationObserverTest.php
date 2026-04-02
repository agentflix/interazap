<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Domain\Chat\Models\ChatQuickAnswer;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Observers\CacheInvalidationObserver;
use Domain\Shared\Services\CriticalDataCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheInvalidationObserverTest extends TestCase
{
    private CriticalDataCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = app(CriticalDataCacheService::class);
        Cache::flush();
    }

    #[Test]
    public function it_invalidates_tenant_config_on_update(): void
    {
        $tenant = PlatformTenant::factory()->create(['name' => 'Old Name']);

        // Populate cache
        $this->cacheService->getTenantConfig($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:config"));

        // Update tenant - should invalidate cache
        $tenant->update(['name' => 'New Name']);

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:config"));
    }

    #[Test]
    public function it_invalidates_plan_quotas_on_update(): void
    {
        $plan = PlatformPlan::factory()->create();

        // Populate cache
        $this->cacheService->getPlanQuotas($plan->id);
        $this->assertTrue(Cache::has("plan:{$plan->id}:quotas"));

        // Update plan - should invalidate cache
        $plan->update(['limit_users' => 999]);

        $this->assertFalse(Cache::has("plan:{$plan->id}:quotas"));
    }

    #[Test]
    public function it_invalidates_funnel_cache_on_save(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Populate cache
        $this->cacheService->getFunnelWithSteps($tenant->id, $funnel->id);
        $this->cacheService->getTenantFunnels($tenant->id);

        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnels:all"));

        // Update funnel - should invalidate cache
        $funnel->update(['name' => 'Updated Pipeline']);

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:funnels:all"));
    }

    #[Test]
    public function it_invalidates_funnel_cache_on_delete(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Populate cache
        $this->cacheService->getFunnelWithSteps($tenant->id, $funnel->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));

        // Delete funnel - should invalidate cache
        $funnel->delete();

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));
    }

    #[Test]
    public function it_invalidates_quick_answers_on_save(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $quickAnswer = ChatQuickAnswer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Populate cache
        $this->cacheService->getQuickAnswers($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:quick_answers"));

        // Update quick answer - should invalidate cache
        $quickAnswer->update(['content' => 'Updated content']);

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:quick_answers"));
    }

    #[Test]
    public function it_invalidates_quick_answers_on_delete(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $quickAnswer = ChatQuickAnswer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Populate cache
        $this->cacheService->getQuickAnswers($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:quick_answers"));

        // Soft delete quick answer - should invalidate cache
        $quickAnswer->delete();

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:quick_answers"));
    }

    #[Test]
    public function it_deduplicates_funnel_invalidation_between_updated_and_saved_events(): void
    {
        $tenantId = Str::orderedUuid()->toString();
        $funnelId = Str::orderedUuid()->toString();

        $funnelKey = "tenant:{$tenantId}:funnel:{$funnelId}";
        $allFunnelsKey = "tenant:{$tenantId}:funnels:all";

        Cache::put($funnelKey, 'cached', 60);
        Cache::put($allFunnelsKey, 'cached', 60);

        $cacheService = new CriticalDataCacheService;
        $observer = new CacheInvalidationObserver($cacheService);
        $funnel = new CRMNegotiationFunnel;
        $funnel->id = $funnelId;
        $funnel->tenant_id = $tenantId;

        $observer->updated($funnel);
        $this->assertTrue(Cache::has($funnelKey));
        $this->assertTrue(Cache::has($allFunnelsKey));

        $observer->saved($funnel);

        $this->assertFalse(Cache::has($funnelKey));
        $this->assertFalse(Cache::has($allFunnelsKey));
    }

    #[Test]
    public function it_deduplicates_quick_answer_invalidation_between_updated_and_saved_events(): void
    {
        $tenantId = Str::orderedUuid()->toString();
        $quickAnswersKey = "tenant:{$tenantId}:quick_answers";

        Cache::put($quickAnswersKey, 'cached', 60);

        $cacheService = new CriticalDataCacheService;
        $observer = new CacheInvalidationObserver($cacheService);
        $quickAnswer = new ChatQuickAnswer;
        $quickAnswer->tenant_id = $tenantId;

        $observer->updated($quickAnswer);
        $this->assertTrue(Cache::has($quickAnswersKey));

        $observer->saved($quickAnswer);

        $this->assertFalse(Cache::has($quickAnswersKey));
    }
}
