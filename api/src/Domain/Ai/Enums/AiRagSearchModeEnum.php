<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Search mode for RAG retrieval.
 *
 * - VECTOR: Pure vector similarity search
 * - HYBRID: Combined vector + keyword search
 */
enum AiRagSearchModeEnum: string
{
    case VECTOR = 'vector';
    case HYBRID = 'hybrid';

    /**
     * Returns label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::VECTOR => 'Vector Search',
            self::HYBRID => 'Hybrid Search',
        };
    }
}
