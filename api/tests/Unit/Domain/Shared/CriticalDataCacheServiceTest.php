<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatQuickAnswer;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\CriticalDataCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CriticalDataCacheServiceTest extends TestCase
{
    private CriticalDataCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CriticalDataCacheService;
        Cache::flush();
    }

    #[Test]
    public function it_caches_tenant_subscription_data(): void
    {
        $uniqueCode = 'TEST'.Str::random(6);
        $tenant = PlatformTenant::factory()->create([
            'is_active' => true,
            'tenant_code' => $uniqueCode,
        ]);

        // First call - hits database
        $result = $this->service->getTenantSubscription($tenant->id);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_active']);
        $this->assertSame($uniqueCode, $result['tenant_code']);

        // Second call - should be cached
        $cachedResult = $this->service->getTenantSubscription($tenant->id);

        $this->assertEquals($result, $cachedResult);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_tenant(): void
    {
        $nonExistentUuid = (string) Str::orderedUuid();

        $result = $this->service->getTenantSubscription($nonExistentUuid);

        $this->assertNull($result);
    }

    #[Test]
    public function it_can_forget_tenant_subscription_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();

        // Populate cache
        $this->service->getTenantSubscription($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:subscription"));

        // Forget cache
        $this->service->forgetTenantSubscription($tenant->id);
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:subscription"));
    }

    #[Test]
    public function it_caches_chat_instance_by_token(): void
    {
        $uniqueToken = 'test-webhook-token-'.Str::random(10);
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'webhook_token' => $uniqueToken,
        ]);

        // First call - hits database
        $result = $this->service->getChatInstanceByToken($uniqueToken);

        $this->assertNotNull($result);
        $this->assertSame($instance->id, $result->id);

        // Second call - should use cached ID
        $cachedResult = $this->service->getChatInstanceByToken($uniqueToken);

        $this->assertNotNull($cachedResult);
        $this->assertSame($instance->id, $cachedResult->id);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_token(): void
    {
        $result = $this->service->getChatInstanceByToken('nonexistent-token-'.Str::random(10));

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_inactive_instance(): void
    {
        $uniqueToken = 'inactive-token-'.Str::random(10);
        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'webhook_token' => $uniqueToken,
        ]);

        $result = $this->service->getChatInstanceByToken($uniqueToken);

        $this->assertNull($result);
    }

    #[Test]
    public function it_can_forget_chat_instance_token_cache(): void
    {
        $uniqueToken = 'forget-test-token-'.Str::random(10);
        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'webhook_token' => $uniqueToken,
        ]);

        // Populate cache
        $this->service->getChatInstanceByToken($uniqueToken);
        $this->assertTrue(Cache::has("instance:token:{$uniqueToken}"));

        // Forget cache
        $this->service->forgetChatInstanceToken($uniqueToken);
        $this->assertFalse(Cache::has("instance:token:{$uniqueToken}"));
    }

    #[Test]
    public function it_caches_plan_quotas(): void
    {
        $plan = PlatformPlan::factory()->create([
            'limit_users' => 10,
            'storage_limit_bytes' => 1073741824, // 1GB
            'ai_enabled' => true,
            'chat_channels_limit' => 5,
            'negotiations_limit' => 100,
        ]);

        // First call - hits database
        $result = $this->service->getPlanQuotas($plan->id);

        $this->assertNotNull($result);
        $this->assertSame(10, $result['limit_users']);
        $this->assertSame(1073741824, $result['storage_limit_bytes']);
        $this->assertTrue($result['ai_enabled']);
        $this->assertSame(5, $result['chat_channels_limit']);
        $this->assertSame(100, $result['negotiations_limit']);

        // Second call - should be cached
        $cachedResult = $this->service->getPlanQuotas($plan->id);

        $this->assertEquals($result, $cachedResult);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_plan(): void
    {
        $nonExistentUuid = (string) Str::orderedUuid();

        $result = $this->service->getPlanQuotas($nonExistentUuid);

        $this->assertNull($result);
    }

    #[Test]
    public function it_can_forget_plan_quotas_cache(): void
    {
        $plan = PlatformPlan::factory()->create();

        // Populate cache
        $this->service->getPlanQuotas($plan->id);
        $this->assertTrue(Cache::has("plan:{$plan->id}:quotas"));

        // Forget cache
        $this->service->forgetPlanQuotas($plan->id);
        $this->assertFalse(Cache::has("plan:{$plan->id}:quotas"));
    }

    #[Test]
    public function it_caches_tenant_config(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'name' => 'Test Tenant',
            'is_active' => true,
        ]);

        // First call - hits database
        $result = $this->service->getTenantConfig($tenant->id);

        $this->assertNotNull($result);
        $this->assertSame('Test Tenant', $result['name']);
        $this->assertTrue($result['is_active']);

        // Second call - should be cached
        $cachedResult = $this->service->getTenantConfig($tenant->id);
        $this->assertEquals($result, $cachedResult);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:config"));
    }

    #[Test]
    public function it_can_forget_tenant_config_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $this->service->getTenantConfig($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:config"));

        $this->service->forgetTenantConfig($tenant->id);
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:config"));
    }

    #[Test]
    public function it_caches_funnel_with_steps(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Pipeline',
        ]);
        CRMNegotiationFunnelStep::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        // First call - hits database
        $result = $this->service->getFunnelWithSteps($tenant->id, $funnel->id);

        $this->assertNotNull($result);
        $this->assertSame('Sales Pipeline', $result['name']);
        $this->assertCount(3, $result['steps']);

        // Second call - should be cached
        $cachedResult = $this->service->getFunnelWithSteps($tenant->id, $funnel->id);
        $this->assertEquals($result, $cachedResult);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_funnel(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $nonExistentId = (string) Str::orderedUuid();

        $result = $this->service->getFunnelWithSteps($tenant->id, $nonExistentId);

        $this->assertNull($result);
    }

    #[Test]
    public function it_caches_tenant_funnels_list(): void
    {
        $tenant = PlatformTenant::factory()->create();
        CRMNegotiationFunnel::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);

        // First call - hits database
        $result = $this->service->getTenantFunnels($tenant->id);

        $this->assertCount(3, $result);

        // Second call - should be cached
        $cachedResult = $this->service->getTenantFunnels($tenant->id);
        $this->assertCount(3, $cachedResult);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnels:all"));
    }

    #[Test]
    public function it_can_forget_funnel_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->service->getFunnelWithSteps($tenant->id, $funnel->id);
        $this->service->getTenantFunnels($tenant->id);

        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:funnels:all"));

        $this->service->forgetFunnel($tenant->id, $funnel->id);

        $this->assertFalse(Cache::has("tenant:{$tenant->id}:funnel:{$funnel->id}"));
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:funnels:all"));
    }

    #[Test]
    public function it_caches_quick_answers(): void
    {
        $tenant = PlatformTenant::factory()->create();
        ChatQuickAnswer::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        // First call - hits database
        $result = $this->service->getQuickAnswers($tenant->id);

        $this->assertCount(5, $result); // Only active ones

        // Second call - should be cached
        $cachedResult = $this->service->getQuickAnswers($tenant->id);
        $this->assertCount(5, $cachedResult);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:quick_answers"));
    }

    #[Test]
    public function it_can_forget_quick_answers_cache(): void
    {
        $tenant = PlatformTenant::factory()->create();
        ChatQuickAnswer::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->service->getQuickAnswers($tenant->id);
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:quick_answers"));

        $this->service->forgetQuickAnswers($tenant->id);
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:quick_answers"));
    }
}
