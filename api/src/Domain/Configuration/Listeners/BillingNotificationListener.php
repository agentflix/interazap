<?php

declare(strict_types=1);

namespace Domain\Configuration\Listeners;

use Domain\Configuration\Events\BillingInvoiceCreatedEvent;
use Domain\Configuration\Events\BillingPaymentConfirmedEvent;
use Domain\Configuration\Events\BillingPaymentOverdueEvent;
use Domain\Configuration\Services\NotificationDispatcherService;

/**
 * Listener de eventos de Billing para geração de notificações.
 */
final class BillingNotificationListener
{
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(BillingInvoiceCreatedEvent|BillingPaymentConfirmedEvent|BillingPaymentOverdueEvent $event): void
    {
        if ($event instanceof BillingPaymentConfirmedEvent) {
            $this->dispatcher->dispatch(
                tenantId: $event->tenantId,
                userIds: '*',
                type: 'billing',
                title: 'Pagamento confirmado',
                body: sprintf('Fatura %s foi confirmada com sucesso.', $event->referenceMonth),
                data: [
                    'invoice_id' => $event->invoiceId,
                    'amount' => $event->amount,
                    'reference_month' => $event->referenceMonth,
                ],
                priority: 'normal',
            );

            return;
        }

        if ($event instanceof BillingPaymentOverdueEvent) {
            $this->dispatcher->dispatch(
                tenantId: $event->tenantId,
                userIds: '*',
                type: 'billing',
                title: 'Pagamento em atraso',
                body: sprintf('A fatura %s está em atraso.', $event->referenceMonth),
                data: [
                    'invoice_id' => $event->invoiceId,
                    'amount' => $event->amount,
                    'reference_month' => $event->referenceMonth,
                ],
                priority: 'urgent',
            );

            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: '*',
            type: 'billing',
            title: 'Nova fatura criada',
            body: sprintf('A fatura %s foi criada e está disponível.', $event->referenceMonth),
            data: [
                'invoice_id' => $event->invoiceId,
                'amount' => $event->amount,
                'reference_month' => $event->referenceMonth,
            ],
            priority: 'normal',
        );
    }
}
