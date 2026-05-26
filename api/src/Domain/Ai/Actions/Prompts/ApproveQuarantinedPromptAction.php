<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Exceptions\AiPromptNotQuarantinedException;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Services\AiPromptHashService;

/**
 * Action para aprovação de prompts em quarentena.
 */
final class ApproveQuarantinedPromptAction
{
    public function __construct(
        private readonly AiPromptHashService $hashService,
    ) {}

    /**
     * Aprova um prompt em quarentena, calculando e gravando o hash validado.
     *
     * @param  AiPromptTenant  $tenantPrompt  Prompt a aprovar.
     * @return AiPromptTenant Prompt aprovado com hash atualizado.
     *
     * @throws AiPromptNotQuarantinedException Se o prompt não estiver em quarentena.
     */
    public function execute(AiPromptTenant $tenantPrompt): AiPromptTenant
    {
        if (! $tenantPrompt->isQuarantined()) {
            throw new AiPromptNotQuarantinedException('approve');
        }

        $tenantPrompt->forceFill([
            'validation_status' => AiPromptValidationStatus::APPROVED,
            'validated_hash' => $this->hashService->hash($tenantPrompt->content),
            'validated_at' => now(),
        ])->save();

        return $tenantPrompt->refresh();
    }
}
