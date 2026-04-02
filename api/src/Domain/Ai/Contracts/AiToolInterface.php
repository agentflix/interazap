<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;

/**
 * Interface para ferramentas de IA.
 */
interface AiToolInterface
{
    /**
     * Executa a ferramenta.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO;

    /**
     * Retorna o nome da ferramenta.
     */
    public function getName(): string;

    /**
     * Retorna a descrição da ferramenta.
     */
    public function getDescription(): string;

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array;
}
