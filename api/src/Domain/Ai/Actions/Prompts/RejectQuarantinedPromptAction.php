<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Exceptions\AiPromptNotQuarantinedException;
use Domain\Ai\Models\AiPromptTenant;

/**
 * Action para rejeição de prompts em quarentena, marcando-os como REJECTED.
 *
 * Prompts rejeitados permanecem no banco para auditoria mas não são
 * utilizados na resolução de prompts do tenant.
 */
final class RejectQuarantinedPromptAction
{
    /**
     * Rejeita um prompt em quarentena, marcando-o com status REJECTED.
     *
     * @param  AiPromptTenant  $tenantPrompt  Prompt a rejeitar.
     * @return AiPromptTenant Prompt com status atualizado.
     *
     * @throws AiPromptNotQuarantinedException Se o prompt não estiver em quarentena.
     */
    public function execute(AiPromptTenant $tenantPrompt): AiPromptTenant
    {
        if (! $tenantPrompt->isQuarantined()) {
            throw new AiPromptNotQuarantinedException('reject');
        }

        $tenantPrompt->forceFill([
            'validation_status' => AiPromptValidationStatus::REJECTED,
        ])->save();

        return $tenantPrompt->refresh();
    }
}
