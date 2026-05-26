<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\MasterPromptDTO;
use Domain\Ai\Models\AiPromptMaster;

/**
 * Action para criação de Master Prompts globais à plataforma.
 *
 * Master prompts são a camada base da hierarquia de prompts,
 * herdados por todos os tenants que não possuem customização.
 */
final class CreateMasterPromptAction
{
    /**
     * Cria um novo Master Prompt a partir dos dados do DTO.
     *
     * @param  MasterPromptDTO  $dto  Dados do novo master prompt.
     * @return AiPromptMaster Prompt criado.
     */
    public function execute(MasterPromptDTO $dto): AiPromptMaster
    {
        return AiPromptMaster::query()->create($dto->toArray());
    }
}
