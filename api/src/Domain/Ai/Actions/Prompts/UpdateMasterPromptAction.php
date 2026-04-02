<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\MasterPromptDTO;
use Domain\Ai\Models\AiPromptMaster;

/**
 * Action para atualização de Master Prompts.
 */
final class UpdateMasterPromptAction
{
    public function execute(AiPromptMaster $master, MasterPromptDTO $dto): AiPromptMaster
    {
        $data = $dto->toArray();

        // Incrementa versão se o conteúdo mudou
        if ($data['content'] !== $master->content) {
            $master->incrementVersion();
        }

        $master->update($data);

        return $master->refresh();
    }
}
