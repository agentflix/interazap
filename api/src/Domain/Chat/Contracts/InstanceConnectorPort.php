<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\ConnectionResultDTO;

/**
 * Port para conexão e desconexão de instâncias de canal.
 *
 * Define as operações de ciclo de vida de uma instância (conectar, desconectar,
 * reiniciar e configurar webhooks) independente do provedor utilizado.
 */
interface InstanceConnectorPort
{
    /** Conecta a instância no modo especificado, opcionalmente vinculando um número de telefone. */
    public function connect(string $mode, ?string $phone = null): ConnectionResultDTO;

    /** Desconecta a instância do provedor. */
    public function disconnect(): bool;

    /** Reinicia a instância sem desconectar o número associado. */
    public function restart(): bool;

    /**
     * Configura os eventos de webhook que o provedor deve notificar.
     *
     * @param  array<string>  $events  Lista de tipos de evento a registrar.
     */
    public function configureWebhooks(string $webhookUrl, array $events): bool;
}
