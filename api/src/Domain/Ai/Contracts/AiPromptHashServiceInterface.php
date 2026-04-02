<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

/**
 * Interface para o serviço de hash de prompts.
 */
interface AiPromptHashServiceInterface
{
    /**
     * Gera hash SHA256 do conteúdo.
     */
    public function hash(string $content): string;

    /**
     * Verifica se o hash bate com o conteúdo.
     */
    public function verify(string $content, string $hash): bool;
}
