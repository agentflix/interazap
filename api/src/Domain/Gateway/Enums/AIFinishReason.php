<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Reason why an AI completion finished.
 */
enum AIFinishReason: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case CONTENT_FILTER = 'content_filter';
    case TOOL_CALLS = 'tool_calls';

    /**
     * Returns label for display.
     */
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
