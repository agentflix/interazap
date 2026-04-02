<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\ConnectionResultDTO;

/**
 * Port para conexão/desconexão de instâncias.
 */
interface InstanceConnectorPort
{
    public function connect(string $mode, ?string $phone = null): ConnectionResultDTO;

    public function disconnect(): bool;

    public function restart(): bool;

    /**
     * @param  array<string>  $events
     */
    public function configureWebhooks(string $webhookUrl, array $events): bool;
}
