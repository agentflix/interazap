<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\SegmentPromptDTO;
use Domain\Ai\Models\AiPromptSegment;

/**
 * Action para atualização de Segment Prompts.
 */
final class UpdateSegmentPromptAction
{
    public function execute(AiPromptSegment $segment, SegmentPromptDTO $dto): AiPromptSegment
    {
        $data = $dto->toArray();

        // Remove code do update se for GENERAL (não pode mudar)
        if ($segment->isGeneral()) {
            unset($data['code']);
        }

        $segment->update($data);

        return $segment->refresh();
    }
}
