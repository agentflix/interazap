<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;

/**
 * Action para resolver waba_id (WhatsApp Business Account ID da Meta) para
 * dados de uma ChatInstance ativa.
 *
 * Usado pelo Gateway para enriquecer eventos `message_template_status_update`
 * antes de publicá-los no stream `chat-events`. O webhook de status de
 * template não traz `phone_number_id` — apenas o `waba_id` da conta — por isso
 * precisamos deste lookup adicional.
 *
 * Realiza busca global (sem isolamento de tenant) pois o `waba_id` é único
 * globalmente no escopo da Meta e o Gateway autentica via GATEWAY_SECRET.
 *
 * Quando há múltiplas instâncias para o mesmo WABA, retorna a primeira ativa
 * (ordem por `created_at` ASC para determinismo).
 *
 * @category Actions
 */
final readonly class LookupInstanceByWabaIdAction
{
    /**
     * Resolve waba_id para identificadores da ChatInstance.
     *
     * @param  string  $wabaId  WhatsApp Business Account ID da Meta.
     * @return array{tenant_id: string, instance_id: string}|null
     */
    public function execute(string $wabaId): ?array
    {
        $instance = ChatInstance::query()
            ->where('settings_json->waba_id', $wabaId)
            ->where('provider', 'meta')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        if (! $instance instanceof ChatInstance) {
            return null;
        }

        return [
            'tenant_id' => (string) $instance->tenant_id,
            'instance_id' => (string) $instance->id,
        ];
    }
}
