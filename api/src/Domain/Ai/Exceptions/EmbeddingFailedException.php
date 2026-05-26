<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

/**
 * Exceção lançada quando a geração de embeddings falha após todas as tentativas.
 *
 * Indica falha permanente no pipeline de vetorização de chunks da Knowledge Base.
 */
final class EmbeddingFailedException extends \RuntimeException {}
