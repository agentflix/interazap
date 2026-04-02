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

final class AIGatewayService implements AIServiceInterface
{
    public function __construct(
        private readonly GatewayClientInterface $client,
        private readonly GatewayProvider $defaultProvider = GatewayProvider::OPENAI,
        private readonly int $timeoutSeconds = 180,
    ) {}

    /**
     * Execute a completion request and return the response.
     *
     * Responsibility:
     * 1. Create GatewayMessage with AI domain
     * 2. Send via client and wait for response
     * 3. Convert response to AICompletionResponse
     *
     * @throws GatewayException
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

    /**
     * Get the currently configured provider name.
     */
    public function getProvider(): string
    {
        return $this->defaultProvider->value;
    }
}
