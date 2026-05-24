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
 * Cenário 2: Reset de ciclo no dia do aniversário.
 * Quando cycle_end expirou, uma nova row deve ser criada com contador zerado.
 */
final class CycleResetScenarioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UsageCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageCounterService(new BillingCycleCalculator);
    }

    #[Test]
    public function new_cycle_row_is_created_when_previous_cycle_expired(): void
    {
        $plan = PlatformPlan::factory()->create([
            'message_limit_monthly' => 100,
            'overage_mode' => 'stop',
        ]);

        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'billing_cycle_anchor_day' => 1,
        ]);

        $yesterday = CarbonImmutable::now()->subDay()->toDateString();
        $twoDaysAgo = CarbonImmutable::now()->subDays(2)->toDateString();

        // Criar row "antiga" com cycle_end = ontem (ciclo expirado)
        $oldUsage = \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'tenant_id' => $tenant->id,
            'cycle_start' => $twoDaysAgo,
            'cycle_end' => $yesterday,
            'message_count' => 50,
            'overage_count' => 0,
        ]);

        // Act: disparar nova mensagem
        $result = $this->service->checkAndIncrement(
            $tenant->id,
            'whatsapp',
            'turn-cycle-reset-1'
        );

        // Assert: mensagem permitida (novo ciclo, contador zerado)
        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->current);

        // Assert: nova row criada com message_count=1
        $newUsage = TenantMessageUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('cycle_start', '!=', $oldUsage->cycle_start)
            ->first();

        $this->assertNotNull($newUsage, 'Nova row deveria ter sido criada para o ciclo atual');
        $this->assertSame(1, $newUsage->message_count);

        // Assert: row antiga preservada
        $oldUsageRefreshed = TenantMessageUsage::query()
            ->where('id', $oldUsage->id)
            ->first();

        $this->assertNotNull($oldUsageRefreshed);
        $this->assertSame(50, $oldUsageRefreshed->message_count);
        $this->assertSame($yesterday, $oldUsageRefreshed->cycle_end->toDateString());
    }
}
