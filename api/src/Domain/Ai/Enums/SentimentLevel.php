<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Níveis de sentimento derivados do score de risco (0-100).
 */
enum SentimentLevel: string
{
    case POSITIVE = 'positive';
    case NEUTRAL = 'neutral';
    case NEGATIVE = 'negative';
    case CRITICAL = 'critical';

    /**
     * Resolver nível de sentimento por score.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score <= 30 => self::POSITIVE,
            $score <= 60 => self::NEUTRAL,
            $score <= 80 => self::NEGATIVE,
            default => self::CRITICAL,
        };
    }

    /**
     * Cor base para representação visual no frontend.
     */
    public function color(): string
    {
        return match ($this) {
            self::POSITIVE => 'green',
            self::NEUTRAL => 'yellow',
            self::NEGATIVE => 'orange',
            self::CRITICAL => 'red',
        };
    }
}
