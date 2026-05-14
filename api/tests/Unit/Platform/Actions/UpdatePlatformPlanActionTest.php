<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Actions;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Actions\UpdatePlatformPlanAction;
use Domain\Platform\DTOs\PlatformPlanDTO;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class UpdatePlatformPlanActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_updates_plan_and_triggers_asaas_update_on_price_change(): void
    {
        $plan = PlatformPlan::factory()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 9.9,
            'storage_mode' => PlatformStorageMode::LIMITED,
            'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
        ]);

        $dto = new PlatformPlanDTO(
            name: 'Starter',
            slug: null,
            limitUsers: 10,
            storageMode: PlatformStorageMode::LIMITED,
            storageLimitBytes: 1024,
            aiEnabled: true,
            tokenLimitMonthly: null,
            allowOverage: false,
            overagePricePer1k: null,
            chatChannelsLimit: 2,
            negotiationsMode: PlatformNegotiationsMode::LIMITED,
            negotiationsLimit: 5,
            priceMonthly: 19.9,
        );

        $gatewayService = Mockery::mock(BillingGatewayService::class);
        $gatewayService->shouldReceive('updateProduct')->once()->with(Mockery::type(PlatformPlan::class));

        $action = new UpdatePlatformPlanAction($gatewayService);
        $updated = $action->execute($plan, $dto);

        $this->assertSame('starter', $updated->slug);
        $this->assertSame('19.90', $updated->price_monthly);
    }
}
