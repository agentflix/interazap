<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiAgentToolPermissionServiceInterface;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Models\AiAutopilotTool;
use Illuminate\Support\Str;

/**
 * Service responsible for exposing and dispatching Autopilot tools.
 *
 * Uses database-backed permissions (ai_agent_tools) for authorization
 * instead of hardcoded role-based matrix. AiPermissionMatrixService is
 * retained only for legacy preset fallbacks.
 *
 * @category Services
 */
final class ToolDispatcherService
{
    /** @var array<string, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}> */
    private static array $definitionCache = [];

    public function __construct(
        private readonly AiAgentToolPermissionServiceInterface $agentToolPermissionService,
        private readonly AiPermissionMatrixService $permissionMatrixService,
    ) {}

    /**
     * Build tenant tool definitions for LLM function calling.
     *
     * When agentId is informed, uses AiAgentToolPermissionService to obtain
     * tool names from the database. Falls back to role-based matrix for
     * legacy callers when agentId is not provided.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string|null  $agentId  UUID do agente (optional for backward compatibility)
     * @param  list<string>|null  $selectedTools  Explicit tool filter
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function getToolDefinitions(string $tenantId, ?string $agentId = null, ?array $selectedTools = null): array
    {
        $toolNames = $this->resolveToolNames($tenantId, $agentId, $selectedTools);

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
     * Dispatch an individual tool call.
     *
     * Validates tenant_id, agent_id and tool_name against ai_agent_tools.
     *
     * @param  string  $toolName  Nome da tool a executar
     * @param  array<string, mixed>  $parameters  Parâmetros da tool
     * @param  array<string, mixed>  $context  Contexto de execução (deve conter tenant_id e agent_id)
     */
    public function dispatch(string $toolName, array $parameters, array $context): ToolResultDTO
    {
        $tenantId = (string) ($context['tenant_id'] ?? '');
        if ($tenantId === '') {
            return ToolResultDTO::failure('Tenant context not informed.');
        }

        $agentId = (string) ($context['agent_id'] ?? '');
        if ($agentId === '') {
            return ToolResultDTO::failure('Agent context not informed.');
        }

        // Primary check: single query verifies tool assignment + active status.
        if (! $this->agentToolPermissionService->agentCanUseTool($tenantId, $agentId, $toolName)) {
            // Only when the primary check fails, query tool names to differentiate reasons.
            $agentToolNames = $this->agentToolPermissionService->toolNamesForAgent($tenantId, $agentId);
            if ($agentToolNames === []) {
                return ToolResultDTO::failure(
                    'Agent tools not configured.',
                    ['reason' => 'agent_tools_not_configured'],
                );
            }

            return ToolResultDTO::failure(
                "Tool '{$toolName}' not assigned to agent.",
                ['reason' => 'tool_not_assigned_to_agent'],
            );
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
     * Retorna catálogo completo de tools ativas do tenant.
     *
     * Returns all active tools from ai_autopilot_tools that have an
     * existing handler class, without role-based filtering.
     *
     * @param  string  $tenantId  UUID do tenant
     * @return list<array{name: string, display_name: string, description: string, handler_class: string, is_active: bool}>
     */
    public function getCatalog(string $tenantId): array
    {
        $toolNames = $this->getActiveToolNamesFromDb($tenantId);

        return collect($toolNames)
            ->map(function (string $toolName): ?array {
                $className = $this->resolveClassNameFromToolName($toolName);

                if (! class_exists($className)) {
                    return null;
                }

                $description = $this->humanizeToolName($toolName);

                try {
                    $handler = app()->make($className);
                    if ($handler instanceof AiToolInterface) {
                        $description = $handler->getDescription();
                    }
                } catch (\Throwable) {
                    // Keep humanized description as fallback
                }

                return [
                    'name' => $toolName,
                    'display_name' => $this->humanizeToolName($toolName),
                    'description' => $description,
                    'handler_class' => $className,
                    'is_active' => true,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->values()
            ->all();
    }

    /**
     * Resolve tool names based on agentId or falls back to legacy behavior.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string|null  $agentId  UUID do agente
     * @param  list<string>|null  $selectedTools  Explicit tool filter
     * @return list<string>
     */
    private function resolveToolNames(string $tenantId, ?string $agentId, ?array $selectedTools): array
    {
        // Explicit filter takes precedence
        if ($selectedTools !== null && $selectedTools !== []) {
            return array_values(array_unique(array_filter(array_map(
                static fn (mixed $tool): string => trim((string) $tool),
                $selectedTools,
            ))));
        }

        // Database-backed permissions when agentId is provided
        if ($agentId !== null) {
            return $this->agentToolPermissionService->toolNamesForAgent($tenantId, $agentId);
        }

        // Legacy fallback: role-based matrix
        return array_values(array_unique($this->permissionMatrixService->getAllToolNames()));
    }

    /**
     * Get all active tool names from the database for a tenant.
     *
     * @return list<string>
     */
    private function getActiveToolNamesFromDb(string $tenantId): array
    {
        /** @var list<string> $names */
        $names = AiAutopilotTool::forTenant($tenantId)
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        return $names;
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
