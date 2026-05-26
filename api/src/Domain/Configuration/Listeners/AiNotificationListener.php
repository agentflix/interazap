<?php

declare(strict_types=1);

namespace Domain\Configuration\Listeners;

use Domain\Configuration\Events\AiEscalationRequiredEvent;
use Domain\Configuration\Events\AiHotLeadDetectedEvent;
use Domain\Configuration\Events\StorageLimitWarningEvent;
use Domain\Configuration\Services\NotificationDispatcherService;

/**
 * Listener de eventos de IA para geração de notificações internas.
 */
final class AiNotificationListener
{
    /** Injeta o serviço de despacho de notificações. */
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
    ) {}

    /**
     * Processa eventos de IA e despacha notificação interna ao tenant.
     *
     * @param  AiHotLeadDetectedEvent|AiEscalationRequiredEvent|StorageLimitWarningEvent  $event  Evento disparado.
     */
    public function handle(AiHotLeadDetectedEvent|AiEscalationRequiredEvent|StorageLimitWarningEvent $event): void
    {
        if ($event instanceof AiHotLeadDetectedEvent) {
            $this->dispatcher->dispatch(
                tenantId: $event->tenantId,
                userIds: '*',
                type: 'system',
                title: $event->title,
                body: $event->message,
                data: $event->data,
                priority: 'urgent',
            );

            return;
        }

        if ($event instanceof AiEscalationRequiredEvent) {
            $this->dispatcher->dispatch(
                tenantId: $event->tenantId,
                userIds: '*',
                type: 'system',
                title: $event->title,
                body: $event->message,
                data: $event->data,
                priority: 'urgent',
            );

            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: '*',
            type: 'system',
            title: $event->title,
            body: $event->message,
            data: $event->data,
            priority: 'high',
        );
    }
}
