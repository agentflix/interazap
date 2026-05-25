<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeAdmin(): AuthUser
    {
        $admin = AuthUser::factory()->create();
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $admin->assignRole(AuthRole::INQUILINO_ID);

        return $admin->refresh();
    }

    public function test_admin_can_create_plan(): void
    {
        Http::fake(['*' => Http::response(['id' => 'prod_123'], 200)]);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $payload = [
            'name' => 'Starter',
            'slug' => 'starter',
            'limit_users' => 3,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
            'ai_enabled' => false,
            'chat_channels_limit' => 1,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 10,
            'price_monthly' => 99.0,
            'message_limit_monthly' => 1000,
            'overage_mode' => 'stop',
        ];

        $this->postJson('/api/platform/plans', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'starter');
    }

    public function test_non_admin_cannot_create_plan(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $payload = [
            'name' => 'Starter',
            'slug' => 'starter',
            'limit_users' => 3,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
            'ai_enabled' => false,
            'chat_channels_limit' => 1,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 10,
            'price_monthly' => 99.0,
        ];

        $this->postJson('/api/platform/plans', $payload)
            ->assertStatus(403);
    }

    public function test_duplicate_slug_returns_422(): void
    {
        Http::fake(['*' => Http::response(['id' => 'prod_123'], 200)]);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        PlatformPlan::factory()->create(['slug' => 'starter']);

        $payload = [
            'name' => 'Starter',
            'slug' => 'starter',
            'limit_users' => 3,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
            'ai_enabled' => false,
            'chat_channels_limit' => 1,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 10,
            'price_monthly' => 99.0,
        ];

        $this->postJson('/api/platform/plans', $payload)
            ->assertStatus(422);
    }

    public function test_toggle_plan_status(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create(['is_active' => true]);

        $this->patchJson("/api/platform/plans/{$plan->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_list_plans_with_search(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        PlatformPlan::factory()->create(['name' => 'Starter']);
        PlatformPlan::factory()->create(['name' => 'Pro']);

        $response = $this->getJson('/api/platform/plans?search=Starter');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_validate_slug(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        PlatformPlan::factory()->create(['slug' => 'starter']);

        $this->getJson('/api/platform/plans/validate-slug?slug=starter')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/platform/plans/validate-slug?slug=pro')
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_admin_can_view_plan(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create();

        $this->getJson("/api/platform/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'storage_mode' => 'LIMITED',
            'negotiations_mode' => 'LIMITED',
            'price_monthly' => 10.0,
        ]);

        $payload = [
            'name' => 'Starter Updated',
            'slug' => 'starter',
            'limit_users' => 5,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 2048,
            'ai_enabled' => false,
            'chat_channels_limit' => 1,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 10,
            'price_monthly' => 10.0,
            'message_limit_monthly' => 2000,
            'overage_mode' => 'stop',
        ];

        $this->putJson("/api/platform/plans/{$plan->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Starter Updated');
    }

    public function test_admin_can_delete_plan(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create();

        $this->deleteJson("/api/platform/plans/{$plan->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('platform_plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_delete_plan_with_active_invoices_returns_422(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $this->deleteJson("/api/platform/plans/{$plan->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível remover um plano com faturas ativas.');
    }
}
