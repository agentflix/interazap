<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Actions para Master Prompts.
 */
final class AiPromptMasterActions
{
    /**
     * Listar master prompts com paginação.
     *
     * @return LengthAwarePaginator<int, AiPromptMaster>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptMaster::query()
            ->orderByDesc('version')
            ->paginate($perPage);
    }

    /**
     * Desativar master prompt.
     */
    public function deactivate(AiPromptMaster $master): AiPromptMaster
    {
        $master->update(['is_active' => false]);

        return $master->refresh();
    }
}
