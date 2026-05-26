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

    /**
     * Reverte o prompt do tenant para o conteúdo anterior via swap de campos.
     *
     * A lógica de swap permite reversões infinitas: content ↔ previous_content.
     * Após o rollback, o prompt recebe status APPROVED e hash recalculado
     * a partir do conteúdo restaurado.
     *
     * @param  AiPromptTenant  $tenantPrompt  Prompt a reverter.
     * @return AiPromptTenant Prompt com conteúdo revertido.
     *
     * @throws \Domain\Ai\Exceptions\AiPromptNoPreviousContentException Se não houver versão anterior.
     */
    public function execute(AiPromptTenant $tenantPrompt): AiPromptTenant
    {
        if (! $tenantPrompt->hasPreviousContent()) {
            throw new AiPromptNoPreviousContentException;
        }

        // Lógica de swap
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
