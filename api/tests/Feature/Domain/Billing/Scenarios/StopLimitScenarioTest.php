<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Scenarios;

use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\BillingCycleCalculator;
use Domain\Billing\Services\UsageCounterService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cenário 1: Limite stop — plano com message_limit_monthly=5 e overage_mode=stop.
 * A 6ª mensagem deve ser bloqueada (allowed=false).
 */
final class StopLimitScenarioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UsageCounterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageCounterService(new BillingCycleCalculator);
    }

    #[Test]
    public function sixth_message_is_blocked_when_limit_is_five_in_stop_mode(): void
    {
        $plan = PlatformPlan::factory()->create([
            'message_limit_monthly' => 5,
            'overage_mode' => 'stop',
        ]);

        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'billing_cycle_anchor_day' => 1,
        ]);

        // Act: enviar 6 mensagens com IDs diferentes
        $results = [];
        for ($i = 1; $i <= 6; $i++) {
            $results[] = $this->service->checkAndIncrement(
                $tenant->id,
                'whatsapp',
                "turn-{$i}"
            );
        }

        // Assert: primeiras 5 permitidas
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(
                $results[$i]->allowed,
                "Mensagem {$i} deveria estar allowed=true"
            );
            $this->assertSame($i + 1, $results[$i]->current);
        }

        // Assert: 6ª bloqueada
        $this->assertFalse(
            $results[5]->allowed,
            '6ª mensagem deveria estar bloqueada (allowed=false)'
        );
        $this->assertSame(5, $results[5]->current);
        $this->assertSame(5, $results[5]->limit);

        // Assert: contador no banco = 5
        $usage = TenantMessageUsage::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame(5, $usage->message_count);
    }
}
