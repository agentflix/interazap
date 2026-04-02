<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingInvoice;
use Domain\Configuration\Events\BillingInvoiceCreatedEvent;
use Domain\Configuration\Events\BillingPaymentConfirmedEvent;
use Domain\Configuration\Events\BillingPaymentOverdueEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class BillingNotificationListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_notifications_for_billing_events(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $user->tenant_id,
            'reference_month' => '2026-02',
            'amount' => 249.90,
        ]);

        Event::dispatch(new BillingInvoiceCreatedEvent(
            tenantId: (string) $invoice->tenant_id,
            invoiceId: (string) $invoice->id,
            amount: (float) $invoice->amount,
            referenceMonth: (string) $invoice->reference_month,
        ));

        Event::dispatch(new BillingPaymentConfirmedEvent(
            tenantId: (string) $invoice->tenant_id,
            invoiceId: (string) $invoice->id,
            amount: (float) $invoice->amount,
            referenceMonth: (string) $invoice->reference_month,
        ));

        Event::dispatch(new BillingPaymentOverdueEvent(
            tenantId: (string) $invoice->tenant_id,
            invoiceId: (string) $invoice->id,
            amount: (float) $invoice->amount,
            referenceMonth: (string) $invoice->reference_month,
        ));

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $invoice->tenant_id,
            'type' => 'billing',
            'title' => 'Nova fatura criada',
        ]);

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $invoice->tenant_id,
            'type' => 'billing',
            'title' => 'Pagamento confirmado',
        ]);

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $invoice->tenant_id,
            'type' => 'billing',
            'title' => 'Pagamento em atraso',
        ]);
    }
}
