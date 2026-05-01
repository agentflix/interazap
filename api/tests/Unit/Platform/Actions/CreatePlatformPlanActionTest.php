<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Actions;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Actions\CreatePlatformPlanAction;
use Domain\Platform\DTOs\PlatformPlanDTO;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreatePlatformPlanActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_plan_with_generated_slug(): void
    {
        $dto = new PlatformPlanDTO(
            name: 'Enterprise Pro',
            slug: null,
            limitUsers: 100,
            storageMode: PlatformStorageMode::UNLIMITED,
            storageLimitBytes: null,
            aiEnabled: true,
            chatChannelsLimit: 10,
            negotiationsMode: PlatformNegotiationsMode::UNLIMITED,
            negotiationsLimit: null,
            priceMonthly: 299.99,
        );

        $gatewayService = Mockery::mock(BillingGatewayService::class);
        $gatewayService->shouldReceive('createProduct')->once()->andReturn('prod_123');

        $action = new CreatePlatformPlanAction($gatewayService);
        $plan = $action->execute($dto);

        $this->assertSame('enterprise-pro', $plan->slug);
        $this->assertSame('Enterprise Pro', $plan->name);
        $this->assertSame('prod_123', $plan->asaas_product_id);
        $this->assertTrue($plan->ai_enabled);
    }

    public function test_creates_plan_with_explicit_slug(): void
    {
        $dto = new PlatformPlanDTO(
            name: 'My Plan',
            slug: 'custom-slug',
            limitUsers: 5,
            storageMode: PlatformStorageMode::LIMITED,
            storageLimitBytes: 1024 * 1024 * 1024,
            aiEnabled: false,
            chatChannelsLimit: 1,
            negotiationsMode: PlatformNegotiationsMode::LIMITED,
            negotiationsLimit: 50,
            priceMonthly: 49.99,
        );

        $gatewayService = Mockery::mock(BillingGatewayService::class);
        $gatewayService->shouldReceive('createProduct')->once()->andReturn(null);

        $action = new CreatePlatformPlanAction($gatewayService);
        $plan = $action->execute($dto);

        $this->assertSame('custom-slug', $plan->slug);
        $this->assertNull($plan->asaas_product_id);
    }

    public function test_creates_plan_with_all_fields(): void
    {
        $dto = new PlatformPlanDTO(
            name: 'Complete Plan',
            slug: 'complete',
            limitUsers: 25,
            storageMode: PlatformStorageMode::LIMITED,
            storageLimitBytes: 5 * 1024 * 1024 * 1024,
            aiEnabled: true,
            chatChannelsLimit: 5,
            negotiationsMode: PlatformNegotiationsMode::LIMITED,
            negotiationsLimit: 100,
            priceMonthly: 149.99,
            isActive: true,
        );

        $gatewayService = Mockery::mock(BillingGatewayService::class);
        $gatewayService->shouldReceive('createProduct')->once()->andReturn('prod_456');

        $action = new CreatePlatformPlanAction($gatewayService);
        $plan = $action->execute($dto);

        $this->assertSame('Complete Plan', $plan->name);
        $this->assertSame('complete', $plan->slug);
        $this->assertSame(25, $plan->limit_users);
        $this->assertSame(PlatformStorageMode::LIMITED, $plan->storage_mode);
        $this->assertSame(5 * 1024 * 1024 * 1024, $plan->storage_limit_bytes);
        $this->assertTrue($plan->ai_enabled);
        $this->assertSame(5, $plan->chat_channels_limit);
        $this->assertSame(PlatformNegotiationsMode::LIMITED, $plan->negotiations_mode);
        $this->assertSame(100, $plan->negotiations_limit);
        $this->assertTrue($plan->is_active);
    }

    public function test_creates_plan_without_asaas_when_service_returns_null(): void
    {
        $dto = new PlatformPlanDTO(
            name: 'Basic',
            slug: 'basic',
            limitUsers: 3,
            storageMode: PlatformStorageMode::LIMITED,
            storageLimitBytes: 1024 * 1024 * 512,
            aiEnabled: false,
            chatChannelsLimit: 1,
            negotiationsMode: PlatformNegotiationsMode::LIMITED,
            negotiationsLimit: 10,
            priceMonthly: 29.99,
        );

        $gatewayService = Mockery::mock(BillingGatewayService::class);
        $gatewayService->shouldReceive('createProduct')->once()->andReturn(null);

        $action = new CreatePlatformPlanAction($gatewayService);
        $plan = $action->execute($dto);

        $this->assertNull($plan->asaas_product_id);
        $this->assertDatabaseHas('platform_plans', [
            'id' => $plan->id,
            'name' => 'Basic',
        ]);
    }
}
