<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Ai\Services\AiToolEntityResolver;
use Illuminate\Support\Str;

/**
 * Ferramenta para notificar vendedores.
 */
class NotifySellerTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolEntityResolver $entityResolver,
    ) {}

    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $sellerId = $input->parameters['seller_id'] ?? null;
        $ticketId = $input->parameters['ticket_id'] ?? $input->context['ticket_id'] ?? null;
        $message = $input->parameters['message'] ?? '';
        $reason = $input->parameters['reason'] ?? 'general';
        $channel = $input->parameters['channel'] ?? 'email';
        $priority = $input->parameters['priority'] ?? 'normal';
        $tenantId = $input->context['tenant_id'] ?? null;

        if (empty($tenantId)) {
            return ToolResultDTO::failure('Tenant ID is required');
        }

        if (empty(trim($message))) {
            return ToolResultDTO::failure('Message cannot be empty');
        }

        $seller = $this->entityResolver->resolveSeller(
            (string) $tenantId,
            $input->parameters,
            is_string($ticketId) && Str::isUuid($ticketId) ? $ticketId : null,
        );

        if (! $seller) {
            return ToolResultDTO::failure('Seller could not be resolved for this tenant.', [
                'error_code' => 'seller_not_found',
                'recoverable' => true,
                'hint' => 'Use send_message to tell the customer a specialist will follow up, or transfer_to_human.',
            ]);
        }

        // Map string reason to enum if possible
        $reasonEnum = AiNotificationReason::tryFrom($reason);
        $channelEnum = AiNotificationChannel::tryFrom($channel);

        $notification = AiSellerNotification::query()->create([
            'tenant_id' => (string) $tenantId,
            'seller_id' => (string) $seller->id,
            'message' => (string) $message,
            'reason' => ($reasonEnum ?? AiNotificationReason::CUSTOM)->value,
            'channel' => ($channelEnum ?? AiNotificationChannel::EMAIL)->value,
            'priority' => (string) $priority,
            'metadata' => [
                'source' => 'autopilot_tool',
                'tool' => $this->getName(),
            ],
        ]);

        return ToolResultDTO::success(
            message: 'Notification persisted and queued for delivery',
            data: [
                'notification_id' => $notification->id,
                'seller_id' => (string) $seller->id,
                'seller_name' => $seller->name,
                'channel' => $channelEnum !== null ? $channelEnum->value : $channel,
                'reason' => $reasonEnum !== null ? $reasonEnum->value : $reason,
                'priority' => $priority,
                'status' => 'pending',
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER;
    }

    public function getDescription(): string
    {
        return 'Sends a notification to a seller about an important event. Supports email and WhatsApp channels with automatic fallback.';
    }

    /**
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'seller_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of the seller to notify. Optional when seller, seller_name, seller_email, ticket_id, or default tenant seller can resolve it.',
            ],
            'seller' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Human-readable seller name or alias, e.g. "Rosa".',
            ],
            'seller_name' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Human-readable seller name.',
            ],
            'seller_email' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Seller email address.',
            ],
            'ticket_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Ticket UUID used to resolve assigned/default seller.',
            ],
            'message' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Notification message content',
            ],
            'reason' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Reason for notification: high_value_lead, escalation, urgent_response, appointment_set, follow_up_reminder, general',
            ],
            'channel' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Delivery channel: email (default), whatsapp',
            ],
            'priority' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Priority level: low, normal, high, urgent',
            ],
        ];
    }
}
