<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Domain\Ai\Enums\SentimentLevel;

/**
 * DTO que representa o resultado da análise de sentimento de uma mensagem.
 *
 * Utilizado nos fluxos de monitoramento de satisfação do cliente, onde
 * a IA classifica o tom da mensagem (positivo, neutro, negativo) para
 * detectar insatisfação e acionar escalonamentos quando necessário.
 *
 * @readonly
 */
final readonly class SentimentResultDTO
{
    public function __construct(
        public int $score,
        public SentimentLevel $level,
        public float $confidence,
        public string $method,
    ) {}

    /**
     * Exportar payload para serialização.
     *
     * @return array{score:int,level:string,confidence:float,method:string}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level->value,
            'confidence' => $this->confidence,
            'method' => $this->method,
        ];
    }
}
