<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptSegment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Actions para Segment Prompts.
 */
final class AiPromptSegmentActions
{
    /**
     * Listar segment prompts com paginação.
     *
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
     * Carregar relações necessárias do segmento.
     */
    public function loadWithMaster(AiPromptSegment $segment): AiPromptSegment
    {
        return $segment->load('master');
    }

    /**
     * Remover um segment prompt.
     */
    public function delete(AiPromptSegment $segment): void
    {
        $segment->delete();
    }
}
