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
 * Serviço responsável por expor e despachar tools do Autopilot.
 *
 * Usa permissões baseadas em banco de dados (ai_agent_tools) para
 * autorização em vez de matriz hardcoded por role. AiPermissionMatrixService
 * é mantido apenas para fallback legacy de presets.
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
     * Constrói as definições de tools para function calling do LLM.
     *
     * Quando agentId é informado, usa AiAgentToolPermissionService para
     * obter os tools do banco. Faz fallback para matriz baseada em role
     * para chamadores legacy quando agentId não é fornecido.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string|null  $agentId  UUID do agente (opcional para compatibilidade).
     * @param  list<string>|null  $selectedTools  Filtro explícito de tools.
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
     * Despacha uma chamada individual de tool.
     *
     * Valida tenant_id, agent_id e tool_name contra ai_agent_tools.
     *
     * @param  string  $toolName  Nome da tool a executar.
     * @param  array<string, mixed>  $parameters  Parâmetros da tool.
     * @param  array<string, mixed>  $context  Contexto de execução (deve conter tenant_id e agent_id).
     * @return ToolResultDTO Resultado da execução.
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
     * Retorna todas as tools ativas de ai_autopilot_tools que possuem
     * handler class existente, sem filtragem por role.
     *
     * @param  string  $tenantId  UUID do tenant.
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
     * Resolve os nomes das tools com base no agentId ou faz fallback legacy.
     *
     * @param  list<string>|null  $selectedTools
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
     * Retorna os nomes de todas as tools ativas do tenant no banco.
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

    /** Resolve o nome da classe handler a partir do nome da tool. */
    private function resolveClassNameFromToolName(string $toolName): string
    {
        $classBasename = Str::studly($toolName).'Tool';

        return "Domain\\Ai\\Tools\\{$classBasename}";
    }

    /** Converte o nome da tool para formato legível (headline case). */
    private function humanizeToolName(string $toolName): string
    {
        return Str::headline($toolName);
    }

    /**
     * Converte parâmetros de tool para o schema de function calling da OpenAI.
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
