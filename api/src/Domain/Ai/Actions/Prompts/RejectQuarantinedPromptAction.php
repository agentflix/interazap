<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Exceptions\AiPromptNotQuarantinedException;
use Domain\Ai\Models\AiPromptTenant;

/**
 * Action para rejeição de prompts em quarentena.
 */
final class RejectQuarantinedPromptAction
{
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
