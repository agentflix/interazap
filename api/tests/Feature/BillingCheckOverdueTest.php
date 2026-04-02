<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Billing\Actions\BillingCheckOverdueAction;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class BillingCheckOverdueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_grace_transition_at_d_plus_1(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::ACTIVE,
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $result = app(BillingCheckOverdueAction::class)->handle();

        $this->assertSame(1, $result['grace']);
        $this->assertEquals(BillingTenantStatus::GRACE, $tenant->fresh()->billing_status);
    }

    public function test_lock_transition_at_d_plus_5(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::ACTIVE,
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $result = app(BillingCheckOverdueAction::class)->handle();

        $this->assertSame(1, $result['locked']);
        $this->assertEquals(BillingTenantStatus::LOCKED, $tenant->fresh()->billing_status);
    }

    public function test_pending_purge_transition_at_d_plus_25(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::LOCKED,
            'billing_purge_deadline' => now()->addDays(5)->toDateString(),
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(25)->toDateString(),
        ]);

        $result = app(BillingCheckOverdueAction::class)->handle();

        $this->assertSame(1, $result['pending_purge']);
        $this->assertEquals(BillingTenantStatus::PENDING_PURGE, $tenant->fresh()->billing_status);
    }

    public function test_no_transition_for_paid_invoice(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::ACTIVE,
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $result = app(BillingCheckOverdueAction::class)->handle();

        $this->assertSame(0, $result['processed']);
        $this->assertEquals(BillingTenantStatus::ACTIVE, $tenant->fresh()->billing_status);
    }

    public function test_idempotency_does_not_repeat_transition_on_second_run(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::ACTIVE,
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        app(BillingCheckOverdueAction::class)->handle();
        $second = app(BillingCheckOverdueAction::class)->handle();

        $this->assertSame(0, $second['grace']);
        $this->assertSame(0, $second['locked']);
        $this->assertEquals(BillingTenantStatus::LOCKED, $tenant->fresh()->billing_status);
    }
}
