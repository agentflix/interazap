<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\InstanceLookupDTO;
use Domain\Chat\Models\ChatInstance;

/**
 * Action para resolver phone_number_id para ChatInstance.
 *
 * Usado pelo Gateway via HTTP para normalizar webhooks da Meta.
 * Realiza busca global (sem isolamento de tenant) pois o phone_number_id
 * é único globalmente e o Gateway autentica via GATEWAY_SECRET.
 *
 * @category Actions
 */
final readonly class LookupInstanceByPhoneNumberAction
{
    /**
     * Resolve phone_number_id para dados da ChatInstance.
     *
     * @param  string  $phoneNumberId  Phone Number ID da Meta.
     * @return InstanceLookupDTO|null DTO com dados da instância ou null se não encontrada.
     */
    public function execute(string $phoneNumberId): ?InstanceLookupDTO
    {
        $instance = ChatInstance::query()
            ->where('settings_json->phone_number_id', $phoneNumberId)
            ->where('provider', 'meta')
            ->first();

        if (! $instance) {
            return null;
        }

        return InstanceLookupDTO::from([
            'instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'webhook_token' => $instance->webhook_token,
        ]);
    }
}
