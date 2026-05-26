<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\SegmentPromptDTO;
use Domain\Ai\Models\AiPromptSegment;

/**
 * Action para criação de Segment Prompts que definem instruções por nicho/mercado.
 *
 * Segment prompts são herdados pelos tenants vinculados ao segmento
 * e compõem a camada SEGMENT na hierarquia de resolução de prompts.
 */
final class CreateSegmentPromptAction
{
    /**
     * Cria um novo Segment Prompt a partir dos dados do DTO.
     *
     * @param  SegmentPromptDTO  $dto  Dados do novo segment prompt.
     * @return AiPromptSegment Segmento criado.
     */
    public function execute(SegmentPromptDTO $dto): AiPromptSegment
    {
        return AiPromptSegment::query()->create($dto->toArray());
    }
}
