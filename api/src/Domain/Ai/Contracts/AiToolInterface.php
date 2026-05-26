<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;

/**
 * Contrato para ferramentas (functions) invocáveis pelo agente de IA.
 *
 * Define a interface que toda tool deve implementar para ser registrada
 * no ToolDispatcherService e executada durante um run do Autopilot.
 */
interface AiToolInterface
{
    /**
     * Executa a ferramenta com o input fornecido pelo agente.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO;

    /**
     * Retorna o nome identificador da ferramenta (ex.: 'create_contact').
     */
    public function getName(): string;

    /**
     * Retorna a descrição da ferramenta para o LLM.
     */
    public function getDescription(): string;

    /**
     * Retorna o schema de parâmetros esperados pela ferramenta.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array;
}
