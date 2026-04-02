<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Tipos de gatilho suportados pela Trigger Engine do Autopilot.
 */
enum AutopilotTriggerType: string
{
    case MANUAL = 'MANUAL';
    case INBOUND_MESSAGE = 'INBOUND_MESSAGE';
    case TICKET_CREATED = 'TICKET_CREATED';
    case TICKET_IDLE = 'TICKET_IDLE';
    case NEGOTIATION_STAGE_CHANGED = 'NEGOTIATION_STAGE_CHANGED';
    case NEGOTIATION_WON = 'NEGOTIATION_WON';
    case NEGOTIATION_LOST = 'NEGOTIATION_LOST';
    case CONTACT_CREATED = 'CONTACT_CREATED';
    case HUMAN_TAKEOVER_ENDED = 'HUMAN_TAKEOVER_ENDED';
    case SCHEDULED = 'SCHEDULED';
    case TAG_ADDED = 'TAG_ADDED';
    case LEAD_SCORE_THRESHOLD = 'LEAD_SCORE_THRESHOLD';
    case NO_RESPONSE_TIMEOUT = 'NO_RESPONSE_TIMEOUT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $item): string => $item->value,
            self::cases(),
        );
    }

    /**
     * Define se o gatilho depende de scheduler.
     */
    public function isScheduleBased(): bool
    {
        return in_array($this, [
            self::TICKET_IDLE,
            self::SCHEDULED,
            self::NO_RESPONSE_TIMEOUT,
        ], true);
    }

    /**
     * Rótulo amigável para uso em UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::INBOUND_MESSAGE => 'Mensagem Recebida',
            self::TICKET_CREATED => 'Ticket Criado',
            self::TICKET_IDLE => 'Ticket Ocioso',
            self::NEGOTIATION_STAGE_CHANGED => 'Etapa Alterada',
            self::NEGOTIATION_WON => 'Negociação Ganha',
            self::NEGOTIATION_LOST => 'Negociação Perdida',
            self::CONTACT_CREATED => 'Contato Criado',
            self::HUMAN_TAKEOVER_ENDED => 'Atendimento Humano Encerrado',
            self::SCHEDULED => 'Agendado',
            self::TAG_ADDED => 'Tag Adicionada',
            self::LEAD_SCORE_THRESHOLD => 'Lead Score Atingido',
            self::NO_RESPONSE_TIMEOUT => 'Sem Resposta',
        };
    }
}
