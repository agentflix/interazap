<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Internal;

use Domain\Billing\Models\BillingWebhookEvent;
use Illuminate\Support\Str;

/**
 * Registra um evento de webhook de billing recebido pelo gateway.
 *
 * O campo domain='billing' é fixado automaticamente pelo boot do modelo.
 *
 * @category Actions
 */
final readonly class CreateBillingWebhookEventAction
{
    /**
     * @param  array{tenant_id: string, event_type: string, payload: array<string, mixed>, external_id?: string|null}  $data
     * @return array{event_id: string, created_at: string}
     */
    public function execute(array $data): array
    {
        $event = BillingWebhookEvent::create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $data['tenant_id'],
            'event_type' => $data['event_type'],
            'payload' => $data['payload'],
            'provider' => $data['provider'] ?? 'billing',
            'instance_webhook_token' => $data['instance_webhook_token'] ?? 'billing',
            'provider_event_id' => $data['external_id'] ?? null,
        ]);

        return [
            'event_id' => (string) $event->id,
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }
}
