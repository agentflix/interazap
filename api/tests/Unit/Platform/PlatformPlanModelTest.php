<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformPlanModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_plan_casts_and_booted(): void
    {
        $plan = PlatformPlan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'limit_users' => 10,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
            'ai_enabled' => true,
            'token_limit_monthly' => 100000,
            'allow_overage' => true,
            'overage_price_per_1k' => 2.0,
            'chat_channels_limit' => 2,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 5,
            'price_monthly' => 9.9,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($plan->id);
        $this->assertSame(10, $plan->limit_users);
        $this->assertTrue($plan->ai_enabled);
        $this->assertSame(100000, $plan->token_limit_monthly);
        $this->assertTrue($plan->allow_overage);
        $this->assertSame('2.00', $plan->overage_price_per_1k);
        $this->assertSame('9.90', $plan->price_monthly);
    }
}
