<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Motivo pelo qual uma conclusão de IA foi encerrada.
 *
 * Valores possíveis: parada normal, limite de tokens, filtro de conteúdo ou chamada de ferramenta.
 */
enum AIFinishReason: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case CONTENT_FILTER = 'content_filter';
    case TOOL_CALLS = 'tool_calls';

    /** Retorna o rótulo legível para exibição. */
    public function label(): string
    {
        return match ($this) {
            self::STOP => 'Stop',
            self::LENGTH => 'Length Limit',
            self::CONTENT_FILTER => 'Content Filter',
            self::TOOL_CALLS => 'Tool Calls',
        };
    }
}
