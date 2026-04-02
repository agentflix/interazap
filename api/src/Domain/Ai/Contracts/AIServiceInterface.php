<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;

interface AIServiceInterface
{
    /**
     * Execute a completion request and return the response.
     *
     * @param  AICompletionRequest  $request  The completion request
     * @return AICompletionResponse The completion response
     *
     * @throws GatewayTimeoutException If the gateway times out
     * @throws ProviderException If the provider returns an error
     */
    public function complete(AICompletionRequest $request): AICompletionResponse;

    /**
     * Get the currently configured provider name.
     *
     * @return string The provider identifier (e.g., 'openai', 'gemini')
     */
    public function getProvider(): string;
}
