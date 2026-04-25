<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

/**
 * DTO para resultado de lookup de instância por phone_number_id.
 *
 * Usado pelo Gateway para resolver phone_number_id → ChatInstance
 * durante normalização de webhooks da Meta.
 *
 * @category DTOs
 */
final readonly class InstanceLookupDTO
{
    /**
     * @param  string  $instanceId  UUID da instância.
     * @param  string  $tenantId  UUID do tenant proprietário.
     * @param  string  $webhookToken  Token para validação de webhooks.
     */
    public function __construct(
        public string $instanceId,
        public string $tenantId,
        public string $webhookToken,
    ) {}

    /**
     * Cria uma instância do DTO a partir de um array associativo.
     *
     * @param  array<string, mixed>  $data  Dados do lookup.
     */
    public static function from(array $data): self
    {
        return new self(
            instanceId: $data['instance_id'],
            tenantId: $data['tenant_id'],
            webhookToken: $data['webhook_token'],
        );
    }

    /**
     * Converte o DTO em um array associativo.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'instanceId' => $this->instanceId,
            'tenantId' => $this->tenantId,
            'webhookToken' => $this->webhookToken,
        ];
    }
}
