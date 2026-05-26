<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;

/**
 * Contrato para o serviço de IA do Gateway.
 *
 * Abstrai a execução de completions com suporte a múltiplos provedores
 * e tratamento de erros de timeout e falhas do provedor.
 */
interface AIServiceInterface
{
    /**
     * Executa uma requisição de completion e retorna a resposta.
     *
     * @param  AICompletionRequest  $request  Requisição de completion.
     * @return AICompletionResponse Resposta da completion.
     *
     * @throws GatewayTimeoutException Se o gateway exceder o tempo limite.
     * @throws ProviderException Se o provedor retornar um erro.
     */
    public function complete(AICompletionRequest $request): AICompletionResponse;

    /**
     * Retorna o nome do provedor atualmente configurado.
     *
     * @return string Identificador do provedor (ex.: 'openai', 'gemini').
     */
    public function getProvider(): string;
}
