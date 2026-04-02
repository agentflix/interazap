<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

/**
 * Exception thrown when embedding generation fails after retries.
 */
final class EmbeddingFailedException extends \RuntimeException {}
