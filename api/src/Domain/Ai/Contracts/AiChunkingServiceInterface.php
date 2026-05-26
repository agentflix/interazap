<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\Enums\AiDocumentType;

/**
 * Contrato para o serviço de chunking de documentos.
 *
 * Define a interface para fragmentação de texto em chunks com overlap,
 * utilizado no pipeline de processamento da Knowledge Base RAG.
 */
interface AiChunkingServiceInterface
{
    /**
     * Fragmenta o texto em pedaços menores com overlap.
     *
     * @param  string  $text  Texto a ser fragmentado.
     * @param  AiDocumentType|null  $type  Tipo do documento para chunking específico por tipo.
     * @return list<\Domain\Ai\DTOs\ChunkDTO>
     */
    public function chunk(string $text, ?AiDocumentType $type = null): array;

    /**
     * Estima a contagem de tokens de um texto.
     */
    public function estimateTokens(string $text): int;
}
