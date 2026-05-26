<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Actions\BillingChangePlanAction;
use Domain\Billing\DTOs\BillingChangePlanDTO;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingChangePlanActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_execute_bypasses_password_when_dto_flag_true(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'password' => Hash::make('any-password'),
            'tenant_id' => $tenant->id,
        ]);
        $newPlan = PlatformPlan::factory()->create([
            'is_active' => true,
            'price_monthly' => 199,
        ]);

        $action = app(BillingChangePlanAction::class);

        $dto = new BillingChangePlanDTO(
            planId: $newPlan->id,
            currentPassword: null,
            bypassPassword: true,
        );

        $result = $action->execute($tenant->id, $user->id, $dto);

        $this->assertSame('upgrade', $result['type']);
        $this->assertSame($newPlan->id, $result['new_plan']['id']);
    }
}
