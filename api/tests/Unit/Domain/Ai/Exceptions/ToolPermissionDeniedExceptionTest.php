<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Exceptions\ToolPermissionDeniedException;

describe('ToolPermissionDeniedException', function (): void {
    it('creates exception with correct message', function (): void {
        $exception = new ToolPermissionDeniedException(
            role: AiAgentRole::SUPPORT_L1,
            toolName: 'update_lead_score'
        );

        expect($exception->getMessage())
            ->toBe("Role 'support_l1' is not allowed to use tool 'update_lead_score'")
            ->and($exception->getRole())->toBe(AiAgentRole::SUPPORT_L1)
            ->and($exception->getToolName())->toBe('update_lead_score');
    });

    it('returns correct HTTP status code', function (): void {
        $exception = new ToolPermissionDeniedException(
            role: AiAgentRole::POST_SALES,
            toolName: 'move_pipeline'
        );

        expect($exception->getCode())->toBe(403);
    });
});
