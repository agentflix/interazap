# TechSpec: Autopilot Agent Tool Permissions Refactor

## Executive Summary

This technical specification refactors Autopilot tool permissions so the persisted database relationship `ai_agent_tools` becomes the single source of truth for which tools an agent can use. Role-based presets remain available only as setup helpers for the frontend and API. Runtime authorization must never infer tool permissions from role presets.

The primary trade-off is stricter configuration correctness over permissive fallback behavior. An agent with no persisted tools will be blocked as misconfigured instead of silently receiving hardcoded role-based tools.

## System Architecture

### Component Overview

- `ai_autopilot_tools`: tenant-scoped tool catalog. Stores available tool definitions and metadata.
- `ai_agent_tools`: tenant-scoped agent-to-tool permission pivot. This becomes the official runtime permission source.
- `AiPermissionMatrixService`: preset provider only. It returns recommended tool names by role for initial setup.
- `AiAgentController` and `AiAgentSubresourceActions`: read and write agent tool permissions through `ai_agent_tools`.
- `ToolDispatcherService`: builds definitions and authorizes dispatch from persisted agent permissions, not hardcoded role matrices.
- `AutopilotRunSnapshotResolver` and internal AI endpoints: hydrate run snapshots with tools from `ai_agent_tools`.
- Angular tools tab: keeps "Apply Preset" behavior, then saves the final user-edited selection to the backend.

## Implementation Design

### Core Interfaces

```php
interface AgentToolPermissionReader
{
    /** @return list<string> */
    public function toolNamesForAgent(string $tenantId, string $agentId): array;

    public function agentCanUseTool(string $tenantId, string $agentId, string $toolName): bool;
}
```

```php
interface AgentToolPermissionWriter
{
    /** @param list<string> $toolNames */
    public function syncAgentTools(string $tenantId, string $agentId, array $toolNames): void;
}
```

### Data Models

- `ai_autopilot_tools`
  - `id`
  - `tenant_id`
  - `name`
  - `handler_class`
  - `display_name`
  - `description`
  - `parameters_schema`
  - `is_active`

- `ai_agent_tools`
  - `id`
  - `tenant_id`
  - `agent_id`
  - `tool_id`
  - unique: `agent_id + tool_id`

- `ai_agents.metadata`
  - remove `tool_names`
  - preserve all other metadata keys

No new table is required.

### API Endpoints

- `GET /api/ai/tools/catalog`
  - Returns all available tenant tools from catalog/handlers.
  - Does not apply role permissions.

- `GET /api/ai/tools/presets/{role}`
  - Returns hardcoded recommended tool names for setup only.
  - Does not imply runtime authorization.

- `GET /api/ai/agents/{id}/tools`
  - Reads selected tools from `ai_agent_tools`.

- `PUT /api/ai/agents/{id}/tools`
  - Accepts `tool_names`.
  - Resolves names against `ai_autopilot_tools` for the same tenant.
  - Syncs `ai_agent_tools`.
  - Removes `metadata.tool_names` if present.

## Integration Points

### API to Gateway

The API must publish hydrated tool definitions based on `ai_agent_tools`. The gateway receives the resulting tool list and executes only tool calls included in the run context.

### Gateway to API Tool Execution

Gateway tool execution calls API internal tool endpoints. The backend must validate the tool call against persisted `ai_agent_tools` using `tenant_id + agent_id + tool_name`. `agent_role` may remain in context for observability, but not authorization.

## Impact Analysis

| Component | Impact Type | Description and Risk | Required Action |
|-----------|-------------|----------------------|-----------------|
| `AiAgentController` | modified | Currently writes `metadata.tool_names` | Sync `ai_agent_tools` instead |
| `AiAgentSubresourceActions` | modified | Duplicate tool sync behavior | Move to shared action/service |
| `ToolDispatcherService` | modified | Authorizes with hardcoded role matrix | Authorize with DB permissions |
| `AiPermissionMatrixService` | modified | Currently acts like runtime permission source | Keep only as preset provider |
| `AutopilotRunSnapshotResolver` | modified | Reads `metadata.tool_names` | Read `ai_agent_tools` |
| `InternalAiController` | modified | Reads metadata snapshot and dispatches tools | Use DB permission source |
| `DispatchAutopilotRunJob` | modified | Publishes `agent_role`; tools must come from DB | Include DB-backed tool snapshot |
| Angular tools tab | modified/minor | Preset UX is mostly correct | Ensure save writes selected tools only |
| Migration | new | Existing metadata must be migrated | Backfill pivot and remove metadata key |

## Testing Approach

### Unit Tests

- `AiPermissionMatrixServiceTest`
  - verifies presets still return expected setup tool names.
  - verifies presets are not used as runtime fallback.

- `ToolDispatcherServiceTest`
  - allows tools present in `ai_agent_tools`.
  - blocks tools absent from `ai_agent_tools`.
  - blocks execution when agent has no tools.

- New permission reader/writer tests
  - sync replaces old permissions.
  - sync ignores unknown/inactive tools.
  - sync is tenant-isolated.

### Integration Tests

- `AiAgentControllerTest`
  - `PUT /agents/{id}/tools` persists pivot rows.
  - `GET /agents/{id}/tools` reads pivot rows.
  - `metadata.tool_names` is not written.
  - tenant A cannot affect tenant B tools.

- Migration test
  - converts `metadata.tool_names` to `ai_agent_tools`.
  - preserves unrelated metadata.
  - removes only `tool_names`.

- Run hydration test
  - snapshot contains tool definitions from pivot.
  - run blocks when agent has no tools configured.

## Development Sequencing

### Build Order

1. Create agent tool permission reader/writer service - no dependencies.
2. Refactor `AiAgentController` tool endpoints - depends on step 1.
3. Refactor `AiAgentSubresourceActions` to reuse the same service - depends on step 1.
4. Refactor `AutopilotRunSnapshotResolver` and `InternalAiController` to read DB permissions - depends on step 1.
5. Refactor `ToolDispatcherService` runtime authorization to require DB-backed agent permission - depends on step 1.
6. Add migration to backfill `metadata.tool_names` into `ai_agent_tools` and remove the metadata key - depends on the target schema already existing.
7. Update tests for controller, dispatcher, migration, and run hydration - depends on steps 1-6.
8. Run backend gates - depends on all implementation steps.

### Technical Dependencies

- Existing `ai_agent_tools` and `ai_autopilot_tools` tables.
- Existing tenant-scoped `AiAutopilotTool` catalog.
- Existing Redis Stream run flow between API and gateway.

## Monitoring and Observability

- Log blocked runs with:
  - `tenant_id`
  - `agent_id`
  - `run_id`
  - reason: `agent_tools_not_configured`
- Log denied tool execution with:
  - `tenant_id`
  - `agent_id`
  - `tool_name`
  - reason: `tool_not_assigned_to_agent`
- Avoid logging tool payloads that may contain sensitive customer data.

## Technical Considerations

### Key Decisions

- Decision: `ai_agent_tools` is the single source of truth.
- Rationale: it has FK integrity, tenant scope, and already exists.
- Trade-off: requires migration and runtime refactor.
- Rejected: `metadata.tool_names` as official source because it lacks FK integrity.

- Decision: presets remain setup-only.
- Rationale: frontend needs fast agent configuration, but final permissions must be explicit.
- Trade-off: users must save tools before runtime can execute them.
- Rejected: role-based runtime fallback because it hides configuration bugs.

- Decision: agents without tools are blocked.
- Rationale: explicit misconfiguration is safer than silent implicit permission grants.
- Trade-off: existing agents must be migrated correctly.

### Known Risks

- Existing agents with stale `metadata.tool_names` may lose tools if migration cannot resolve names.
  - Mitigation: migration should log unresolved names and preserve unrelated metadata.

- Duplicate controller/action paths may diverge.
  - Mitigation: centralize sync/read logic in one service.

- Gateway may still pass `agent_role`.
  - Mitigation: keep it as context/observability only; do not authorize by it.

## Architecture Decision Records

- [ADR-001: Use `ai_agent_tools` as Agent Tool Permission Source](adrs/adr-001.md) - Persisted pivot rows define runtime permissions; `metadata.tool_names` is removed.
- [ADR-002: Keep Role Presets as Setup Helpers Only](adrs/adr-002.md) - Role presets initialize UI selections but never authorize runtime tools.
- [ADR-003: Block Agents Without Configured Tools](adrs/adr-003.md) - Runs fail explicitly when no tools are assigned.
