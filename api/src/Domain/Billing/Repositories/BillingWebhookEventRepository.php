<?php

declare(strict_types=1);

namespace Domain\Billing\Repositories;

use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Str;

/**
 * Repositório para persistir eventos de webhook de billing.
 */
final class BillingWebhookEventRepository
{
    public function storeIfNotExists(PlatformTenant $tenant, BillingWebhookDTO $dto): bool
    {
        $payloadJson = json_encode($dto->payload, JSON_THROW_ON_ERROR);

        $inserted = \Domain\Billing\Models\BillingWebhookEvent::query()->insertOrIgnore([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'domain' => 'billing',
            'stream_id' => $dto->providerEventId ?? $dto->idempotencyKey,
            'provider' => $dto->provider,
            'instance_webhook_token' => (string) ($tenant->billing_webhook_token ?? 'billing'),
            'provider_event_id' => $dto->providerEventId,
            'event_type' => $dto->eventType,
            'idempotency_key' => $dto->idempotencyKey,
            'payload' => $payloadJson,
            'payload_hash' => $dto->payloadHash,
            'payload_json' => $payloadJson,
            'received_at' => now(),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted > 0;
    }
}
