<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Enums\AiAgentRole;
use Illuminate\Support\Str;

/**
 * Service responsible for exposing and dispatching Autopilot tools.
 */
final class ToolDispatcherService
{
    /** @var array<string, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}> */
    private static array $definitionCache = [];

    public function __construct(private readonly AiPermissionMatrixService $permissionMatrixService) {}

    /**
     * Build tenant tool definitions for LLM function calling.
     *
     * @param  list<string>|null  $selectedTools
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function getToolDefinitions(string $tenantId, ?string $agentRole = null, ?array $selectedTools = null): array
    {
        $toolNames = $this->resolveToolNames($agentRole, $selectedTools);

        return collect($toolNames)
            ->map(function (string $toolName): ?array {
                // M-03: Cache estático — descriptions e parameters não mudam em runtime
                if (isset(self::$definitionCache[$toolName])) {
                    return self::$definitionCache[$toolName];
                }

                $className = $this->resolveClassNameFromToolName($toolName);

                if (! class_exists($className)) {
                    return null;
                }

                $description = $this->humanizeToolName($toolName);
                $parameters = ['type' => 'object', 'properties' => new \stdClass];

                try {
                    $handler = app()->make($className);

                    if ($handler instanceof AiToolInterface) {
                        $description = $handler->getDescription();
                        $params = $handler->getParameters();

                        if ($params !== []) {
                            $parameters = [
                                'type' => 'object',
                                'properties' => $this->convertParametersToOpenAiSchema($params),
                                'required' => collect($params)
                                    ->filter(fn (array $p): bool => $p['required'] ?? false)
                                    ->keys()
                                    ->values()
                                    ->all(),
                            ];
                        }
                    }
                } catch (\Throwable) {
                    // Fallback: keep humanized description and empty parameters
                }

                $definition = [
                    'type' => 'function',
                    'function' => [
                        'name' => $toolName,
                        'description' => $description,
                        'parameters' => $parameters,
                    ],
                ];

                self::$definitionCache[$toolName] = $definition;

                return $definition;
            })
            ->filter(fn (?array $definition): bool => $definition !== null)
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, display_name: string, description: string, handler_class: string, is_active: bool}>
     */
    public function getCatalog(?string $agentRole = null): array
    {
        $toolNames = $this->resolveToolNames($agentRole, null);

        return collect($toolNames)
            ->map(function (string $toolName): ?array {
                $className = $this->resolveClassNameFromToolName($toolName);

                if (! class_exists($className)) {
                    return null;
                }

                return [
                    'name' => $toolName,
                    'display_name' => $this->humanizeToolName($toolName),
                    'description' => $this->humanizeToolName($toolName),
                    'handler_class' => $className,
                    'is_active' => true,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->values()
            ->all();
    }

    /**
     * Dispatch an individual tool call.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $context
     */
    public function dispatch(string $toolName, array $parameters, array $context): ToolResultDTO
    {
        if ((string) ($context['tenant_id'] ?? '') === '') {
            return ToolResultDTO::failure('Tenant context not informed.');
        }

        $roleValue = (string) ($context['agent_role'] ?? AiAgentRole::GENERAL->value);
        $role = AiAgentRole::tryFrom($roleValue) ?? AiAgentRole::GENERAL;
        $allowedTools = $this->permissionMatrixService->getAvailableTools($role);
        if (! in_array($toolName, $allowedTools, true)) {
            return ToolResultDTO::failure("Tool '{$toolName}' not allowed for role '{$role->value}'.");
        }

        $className = $this->resolveClassNameFromToolName($toolName);
        if (! class_exists($className)) {
            return ToolResultDTO::failure("Handler class for tool '{$toolName}' not implemented.");
        }

        $handler = app()->make($className);
        if (! $handler instanceof AiToolInterface) {
            return ToolResultDTO::failure("Handler '{$className}' must implement AiToolInterface.");
        }

        return $handler->handle(new ToolInputDTO(
            toolName: $toolName,
            parameters: $parameters,
            context: $context,
        ));
    }

    /**
     * @param  list<string>|null  $selectedTools
     * @return list<string>
     */
    private function resolveToolNames(?string $agentRole, ?array $selectedTools): array
    {
        if ($selectedTools === null || $selectedTools === []) {
            $role = AiAgentRole::tryFrom((string) ($agentRole ?? AiAgentRole::GENERAL->value))
                ?? AiAgentRole::GENERAL;

            return array_values(array_unique($this->permissionMatrixService->getAvailableTools($role)));
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $tool): string => trim((string) $tool),
            $selectedTools,
        ))));
    }

    private function resolveClassNameFromToolName(string $toolName): string
    {
        $classBasename = Str::studly($toolName).'Tool';

        return "Domain\\Ai\\Tools\\{$classBasename}";
    }

    private function humanizeToolName(string $toolName): string
    {
        return Str::headline($toolName);
    }

    /**
     * Convert tool parameters to OpenAI function calling schema format.
     *
     * @param  array<string, array<string, mixed>>  $params
     * @return array<string, array<string, mixed>>
     */
    private function convertParametersToOpenAiSchema(array $params): array
    {
        $properties = [];

        foreach ($params as $name => $definition) {
            $propertyDefinition = $definition;
            unset($propertyDefinition['required']);

            if (! array_key_exists('description', $propertyDefinition)) {
                $propertyDefinition['description'] = '';
            }

            $properties[$name] = $propertyDefinition;
        }

        return $properties;
    }
}
