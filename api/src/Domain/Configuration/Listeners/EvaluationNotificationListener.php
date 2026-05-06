<?php

declare(strict_types=1);

namespace Domain\Configuration\Listeners;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Events\EvaluationLowScoreEvent;
use Domain\Configuration\Services\NotificationDispatcherService;

/**
 * Listener para eventos de avaliação com baixa satisfação (CSAT).
 */
final class EvaluationNotificationListener
{
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EvaluationLowScoreEvent $event): void
    {
        $recipientIds = AuthUser::query()
            ->where('tenant_id', $event->tenantId)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('id', [AuthRole::GERENTE_ID, AuthRole::ADMINISTRADOR_ID])
                    ->where('guard_name', 'sanctum');
            })
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: $recipientIds,
            type: 'evaluation_low_score',
            title: 'Avaliação crítica recebida',
            body: 'Um cliente enviou uma avaliação abaixo da nota de corte.',
            data: [
                'ticket_id' => $event->ticketId,
                'evaluation_id' => $event->evaluationId,
                'rating' => $event->rating,
                'entity_type' => 'chat_ticket_evaluation',
                'entity_id' => $event->evaluationId,
            ],
            priority: 'urgent',
        );
    }
}
