<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Actions;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Actions\DeletePlatformPlanAction;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeletePlatformPlanActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deletes_plan_when_no_active_invoices(): void
    {
        $plan = PlatformPlan::factory()->create();

        $action = new DeletePlatformPlanAction;
        $action->execute($plan);

        $this->assertSoftDeleted('platform_plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_throws_when_plan_has_active_invoices(): void
    {
        $plan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        $action = new DeletePlatformPlanAction;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível remover um plano com faturas ativas.');

        $action->execute($plan);
    }
}
