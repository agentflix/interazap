<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiPromptHashServiceInterface;

/**
 * Serviço de hash para verificação de integridade de prompts.
 *
 * Utiliza SHA256 para garantir que o prompt aprovado não foi alterado
 * entre a validação e a execução.
 */
final class AiPromptHashService implements AiPromptHashServiceInterface
{
    private const ALGORITHM = 'sha256';

    /**
     * Calcula o hash SHA256 do conteúdo.
     *
     * @param  string  $content  O conteúdo a ser hasheado
     * @return string Hash hexadecimal de 64 caracteres
     */
    public function hash(string $content): string
    {
        return hash(self::ALGORITHM, $content);
    }

    /**
     * Verifica se o conteúdo corresponde ao hash fornecido.
     *
     * @param  string  $content  O conteúdo a ser verificado
     * @param  string  $hash  O hash esperado
     * @return bool True se o hash corresponder
     */
    public function verify(string $content, string $hash): bool
    {
        return hash_equals($hash, $this->hash($content));
    }

    /**
     * Retorna o algoritmo de hash utilizado.
     */
    public function getAlgorithm(): string
    {
        return self::ALGORITHM;
    }
}
