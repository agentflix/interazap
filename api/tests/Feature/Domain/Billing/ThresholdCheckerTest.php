<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\ThresholdChecker;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThresholdCheckerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ThresholdChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ThresholdChecker;
    }

    private function makeUsage(int $count): TenantMessageUsage
    {
        $tenant = PlatformTenant::factory()->create();

        return \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'cycle_start' => now()->startOfMonth()->toDateString(),
            'cycle_end' => now()->endOfMonth()->toDateString(),
            'message_count' => $count,
            'overage_count' => 0,
        ]);
    }

    #[Test]
    public function it_returns_empty_when_below_80(): void
    {
        $usage = $this->makeUsage(3); // 30%
        $this->assertSame([], $this->checker->check($usage, 10));
    }

    #[Test]
    public function it_fires_80_threshold(): void
    {
        $usage = $this->makeUsage(8); // 80%
        $thresholds = $this->checker->check($usage, 10);
        $this->assertContains(80, $thresholds);
        $this->assertNotNull($usage->fresh()->alert_80_sent_at);
    }

    #[Test]
    public function it_does_not_fire_80_twice(): void
    {
        $usage = $this->makeUsage(8);
        $this->checker->check($usage, 10);
        $usage->refresh();
        $thresholds = $this->checker->check($usage, 10);
        $this->assertNotContains(80, $thresholds);
    }

    #[Test]
    public function it_fires_100_when_at_limit(): void
    {
        $usage = $this->makeUsage(10);
        $usage->alert_80_sent_at = \Illuminate\Support\Facades\Date::now(); // já enviado
        $usage->save();
        $thresholds = $this->checker->check($usage, 10);
        $this->assertContains(100, $thresholds);
        $this->assertNotContains(80, $thresholds);
    }

    #[Test]
    public function it_returns_empty_for_zero_limit(): void
    {
        $usage = $this->makeUsage(0);
        $this->assertSame([], $this->checker->check($usage, 0));
    }
}
