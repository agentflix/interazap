<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\AiCompletionDTO;
use Domain\Ai\DTOs\AiCompletionResultDTO;

/**
 * Contrato para provedores de IA (LLM).
 *
 * Define a interface para execução de completions com qualquer provedor
 * de modelo de linguagem integrado à plataforma.
 */
interface AiProviderInterface
{
    /**
     * Executa uma completion no provedor de IA.
     *
     * @param  AiCompletionDTO  $request  Requisição de completion.
     * @return AiCompletionResultDTO Resultado da completion.
     *
     * @throws \RuntimeException Se a completion não puder ser gerada.
     */
    public function complete(AiCompletionDTO $request): AiCompletionResultDTO;

    /**
     * Verifica se o provedor está disponível.
     */
    public function isAvailable(): bool;

    /**
     * Retorna o nome do provedor.
     */
    public function getName(): string;
}
