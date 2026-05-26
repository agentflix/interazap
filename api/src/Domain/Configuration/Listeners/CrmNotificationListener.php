<?php

declare(strict_types=1);

namespace Domain\Configuration\Listeners;

use Domain\Configuration\Events\NegotiationLostEvent;
use Domain\Configuration\Events\NegotiationWonEvent;
use Domain\Configuration\Services\NotificationDispatcherService;

/**
 * Listener de eventos de CRM para geração de notificações.
 */
final class CrmNotificationListener
{
    /** Injeta o serviço de despacho de notificações. */
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
    ) {}

    /**
     * Processa eventos de negociação e despacha notificação ao tenant.
     *
     * @param  NegotiationWonEvent|NegotiationLostEvent  $event  Evento disparado.
     */
    public function handle(NegotiationWonEvent|NegotiationLostEvent $event): void
    {
        if ($event instanceof NegotiationWonEvent) {
            $this->dispatcher->dispatch(
                tenantId: $event->tenantId,
                userIds: '*',
                type: 'system',
                title: 'Negociação ganha',
                body: sprintf('A negociação "%s" foi finalizada como ganha.', $event->title),
                data: [
                    'negotiation_id' => $event->negotiationId,
                    'amount' => $event->amount,
                ],
                priority: 'high',
            );

            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: '*',
            type: 'system',
            title: 'Negociação perdida',
            body: sprintf('A negociação "%s" foi finalizada como perdida.', $event->title),
            data: [
                'negotiation_id' => $event->negotiationId,
                'amount' => $event->amount,
            ],
            priority: 'normal',
        );
    }
}
