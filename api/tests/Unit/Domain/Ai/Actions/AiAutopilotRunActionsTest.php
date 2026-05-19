<?php

declare(strict_types=1);

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;

describe('AiAutopilotRunActions::resolveMaxTokens', function (): void {
    beforeEach(function (): void {
        $this->actions = new AiAutopilotRunActions(
            aiService: mock(AIServiceInterface::class),
            promptResolver: new AiPromptResolverService,
            toolDispatcher: new ToolDispatcherService(
                agentToolPermissionService: new AiAgentToolPermissionService,
                permissionMatrixService: new AiPermissionMatrixService,
            ),
            guardrailEvaluator: new GuardrailEvaluatorService,
        );

        $this->resolveMaxTokens = function (): int {
            $reflection = new ReflectionClass($this->actions);
            $method = $reflection->getMethod('resolveMaxTokens');

            return $method->invoke($this->actions);
        };
    });

    it('uses env-driven AI_AUTOPILOT_MAX_TOKENS config', function (): void {
        putenv('AI_AUTOPILOT_MAX_TOKENS=950');
        config()->set('ai', require config_path('ai.php'));

        expect(($this->resolveMaxTokens)())->toBe(950);
    });

    it('falls back to default value when env var is not set', function (): void {
        putenv('AI_AUTOPILOT_MAX_TOKENS');
        config()->set('ai', require config_path('ai.php'));

        expect(($this->resolveMaxTokens)())->toBe(800);
    });
    it('uses env-driven AI_AUTOPILOT_MAX_TOOL_ITERATIONS config', function (): void {
        config()->set('ai.autopilot.max_tool_iterations', 7);

        $reflection = new ReflectionClass($this->actions);
        $method = $reflection->getMethod('resolveMaxToolIterations');

        expect($method->invoke($this->actions))->toBe(7);
    });
});
