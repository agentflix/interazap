<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Exceptions\AiPromptNoPreviousContentException;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Services\AiPromptHashService;

/**
 * Action para rollback de Tenant Prompts.
 *
 * Implementa a lógica de swap infinito entre content e previous_content.
 */
final class RollbackTenantPromptAction
{
    public function __construct(
        private readonly AiPromptHashService $hashService,
    ) {}

    public function execute(AiPromptTenant $tenantPrompt): AiPromptTenant
    {
        if (! $tenantPrompt->hasPreviousContent()) {
            throw new AiPromptNoPreviousContentException;
        }

        // Swap logic
        $currentContent = $tenantPrompt->content;
        $previousContent = $tenantPrompt->previous_content;

        $tenantPrompt->forceFill([
            'content' => $previousContent,
            'previous_content' => $currentContent,
            'version' => $tenantPrompt->version + 1,
            'validation_status' => AiPromptValidationStatus::APPROVED,
            'validated_hash' => $this->hashService->hash($previousContent),
            'validated_at' => now(),
        ])->save();

        return $tenantPrompt->refresh();
    }
}
