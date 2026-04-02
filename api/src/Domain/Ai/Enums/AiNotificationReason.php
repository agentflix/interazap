<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Motivos para notificação de vendedor.
 */
enum AiNotificationReason: string
{
    case HOT_LEAD = 'hot_lead';
    case URGENT_QUESTION = 'urgent_question';
    case NEGATIVE_SENTIMENT = 'negative_sentiment';
    case ESCALATION = 'escalation';
    case CUSTOM = 'custom';
    case VIP_CLIENT = 'vip_client';

    /**
     * Retorna o label legível.
     */
    public function label(): string
    {
        return match ($this) {
            self::HOT_LEAD => 'Hot Lead',
            self::URGENT_QUESTION => 'Urgent Question',
            self::NEGATIVE_SENTIMENT => 'Negative Sentiment',
            self::ESCALATION => 'Escalation',
            self::CUSTOM => 'Custom',
            self::VIP_CLIENT => 'VIP Client',
        };
    }

    /**
     * Retorna o nível de prioridade.
     */
    public function priority(): string
    {
        return match ($this) {
            self::ESCALATION => 'critical',
            self::HOT_LEAD, self::URGENT_QUESTION, self::NEGATIVE_SENTIMENT, self::VIP_CLIENT => 'high',
            self::CUSTOM => 'normal',
        };
    }
}
