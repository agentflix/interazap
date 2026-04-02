<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Policies\ChatInstancePolicy;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatInstancePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_denies_when_tenant_missing(): void
    {
        $user = AuthUser::factory()->make(['tenant_id' => null]);

        $enforcement = new PlatformPlanEnforcementService;

        $policy = new ChatInstancePolicy($enforcement);

        $this->assertFalse($policy->create($user));
    }

    public function test_create_throws_when_plan_limit_exceeded(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $user = AuthUser::factory()->make(['tenant_id' => $tenantId]);

        $plan = PlatformPlan::factory()->create([
            'chat_channels_limit' => 1,
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
        ]);

        $enforcement = new PlatformPlanEnforcementService;

        $policy = new ChatInstancePolicy($enforcement);

        $this->expectException(PlanLimitExceededException::class);
        $policy->create($user);
    }

    public function test_create_allows_when_plan_has_capacity(): void
    {
        $plan = PlatformPlan::factory()->create([
            'chat_channels_limit' => 2,
        ]);

        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $tenantId = (string) $tenant->id;
        $user = AuthUser::factory()->make(['tenant_id' => $tenantId]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
        ]);

        $enforcement = new PlatformPlanEnforcementService;

        $policy = new ChatInstancePolicy($enforcement);

        $this->assertTrue($policy->create($user));
    }
}
