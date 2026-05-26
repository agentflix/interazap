<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptSegment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Casos de uso de leitura e remoção de Segment Prompts.
 *
 * Contexto: operações administrativas globais sobre AiPromptSegment,
 * que definem instruções de nicho/mercado herdadas pelos tenants do segmento.
 */
final class AiPromptSegmentActions
{
    /**
     * Lista segment prompts paginados com relação master carregada, ordenados por code.
     *
     * @param  int  $perPage  Itens por página.
     * @return LengthAwarePaginator<int, AiPromptSegment>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptSegment::query()
            ->with('master')
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * Carrega a relação master de um segmento.
     *
     * @param  AiPromptSegment  $segment  Segmento a hidratar.
     * @return AiPromptSegment Segmento com relação master carregada.
     */
    public function loadWithMaster(AiPromptSegment $segment): AiPromptSegment
    {
        return $segment->load('master');
    }

    /**
     * Remove permanentemente um segment prompt.
     *
     * @param  AiPromptSegment  $segment  Segmento a remover.
     */
    public function delete(AiPromptSegment $segment): void
    {
        $segment->delete();
    }
}
