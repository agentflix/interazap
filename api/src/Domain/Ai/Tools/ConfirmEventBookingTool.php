<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Configuration\Services\NotificationDispatcherService;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMEventClientConfirmation;

/**
 * Tool to confirm or decline an event booking by client response.
 *
 * Updates the confirmation status and the associated CRM event,
 * then notifies the tenant users about the result.
 */
class ConfirmEventBookingTool implements AiToolInterface
{
    /**
     * Create a new tool instance.
     */
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
    ) {}

    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $confirmationId = (string) ($input->parameters['confirmation_id'] ?? '');
        $action = (string) ($input->parameters['action'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($confirmationId === '') {
            return ToolResultDTO::failure('confirmation_id is required');
        }

        if (! in_array($action, [CRMEventClientConfirmation::STATUS_CONFIRMED, CRMEventClientConfirmation::STATUS_DECLINED], true)) {
            return ToolResultDTO::failure('action must be "confirmed" or "declined"');
        }

        // Find confirmation scoped to tenant (tenant isolation)
        $confirmation = CRMEventClientConfirmation::query()
            ->where('tenant_id', $tenantId)
            ->with(['crmEvent'])
            ->find($confirmationId);

        if (! $confirmation instanceof CRMEventClientConfirmation) {
            return ToolResultDTO::failure('Confirmation not found');
        }

        if ($confirmation->status !== CRMEventClientConfirmation::STATUS_PENDING) {
            return ToolResultDTO::failure('Confirmation already processed');
        }

        $event = $confirmation->crmEvent;

        if (! $event instanceof CRMEvent) {
            return ToolResultDTO::failure('Associated event not found');
        }

        // Update confirmation
        $confirmation->status = $action;
        $confirmation->response_at = now();
        $confirmation->save();

        // Update event and notify tenant
        if ($action === CRMEventClientConfirmation::STATUS_CONFIRMED) {
            $event->status = CRMEvent::STATUS_CONFIRMED;
            $event->save();

            $this->notifications->dispatch(
                tenantId: $tenantId,
                userIds: '*',
                type: 'event_confirmed',
                title: 'Agendamento confirmado pelo cliente',
                body: "O cliente confirmou o evento '{$event->title}'",
                data: [
                    'entity_type' => 'crm_event',
                    'entity_id' => $event->id,
                    'confirmation_id' => $confirmation->id,
                    'event_title' => $event->title,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                ],
                priority: 'normal',
            );

            return ToolResultDTO::success(
                message: 'Event confirmed by client',
                data: [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'status' => CRMEvent::STATUS_CONFIRMED,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                ]
            );
        }

        // Declined
        $event->status = CRMEvent::STATUS_CANCELLED;
        $event->save();

        $this->notifications->dispatch(
            tenantId: $tenantId,
            userIds: '*',
            type: 'event_cancelled',
            title: 'Agendamento recusado pelo cliente',
            body: "O cliente recusou o evento '{$event->title}'",
            data: [
                'entity_type' => 'crm_event',
                'entity_id' => $event->id,
                'confirmation_id' => $confirmation->id,
                'event_title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
            ],
            priority: 'normal',
        );

        return ToolResultDTO::success(
            message: 'Event declined by client',
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'status' => CRMEvent::STATUS_CANCELLED,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CONFIRM_EVENT_BOOKING;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Confirms or declines an event booking based on client response, updates the event status and notifies the tenant.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'confirmation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the client confirmation record',
            ],
            'action' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Action to take: "confirmed" or "declined"',
            ],
        ];
    }
}
