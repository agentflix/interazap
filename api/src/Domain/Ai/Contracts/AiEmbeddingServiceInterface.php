<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

/**
 * Contrato para o serviço de geração de embeddings vetoriais.
 *
 * Define a interface para vetorização de textos utilizada no pipeline
 * de indexação e busca semântica (RAG) via pgvector.
 */
interface AiEmbeddingServiceInterface
{
    /**
     * Gera embedding vetorial para um único texto.
     *
     * @return list<float> Vetor com 1536 dimensões.
     */
    public function embed(string $text): array;

    /**
     * Gera embeddings vetoriais para múltiplos textos em lote.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}
