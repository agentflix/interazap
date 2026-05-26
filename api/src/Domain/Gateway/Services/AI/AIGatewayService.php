<?php

declare(strict_types=1);

namespace Domain\Gateway\Services\AI;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Gateway\Contracts\GatewayClientInterface;
use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\Enums\GatewayDomain;
use Domain\Gateway\Enums\GatewayProvider;
use Domain\Gateway\Exceptions\GatewayException;

/**
 * Implementação do AIServiceInterface que delega completions ao gateway NestJS via Redis.
 *
 * Constrói uma GatewayMessage, envia de forma bloqueante via GatewayClientInterface
 * e mapeia a resposta para AICompletionResponse.
 */
final class AIGatewayService implements AIServiceInterface
{
    /**
     * @param  GatewayClientInterface  $client  Cliente de comunicação com o gateway
     * @param  GatewayProvider  $defaultProvider  Provider de IA padrão
     * @param  int  $timeoutSeconds  Timeout de espera da resposta em segundos
     */
    public function __construct(
        private readonly GatewayClientInterface $client,
        private readonly GatewayProvider $defaultProvider = GatewayProvider::OPENAI,
        private readonly int $timeoutSeconds = 180,
    ) {}

    /**
     * Executa uma requisição de completion e retorna a resposta do modelo de IA.
     *
     * Fluxo: cria GatewayMessage no domínio AI, envia via client (bloqueante) e
     * converte o payload de resposta em AICompletionResponse.
     *
     * @param  AICompletionRequest  $request  Requisição com mensagens, modelo e parâmetros
     * @return AICompletionResponse Resposta com conteúdo, tokens e chamadas de ferramentas
     *
     * @throws GatewayException Quando o gateway retorna sucesso sem dados
     */
    public function complete(AICompletionRequest $request): AICompletionResponse
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'complete',
            provider: $this->defaultProvider->value,
            payload: $request->toArray(),
        );

        $response = $this->client->send($message, $this->timeoutSeconds);

        if ($response->data === null) {
            throw new GatewayException(
                message: 'Gateway returned success without data',
                correlationId: $message->correlationId,
            );
        }

        return AICompletionResponse::fromArray($response->data);
    }

    /** Retorna o nome do provider de IA atualmente configurado. */
    public function getProvider(): string
    {
        return $this->defaultProvider->value;
    }
}
