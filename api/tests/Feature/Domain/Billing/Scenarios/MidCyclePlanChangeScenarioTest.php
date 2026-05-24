<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Scenarios;

use Carbon\CarbonImmutable;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\BillingCycleCalculator;
use Domain\Billing\Services\UsageCounterService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cenário 3: Troca de plano no meio do ciclo (mid-cycle plan change).
 * Tenant com 600/800 msgs troca para plano 1500.
 * O contador 600 deve ser preservado e o limite deve passar a ser 1500.
 * O ciclo (cycle_start / cycle_end) deve ser mantido.
 */
final class MidCyclePlanChangeScenarioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UsageCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageCounterService(new BillingCycleCalculator);
    }

    #[Test]
    public function usage_is_preserved_and_limit_updated_on_mid_cycle_plan_change(): void
    {
        // Plano A: 800 mensagens
        $planA = PlatformPlan::factory()->create([
            'message_limit_monthly' => 800,
            'overage_mode' => 'stop',
        ]);

        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $planA->id,
            'billing_cycle_anchor_day' => 1,
        ]);

        $now = CarbonImmutable::now();
        $cycleStart = $now->startOfMonth()->toDateString();
        $cycleEnd = $now->endOfMonth()->toDateString();

        // Criar registro de uso com 600 mensagens no ciclo atual
        $usage = \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'tenant_id' => $tenant->id,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'message_count' => 600,
            'overage_count' => 0,
        ]);

        // Plano B: 1500 mensagens
        $planB = PlatformPlan::factory()->create([
            'message_limit_monthly' => 1500,
            'overage_mode' => 'stop',
        ]);

        // Trocar tenant para plano B (mid-cycle)
        $tenant->update(['plan_id' => $planB->id]);
        $tenant->refresh();

        // Act: disparar nova mensagem
        $result = $this->service->checkAndIncrement(
            $tenant->id,
            'whatsapp',
            'turn-mid-cycle-1'
        );

        // Assert: mensagem permitida (600 < 1500)
        $this->assertTrue($result->allowed);
        $this->assertSame(601, $result->current);
        $this->assertSame(1500, $result->limit);

        // Assert: contador preservado (600 + 1 = 601)
        $usageRefreshed = TenantMessageUsage::query()
            ->where('id', $usage->id)
            ->first();

        $this->assertNotNull($usageRefreshed);
        $this->assertSame(601, $usageRefreshed->message_count);

        // Assert: ciclo mantido (mesmo cycle_start / cycle_end)
        $this->assertSame($cycleStart, $usageRefreshed->cycle_start->toDateString());
        $this->assertSame($cycleEnd, $usageRefreshed->cycle_end->toDateString());
    }
}
