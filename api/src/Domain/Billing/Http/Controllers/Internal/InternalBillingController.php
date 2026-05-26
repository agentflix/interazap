<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers\Internal;

use Domain\Billing\Actions\Internal\CreateBillingWebhookEventAction;
use Domain\Billing\Actions\Internal\ResolveTenantByBillingTokenAction;
use Domain\Billing\Actions\Internal\UpdateBillingWebhookEventStreamIdAction;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller interno consumido pelo gateway para operações de billing.
 *
 * Protegido pelo middleware `internal.api.key` (header X-Internal-Api-Key).
 * Substitui acesso direto do gateway às tabelas platform_tenants e
 * shared_webhook_events (domain=billing) via pg.Pool.
 *
 * @category Controllers
 */
final class InternalBillingController extends BaseController
{
    public function __construct(
        private readonly ResolveTenantByBillingTokenAction $resolveAction,
        private readonly CreateBillingWebhookEventAction $createEventAction,
        private readonly UpdateBillingWebhookEventStreamIdAction $updateStreamIdAction,
    ) {}

    /**
     * GET /api/internal/billing/tenants/by-webhook-token/{token}
     */
    public function tenantByWebhookToken(string $token): JsonResponse
    {
        $result = $this->resolveAction->execute($token);

        if ($result === null) {
            return $this->notFound('Tenant not found for the provided token.');
        }

        return $this->success($result);
    }

    /**
     * POST /api/internal/billing/webhook-events
     */
    public function createWebhookEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'external_id' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->createEventAction->execute($data);

        return $this->created($result);
    }

    /**
     * PATCH /api/internal/billing/webhook-events/{eventId}/stream-id
     */
    public function updateStreamId(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate([
            'stream_id' => ['required', 'string', 'max:255'],
        ]);

        $updated = $this->updateStreamIdAction->execute($eventId, $data['stream_id']);

        if (! $updated) {
            return $this->notFound('Webhook event not found.');
        }

        return $this->success(['updated' => true]);
    }
}
