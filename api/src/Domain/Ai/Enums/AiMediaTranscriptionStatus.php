<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Status de transcrição de mídia.
 *
 * Representa o ciclo de vida de uma transcrição:
 * - PENDING: Aguardando na fila de processamento
 * - PROCESSING: Sendo transcrita/descrita pela API
 * - COMPLETED: Transcrição concluída com sucesso
 * - FAILED: Falha na transcrição
 */
enum AiMediaTranscriptionStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Retorna se a transcrição está finalizada (sucesso ou falha).
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED], true);
    }

    /**
     * Retorna se a transcrição foi bem-sucedida.
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Retorna se pode ser reprocessada.
     */
    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Retorna a cor de badge para exibição no frontend.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::PROCESSING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
        };
    }

    /**
     * Retorna o label para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::COMPLETED => 'Transcrito',
            self::FAILED => 'Falhou',
        };
    }
}
