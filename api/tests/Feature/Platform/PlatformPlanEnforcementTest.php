<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Database\Factories\CRMNegotiationFunnelFactory;
use Database\Factories\CRMNegotiationFunnelStepFactory;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanEnforcementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seedTenantPlan(PlatformTenant $tenant, PlatformPlan $plan): void
    {
        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID->value,
            'due_date' => now()->toDateString(),
        ]);
    }

    private function makeTenantAdmin(PlatformTenant $tenant): AuthUser
    {
        $admin = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $admin->assignRole($role);

        return $admin->refresh();
    }

    public function test_user_limit_blocks_creation(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create(['limit_users' => 1]);
        $this->seedTenantPlan($tenant, $plan);

        $admin = $this->makeTenantAdmin($tenant);
        Sanctum::actingAs($admin, abilities: ['*']);

        $payload = [
            'tenant_id' => $tenant->id,
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => 'password123',
        ];

        $this->postJson('/api/auth/users', $payload)
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
    }

    public function test_storage_limit_blocks_upload(): void
    {
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
        ]);
        $this->seedTenantPlan($tenant, $plan);

        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user, abilities: ['*']);

        $file = UploadedFile::fake()->create('test.png', 10);

        $this->postJson('/api/chat/media', ['file' => $file])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
    }

    public function test_whatsapp_instance_limit_blocks_creation(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create([
            'whatsapp_integrations_limit' => 1,
        ]);
        $this->seedTenantPlan($tenant, $plan);

        ChatInstance::factory()->create(['tenant_id' => $tenant->id]);

        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user, abilities: ['*']);

        $payload = [
            'name' => 'Instancia',
            'provider' => 'uazapi',
            'token' => 'tok-123',
        ];

        $this->postJson('/api/integrations', $payload)
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
    }

    public function test_negotiations_limit_blocks_creation(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create([
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 1,
        ]);
        $this->seedTenantPlan($tenant, $plan);

        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user, abilities: ['*']);

        $funnel = CRMNegotiationFunnelFactory::new()->create(['tenant_id' => $tenant->id]);
        $step = CRMNegotiationFunnelStepFactory::new()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'title' => 'Nova Negociação',
            'amount' => 1000,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
        ];

        $this->postJson('/api/crm/negotiations', $payload)
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLAN_LIMIT_EXCEEDED');
    }
}
