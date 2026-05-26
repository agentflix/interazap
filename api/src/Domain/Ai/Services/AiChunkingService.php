<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\Chunkers\ChunkerStrategyInterface;
use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Services\Chunkers\CsvChunker;
use Domain\Ai\Services\Chunkers\DefaultChunker;
use Domain\Ai\Services\Chunkers\MarkdownChunker;

/**
 * Serviço de divisão de texto em chunks para indexação RAG.
 *
 * Delega para estratégias específicas por tipo de documento:
 * MarkdownChunker para arquivos markdown, CsvChunker para CSV
 * e DefaultChunker para todos os demais tipos.
 */
final class AiChunkingService implements AiChunkingServiceInterface
{
    private const float DEFAULT_CHARS_PER_TOKEN = 3.5;

    /**
     * Divide o texto em chunks com sobreposição de acordo com o tipo do documento.
     *
     * @param  string  $text  Texto a dividir.
     * @param  AiDocumentType|null  $type  Tipo do documento para seleção da estratégia.
     * @return list<ChunkDTO> Lista de chunks gerados.
     */
    public function chunk(string $text, ?AiDocumentType $type = null): array
    {
        $strategy = $this->resolveStrategy($type);

        return $strategy->chunk($text);
    }

    /**
     * Estima a quantidade de tokens de um texto usando divisão por caracteres.
     *
     * @param  string  $text  Texto a estimar.
     * @return int Estimativa de tokens.
     */
    public function estimateTokens(string $text): int
    {
        $charsPerToken = (float) config('ai.chunking.chars_per_token', self::DEFAULT_CHARS_PER_TOKEN);

        return (int) ceil(mb_strlen($text, 'UTF-8') / $charsPerToken);
    }

    /**
     * Seleciona a estratégia de chunking com base no tipo do documento.
     *
     * @param  AiDocumentType|null  $type  Tipo do documento.
     * @return ChunkerStrategyInterface Estratégia adequada ao tipo.
     */
    private function resolveStrategy(?AiDocumentType $type): ChunkerStrategyInterface
    {
        return match ($type) {
            AiDocumentType::MARKDOWN => new MarkdownChunker,
            AiDocumentType::CSV => new CsvChunker,
            default => new DefaultChunker,
        };
    }
}
