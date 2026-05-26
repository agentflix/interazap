<?php

declare(strict_types=1);

namespace Domain\Chat\Repositories;

use Domain\Chat\Models\ChatInstance;

/**
 * Contrato de repositório para resolução de instâncias de chat por token de webhook.
 */
interface ChatInstanceRepository
{
    /**
     * Resolve uma instância ativa a partir do token de webhook.
     *
     * @param  string  $token  Token de webhook da instância.
     * @return ChatInstance|null Instância ativa ou null se não encontrada ou inativa.
     */
    public function resolveInstanceByToken(string $token): ?ChatInstance;

    /**
     * Busca uma instância pelo token sem validar o status de ativação.
     *
     * @param  string  $token  Token de webhook da instância.
     * @return ChatInstance|null Instância encontrada ou null.
     */
    public function findByWebhookToken(string $token): ?ChatInstance;
}
