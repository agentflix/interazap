<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Tipo de evento recebido via webhook de provedores de chat.
 */
enum WebhookEventType: string
{
    /** Nova mensagem recebida ou enviada. */
    case MESSAGE = 'message';
    /** Atualização de status de entrega de uma mensagem. */
    case MESSAGE_STATUS = 'message_status';
    /** Mudança no estado de conexão da instância. */
    case CONNECTION = 'connection';
    /** Indicador de presença do contato (digitando, etc.). */
    case PRESENCE = 'presence';

    /** Retorna o rótulo amigável do tipo de evento. */
    public function label(): string
    {
        return match ($this) {
            self::MESSAGE => 'Mensagem',
            self::MESSAGE_STATUS => 'Status de Mensagem',
            self::CONNECTION => 'Conexão',
            self::PRESENCE => 'Presença',
        };
    }
}
