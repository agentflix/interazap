<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Billing\Actions\BillingAsaasWebhookAction;
use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class BillingUnlockOnPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_locked_tenant_is_auto_unlocked_on_payment_confirmed_webhook(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => BillingTenantStatus::LOCKED,
            'billing_locked_at' => now(),
            'billing_lock_reason' => 'overdue_invoice',
            'billing_purge_deadline' => now()->addDays(20)->toDateString(),
        ]);

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'asaas_payment_id' => 'pay_12345',
            'amount' => 199.90,
        ]);

        $dto = BillingWebhookDTO::fromArray([
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_12345',
                'externalReference' => (string) $invoice->id,
                'value' => 199.90,
                'billingType' => 'PIX',
            ],
        ]);

        $result = app(BillingAsaasWebhookAction::class)->handle($tenant, $dto, true);

        $this->assertTrue($result['created']);
        $this->assertTrue($result['invoice_updated']);

        $invoice->refresh();
        $tenant->refresh();

        $this->assertSame('paid', $invoice->status->value);
        $this->assertEquals(BillingTenantStatus::ACTIVE, $tenant->billing_status);
        $this->assertNull($tenant->billing_locked_at);
        $this->assertNull($tenant->billing_lock_reason);
    }
}
