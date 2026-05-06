<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformBillingInvoiceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Cria um usuário admin autenticado para os testes.
     */
    private function makeAdmin(): AuthUser
    {
        $admin = AuthUser::factory()->create();
        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $admin->assignRole(AuthRole::INQUILINO_ID);

        return $admin->refresh();
    }

    /**
     * Cria um usuário comum (não-admin) autenticado.
     */
    private function makeRegularUser(): AuthUser
    {
        return AuthUser::factory()->create();
    }

    public function test_platform_admin_can_list_all_invoices(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantA->id,
            'reference_month' => '2026-01',
            'status' => 'pending',
            'due_date' => '2026-01-10',
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantB->id,
            'reference_month' => '2026-02',
            'status' => 'draft',
            'due_date' => '2026-02-10',
        ]);

        $response = $this->getJson('/api/platform/billing/invoices');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_platform_admin_can_filter_invoices_by_tenant(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantA->id,
            'reference_month' => '2026-01',
            'status' => 'pending',
            'due_date' => '2026-01-10',
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenantB->id,
            'reference_month' => '2026-02',
            'status' => 'draft',
            'due_date' => '2026-02-10',
        ]);

        $response = $this->getJson('/api/platform/billing/invoices?tenant_id='.$tenantA->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($tenantA->id, $data[0]['tenant_id']);
    }

    public function test_platform_invoice_list_includes_tenant_data(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create([
            'name' => 'Acme Corp',
            'tenant_code' => 'ACME',
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'reference_month' => '2026-03',
            'status' => 'pending',
            'due_date' => '2026-03-10',
        ]);

        $response = $this->getJson('/api/platform/billing/invoices?tenant_id='.$tenant->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('tenant', $first);
        $this->assertSame($tenant->id, $first['tenant']['id']);
        $this->assertSame('Acme Corp', $first['tenant']['name']);
        $this->assertSame('ACME', $first['tenant']['tenant_code']);
    }

    public function test_non_admin_cannot_list_platform_invoices(): void
    {
        $user = $this->makeRegularUser();
        Sanctum::actingAs($user, abilities: ['*']);

        $response = $this->getJson('/api/platform/billing/invoices');

        $response->assertForbidden();
    }

    public function test_platform_admin_can_create_invoice_for_any_tenant(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create();

        $payload = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'reference_month' => '2026-04',
            'amount' => 299.90,
            'due_date' => '2026-04-10',
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/platform/billing/invoices', $payload);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertSame($tenant->id, $data['tenant_id']);
        $this->assertSame('2026-04', $data['reference_month']);
        $this->assertSame(299.90, $data['amount']);
        $this->assertSame('draft', $data['status']);

        $this->assertDatabaseHas('billing_invoices', [
            'tenant_id' => $tenant->id,
            'reference_month' => '2026-04',
        ]);
    }

    public function test_platform_invoice_store_requires_tenant_id(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $payload = [
            'reference_month' => '2026-05',
            'amount' => 100.00,
            'due_date' => '2026-05-10',
        ];

        $response = $this->postJson('/api/platform/billing/invoices', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_duplicate_reference_month_returns_422(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'reference_month' => '2026-06',
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'reference_month' => '2026-06',
            'amount' => 299.90,
            'due_date' => '2026-06-10',
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/platform/billing/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors', 'message']);
        $this->assertStringContainsString('Já existe uma fatura', $response->json('message'));
    }

    public function test_platform_admin_can_cancel_pending_invoice(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'due_date' => '2026-01-10',
        ]);

        $response = $this->deleteJson('/api/platform/billing/invoices/'.$invoice->id);

        $response->assertNoContent();

        $this->assertDatabaseHas('billing_invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_platform_admin_cannot_cancel_paid_invoice(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'paid_at' => now(),
            'due_date' => '2026-01-10',
        ]);

        $response = $this->deleteJson('/api/platform/billing/invoices/'.$invoice->id);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Não é possível cancelar uma fatura já paga.',
        ]);
    }

    public function test_non_admin_cannot_cancel_platform_invoice(): void
    {
        $user = $this->makeRegularUser();
        Sanctum::actingAs($user, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'due_date' => '2026-01-10',
        ]);

        $response = $this->deleteJson('/api/platform/billing/invoices/'.$invoice->id);

        $response->assertForbidden();
    }
}
