<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Casos de uso de leitura e desativação de Master Prompts.
 *
 * Contexto: operações administrativas globais sobre o model AiPromptMaster,
 * sem isolamento por tenant (master prompts são globais à plataforma).
 */
final class AiPromptMasterActions
{
    /**
     * Lista master prompts paginados em ordem decrescente de versão.
     *
     * @param  int  $perPage  Itens por página.
     * @return LengthAwarePaginator<int, AiPromptMaster>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptMaster::query()
            ->orderByDesc('version')
            ->paginate($perPage);
    }

    /**
     * Desativa um master prompt definindo is_active = false.
     *
     * @param  AiPromptMaster  $master  Prompt a desativar.
     * @return AiPromptMaster Instância atualizada e refrescada do banco.
     */
    public function deactivate(AiPromptMaster $master): AiPromptMaster
    {
        $master->update(['is_active' => false]);

        return $master->refresh();
    }
}
