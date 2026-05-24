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
            'message_limit_monthly' => 800,
            'overage_mode' => 'stop',
            'overage_price_per_message' => null,
            'chat_channels_limit' => 2,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 5,
            'price_monthly' => 9.9,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($plan->id);
        $this->assertSame(10, $plan->limit_users);
        $this->assertTrue($plan->ai_enabled);
        $this->assertSame(800, $plan->message_limit_monthly);
        $this->assertSame('stop', $plan->overage_mode->value);
        $this->assertNull($plan->overage_price_per_message);
        $this->assertSame('9.90', $plan->price_monthly);
    }
}
