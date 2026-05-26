<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Internal;

use Domain\Billing\Models\BillingWebhookEvent;
use Domain\Shared\Scopes\TenantScope;

/**
 * Atualiza o campo stream_id de um BillingWebhookEvent pelo seu ID.
 *
 * Remove TenantScope pois o gateway não fornece tenant context nesta operação.
 * O scope de domain='billing' é mantido via global scope do modelo.
 *
 * @category Actions
 */
final readonly class UpdateBillingWebhookEventStreamIdAction
{
    /**
     * Atualiza o stream_id de um evento de webhook pelo seu ID.
     *
     * @param  string  $eventId  UUID do evento a atualizar
     * @param  string  $streamId  Identificador do stream Redis associado
     * @return bool Verdadeiro se o registro foi encontrado e atualizado
     */
    public function execute(string $eventId, string $streamId): bool
    {
        $updated = BillingWebhookEvent::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('id', $eventId)
            ->update(['stream_id' => $streamId]);

        return $updated > 0;
    }
}
