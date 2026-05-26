<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingChangePlanTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_plan_change_requires_current_password(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $targetPlan = PlatformPlan::factory()->create([
            'slug' => 'target-'.Str::lower(Str::random(6)),
            'price_monthly' => 300,
        ]);

        Sanctum::actingAs($admin, abilities: ['*']);

        $this->postJson('/api/billing/plan-change', [
            'plan_id' => $targetPlan->id,
        ])->assertStatus(422);

        $tenant->refresh();
        $this->assertNotSame($targetPlan->id, $tenant->plan_id);
    }

    public function test_upgrade_updates_tenant_plan_id(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $currentPlan = PlatformPlan::factory()->create([
            'slug' => 'starter-'.Str::lower(Str::random(6)),
            'price_monthly' => 99,
            'limit_users' => 10,
        ]);

        $targetPlan = PlatformPlan::factory()->create([
            'slug' => 'pro-'.Str::lower(Str::random(6)),
            'price_monthly' => 199,
            'limit_users' => 25,
        ]);

        $tenant->plan_id = $currentPlan->id;
        $tenant->save();

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->postJson('/api/billing/plan-change', [
            'plan_id' => $targetPlan->id,
            'current_password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.new_plan.id', $targetPlan->id);

        $tenant->refresh();
        $this->assertSame($targetPlan->id, $tenant->plan_id);
    }

    public function test_upgrade_reuses_existing_month_invoice_without_duplicate_key(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $currentPlan = PlatformPlan::factory()->create([
            'slug' => 'starter-'.Str::lower(Str::random(6)),
            'price_monthly' => 499,
            'limit_users' => 10,
        ]);

        $targetPlan = PlatformPlan::factory()->create([
            'slug' => 'enterprise-'.Str::lower(Str::random(6)),
            'price_monthly' => 999,
            'limit_users' => 100,
        ]);

        $tenant->plan_id = $currentPlan->id;
        $tenant->save();

        $existingInvoice = BillingInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $currentPlan->id,
            'reference_month' => now()->format('Y-m'),
            'amount' => 499,
            'status' => BillingInvoiceStatus::PENDING,
            'due_date' => now()->toDateString(),
            'metadata' => ['type' => 'monthly_subscription'],
        ]);

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->postJson('/api/billing/plan-change', [
            'plan_id' => $targetPlan->id,
            'current_password' => 'password',
        ]);

        $response->assertOk();

        $this->assertSame(
            1,
            BillingInvoice::query()->where('tenant_id', $tenant->id)->count()
        );

        $updatedInvoice = BillingInvoice::query()->findOrFail($existingInvoice->id);
        $this->assertSame($targetPlan->id, $updatedInvoice->plan_id);
        $this->assertGreaterThan(499.0, (float) $updatedInvoice->amount);
    }

    public function test_execute_bypasses_password_when_dto_flag_true(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $currentPlan = PlatformPlan::factory()->create([
            'slug' => 'starter-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'price_monthly' => 99,
            'limit_users' => 10,
        ]);

        $targetPlan = PlatformPlan::factory()->create([
            'slug' => 'pro-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'price_monthly' => 199,
            'limit_users' => 25,
        ]);

        $tenant->plan_id = $currentPlan->id;
        $tenant->save();

        $action = app(\Domain\Billing\Actions\BillingChangePlanAction::class);
        $dto = new \Domain\Billing\DTOs\BillingChangePlanDTO(
            planId: $targetPlan->id,
            currentPassword: null,
            bypassPassword: true
        );

        $result = $action->execute($tenant->id, $admin->id, $dto);

        $this->assertSame($targetPlan->id, $result['new_plan']['id']);
    }

    private function createTenantAdmin(): array
    {
        $tenant = PlatformTenant::factory()->create();

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $tenant->primary_email,
        ]);

        \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);

        foreach (['billing.view', 'billing.plan.manage'] as $permission) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $admin->assignRole(AuthRole::INQUILINO_ID);
        $admin->givePermissionTo(['billing.view', 'billing.plan.manage']);

        return [$tenant->refresh(), $admin->refresh()];
    }
}
