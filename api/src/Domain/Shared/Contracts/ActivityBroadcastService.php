<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

/**
 * Contrato para transmissão de atualizações de atividade entre domínios.
 *
 * Define os métodos de broadcast em tempo real que devem ser
 * implementados pelo serviço concreto de gateway WebSocket.
 */
interface ActivityBroadcastService
{
    /**
     * Emite evento de contato atualizado para os clientes do chat.
     *
     * @param  string  $chatId  Identificador do chat relacionado.
     * @param  string  $contactId  Identificador do contato atualizado.
     * @param  array<string, mixed>  $contactData  Dados atualizados do contato.
     */
    public function emitContactUpdated(string $chatId, string $contactId, array $contactData): void;

    /**
     * Transmite evento de mudança de status de negociação para o tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $contactId  Identificador do contato relacionado.
     * @param  array<string, mixed>  $statusData  Dados do novo status da negociação.
     */
    public function broadcastNegotiationStatusChanged(string $tenantId, string $contactId, array $statusData): void;
}
