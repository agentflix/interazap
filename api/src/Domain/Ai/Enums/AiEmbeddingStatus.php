<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Status de processamento de embeddings para documentos da Knowledge Base.
 *
 * Representa o ciclo de vida de processamento de um documento:
 * - PENDING: Aguardando processamento na fila
 * - PROCESSING: Sendo processado (chunking + embedding)
 * - READY: Processado com sucesso, disponível para busca
 * - FAILED: Falha no processamento
 */
enum AiEmbeddingStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';

    /**
     * Retorna se o documento está pronto para busca.
     */
    public function isSearchable(): bool
    {
        return $this === self::READY;
    }

    /**
     * Retorna se o documento está em processamento.
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * Retorna se o documento pode ser reprocessado.
     */
    public function canReprocess(): bool
    {
        return in_array($this, [self::READY, self::FAILED], true);
    }

    /**
     * Retorna a cor de badge para exibição.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::PROCESSING => 'blue',
            self::READY => 'green',
            self::FAILED => 'red',
        };
    }

    /**
     * Retorna o label para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::READY => 'Ready',
            self::FAILED => 'Failed',
        };
    }
}
