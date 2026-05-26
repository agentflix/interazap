<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts\Chunkers;

use Domain\Ai\DTOs\ChunkDTO;

/**
 * Contrato para estratégias de chunking de texto.
 *
 * Define a interface que cada estratégia de fragmentação deve implementar,
 * permitindo que AiChunkingService utilize diferentes algoritmos de chunking
 * por tipo de documento.
 */
interface ChunkerStrategyInterface
{
    /**
     * Fragmenta o texto em pedaços menores.
     *
     * @return list<ChunkDTO>
     */
    public function chunk(string $text): array;
}
