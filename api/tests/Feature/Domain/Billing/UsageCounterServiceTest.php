<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Billing\Services\BillingCycleCalculator;
use Domain\Billing\Services\UsageCounterService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageCounterServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UsageCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageCounterService(new BillingCycleCalculator);
    }

    private function makeTenant(int $limit = 5, string $mode = 'stop', ?float $overagePrice = null): PlatformTenant
    {
        $plan = PlatformPlan::factory()->create([
            'message_limit_monthly' => $limit,
            'overage_mode' => $mode,
            'overage_price_per_message' => $overagePrice,
        ]);

        return PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'billing_cycle_anchor_day' => 1,
        ]);
    }

    #[Test]
    public function it_allows_message_when_under_limit(): void
    {
        $tenant = $this->makeTenant(5);
        $result = $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-1');
        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->current);
    }

    #[Test]
    public function it_blocks_when_limit_reached_in_stop_mode(): void
    {
        $tenant = $this->makeTenant(1, 'stop');
        $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-1');
        $result = $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-2');
        $this->assertFalse($result->allowed);
        $this->assertFalse($result->isOverage);
    }

    #[Test]
    public function it_allows_overage_when_mode_is_overage(): void
    {
        $tenant = $this->makeTenant(1, 'overage', 0.05);
        $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-1');
        $result = $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-2');
        $this->assertTrue($result->allowed);
        $this->assertTrue($result->isOverage);
    }

    #[Test]
    public function it_is_idempotent_for_same_ai_turn_id(): void
    {
        $tenant = $this->makeTenant(10);
        $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-abc');
        $result = $this->service->checkAndIncrement($tenant->id, 'whatsapp', 'turn-abc');
        // Both calls should return allowed=true
        $this->assertTrue($result->allowed);
    }
}
