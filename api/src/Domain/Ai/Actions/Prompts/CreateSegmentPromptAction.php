<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\SegmentPromptDTO;
use Domain\Ai\Models\AiPromptSegment;

/**
 * Action para criação de Segment Prompts.
 */
final class CreateSegmentPromptAction
{
    public function execute(SegmentPromptDTO $dto): AiPromptSegment
    {
        return AiPromptSegment::query()->create($dto->toArray());
    }
}
