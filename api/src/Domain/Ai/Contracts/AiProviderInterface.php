<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\AiCompletionDTO;
use Domain\Ai\DTOs\AiCompletionResultDTO;

/**
 * Interface for AI provider services.
 *
 * Defines the contract for AI/LLM completion providers.
 */
interface AiProviderInterface
{
    /**
     * Generate a completion from the AI provider.
     *
     * @param  AiCompletionDTO  $request  The completion request.
     * @return AiCompletionResultDTO The completion result.
     *
     * @throws \RuntimeException If the completion cannot be generated.
     */
    public function complete(AiCompletionDTO $request): AiCompletionResultDTO;

    /**
     * Check if the provider is available.
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
