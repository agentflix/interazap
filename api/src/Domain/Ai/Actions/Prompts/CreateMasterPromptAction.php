<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\MasterPromptDTO;
use Domain\Ai\Models\AiPromptMaster;

/**
 * Action para criação de Master Prompts.
 */
final class CreateMasterPromptAction
{
    public function execute(MasterPromptDTO $dto): AiPromptMaster
    {
        return AiPromptMaster::query()->create($dto->toArray());
    }
}
