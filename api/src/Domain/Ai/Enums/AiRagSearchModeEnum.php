<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Modo de busca utilizado no pipeline RAG.
 *
 * - VECTOR: Busca exclusiva por similaridade vetorial (pgvector).
 * - HYBRID: Busca combinada de similaridade vetorial + palavras-chave.
 */
enum AiRagSearchModeEnum: string
{
    case VECTOR = 'vector';
    case HYBRID = 'hybrid';

    /**
     * Retorna o rótulo de exibição do modo de busca.
     */
    public function label(): string
    {
        return match ($this) {
            self::VECTOR => 'Vector Search',
            self::HYBRID => 'Hybrid Search',
        };
    }
}
