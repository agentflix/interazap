<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Enums\AiToolEnum;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\ToolDispatcherService;

describe('ToolDispatcherService allowlist enforcement', function (): void {
    it('blocks tools not allowed for the agent role', function (): void {
        $service = new ToolDispatcherService(new AiPermissionMatrixService);

        $result = $service->dispatch(
            AiToolEnum::UPDATE_LEAD_SCORE,
            [],
            [
                'tenant_id' => 'tenant-1',
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
            ]
        );

        expect($result->success)->toBeFalse();
    });
});
