# Tasks: Refatorar Permissões de Tools do Autopilot (AUDIT-AI-TOOLS-001)

TechSpec: `.context/FEATURE/autopilot-agent-tool-permissions-techspec.md`
ADRs:
- `.context/FEATURE/adrs/adr-001.md`
- `.context/FEATURE/adrs/adr-002.md`
- `.context/FEATURE/adrs/adr-003.md`

Status geral: 🔄 Em Planejamento | 0/16 tasks concluídas

---

## FASE 3: BACKEND (api/)

> Fonte de verdade definida: `ai_agent_tools`.
> `metadata.tool_names` deve ser migrado e removido.
> `AiPermissionMatrixService` fica restrito a preset/setup, nunca autorização runtime.
> Ordem recomendada: 3.1.1 → 3.2.1 → 3.3.1 → 3.5.1 → 3.3.2 → 3.3.3 → 3.3.4 → 3.3.5 → 3.4.1 → 3.6.1.

---

### 3.1 — Database

- [ ] **TASK-3.1.1** ⏳: Migrar `metadata.tool_names` para `ai_agent_tools`

  **T — Tarefa:** Criar migration idempotente que migra tools salvas em `ai_agents.metadata->tool_names` para o pivot `ai_agent_tools`, preservando outras chaves de metadata e removendo apenas `tool_names`.

  **A — Arquivo:** `api/database/migrations/2026_05_19_091500_migrate_ai_agent_tool_names_metadata_to_agent_tools.php`

  **C — Comportamento:**
  ANTES:
  - Agentes podem ter permissões em `metadata.tool_names`
  - Tabela `ai_agent_tools` existe, mas pode estar divergente ou vazia
  - Runtime ainda pode ignorar o pivot

  DEPOIS:
  - Cada tool resolvida por `(tenant_id, name)` em `ai_autopilot_tools` gera uma linha única em `ai_agent_tools`
  - `metadata.tool_names` é removido de `ai_agents.metadata`
  - Outras chaves de `metadata` permanecem intactas
  - Migration é reexecutável sem duplicar `ai_agent_tools`

  **E — Evidência:**
  - [ ] Teste de migration confirma backfill de `metadata.tool_names` para `ai_agent_tools`
  - [ ] Teste de migration confirma preservação de outras chaves de `metadata`
  - [ ] Teste de migration confirma que nomes inexistentes em `ai_autopilot_tools` não quebram a execução
  - [ ] Teste de isolamento: tools do tenant A não são vinculadas a agentes do tenant B
  - [ ] `cd api && php artisan migrate --pretend` executa sem erro
  - [ ] `down()` documenta comportamento seguro ou rollback viável sem recriar `metadata.tool_names`

  **Status:** ⏳ Pendente

---

### 3.2 — Domain

- [ ] **TASK-3.2.1** ⏳: Adicionar relacionamento de tools ao `AiAgent`

  **T — Tarefa:** Adicionar relacionamento Eloquent `tools()` no model `AiAgent` apontando para `AiAutopilotTool` via pivot `ai_agent_tools`.

  **A — Arquivo:** `api/src/Domain/Ai/Models/AiAgent.php`

  **C — Comportamento:**
  ANTES:
  - `AiAgent` não expõe relacionamento com `ai_autopilot_tools`
  - Código precisa ler `metadata.tool_names` ou montar queries manuais

  DEPOIS:
  - `AiAgent::tools()` retorna tools do agente via pivot `ai_agent_tools`
  - Relacionamento usa pivot com `tenant_id`, `agent_id`, `tool_id` e timestamps
  - Model mantém `BelongsToTenant`

  **E — Evidência:**
  - [ ] Teste unitário confirma que `AiAgent::tools()` retorna apenas tools vinculadas ao agente
  - [ ] Teste de isolamento: query do tenant A não retorna dados do tenant B
  - [ ] Trait `BelongsToTenant` permanece aplicado ao model
  - [ ] `cd api && composer analyse` retorna 0 erros para o arquivo alterado

  **Status:** ⏳ Pendente

---

### 3.3 — Application / Services

- [ ] **TASK-3.3.1** ⏳: Criar serviço de permissões de tools por agente

  **T — Tarefa:** Criar serviço `AiAgentToolPermissionService` para ler, sincronizar e validar permissões reais de tools usando `ai_agent_tools`.

  **A — Arquivo:** `api/src/Domain/Ai/Services/AiAgentToolPermissionService.php`

  **C — Comportamento:**
  ANTES:
  - Leitura de tools está espalhada entre `metadata.tool_names`, matriz hardcoded e consultas manuais
  - Não existe ponto único para sincronizar ou validar permissões persistidas

  DEPOIS:
  - Serviço expõe métodos para listar nomes de tools por agente, sincronizar tools por nome e validar se agente pode usar uma tool
  - Todas as queries são filtradas por `tenant_id`
  - Serviço resolve apenas tools existentes e ativas em `ai_autopilot_tools`
  - Serviço não usa `AiPermissionMatrixService` para autorização

  **E — Evidência:**
  - [ ] Teste unitário/feature confirma que `syncAgentTools()` substitui permissões antigas pelas novas
  - [ ] Teste confirma que tool inexistente ou inativa não é vinculada
  - [ ] Teste confirma que `agentCanUseTool()` retorna false para tool não vinculada
  - [ ] Teste de isolamento: query do tenant A não retorna dados do tenant B
  - [ ] `cd api && composer analyse` retorna 0 erros

  **Status:** ⏳ Pendente

- [ ] **TASK-3.3.2** ⏳: Refatorar `ToolDispatcherService` para autorização por banco

  **T — Tarefa:** Alterar `ToolDispatcherService` para construir definitions e autorizar dispatch usando permissões persistidas em `ai_agent_tools`, exigindo `agent_id` no contexto runtime.

  **A — Arquivo:** `api/src/Domain/Ai/Services/ToolDispatcherService.php`

  **C — Comportamento:**
  ANTES:
  - `getToolDefinitions()` usa `AiPermissionMatrixService` quando `selectedTools` está vazio
  - `dispatch()` valida tool por `agent_role` e matriz hardcoded

  DEPOIS:
  - Runtime usa tools retornadas por `AiAgentToolPermissionService`
  - `dispatch()` valida `tenant_id`, `agent_id` e `tool_name` contra `ai_agent_tools`
  - Ausência de `agent_id` ou ausência de tools retorna falha controlada
  - `AiPermissionMatrixService` não é usado para autorização runtime

  **E — Evidência:**
  - [ ] `ToolDispatcherServiceTest` permite dispatch de tool vinculada ao agente
  - [ ] `ToolDispatcherServiceTest` bloqueia tool existente mas não vinculada ao agente
  - [ ] `ToolDispatcherServiceTest` bloqueia execução sem `agent_id`
  - [ ] `ToolDispatcherServiceTest` bloqueia agente sem tools com razão `agent_tools_not_configured`
  - [ ] `cd api && composer test -- --filter=ToolDispatcherServiceTest` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-3.3.3** ⏳: Hidratar snapshots de run com tools do pivot

  **T — Tarefa:** Alterar `AutopilotRunSnapshotResolver` para montar tool definitions a partir de `ai_agent_tools`, sem ler `metadata.tool_names`.

  **A — Arquivo:** `api/src/Domain/Ai/Services/AutopilotRunSnapshotResolver.php`

  **C — Comportamento:**
  ANTES:
  - Snapshot lê `metadata.tool_names`
  - Se metadata divergir do pivot, a run recebe tools incorretas

  DEPOIS:
  - Snapshot lê permissões pelo `AiAgentToolPermissionService`
  - Run recebe apenas definitions das tools vinculadas no banco
  - Agente sem tools gera snapshot bloqueado/falha controlada conforme contrato da feature

  **E — Evidência:**
  - [ ] Teste confirma que snapshot inclui definition de tool presente em `ai_agent_tools`
  - [ ] Teste confirma que snapshot não inclui tool presente apenas em `metadata.tool_names`
  - [ ] Teste confirma bloqueio ou falha controlada para agente sem tools
  - [ ] `cd api && composer test -- --filter=AutopilotRunSnapshotResolver` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-3.3.4** ⏳: Remover fallback runtime por role no executor legado

  **T — Tarefa:** Ajustar `AiAutopilotRunActions` para não pedir definitions por `agent_role` e para executar tools somente quando o contexto tiver `agent_id` com permissões persistidas.

  **A — Arquivo:** `api/src/Domain/Ai/Actions/AiAutopilotRunActions.php`

  **C — Comportamento:**
  ANTES:
  - Executor legado chama `getToolDefinitions($tenantId, $agentRole)`
  - Tool dispatch recebe `agent_role` como base de autorização

  DEPOIS:
  - Executor legado usa `agent_id` e permissões em `ai_agent_tools`
  - `agent_role` pode permanecer no contexto apenas para observabilidade
  - Sem tools persistidas, execução retorna estado bloqueado/falha controlada

  **E — Evidência:**
  - [ ] Teste existente de `AiAutopilotRunActions` passa após remover dependência de role runtime
  - [ ] Novo teste confirma que executor legado não expõe tool não vinculada ao agente
  - [ ] Novo teste confirma falha controlada quando `agent_id` não está no contexto
  - [ ] `cd api && composer test -- --filter=AiAutopilotRunActions` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-3.3.5** ⏳: Atualizar delegação para preservar permissões do agente alvo

  **T — Tarefa:** Ajustar serviço de delegação para registrar `agent_id` do agente alvo e depender das tools vinculadas ao alvo no banco, sem usar `$agent->role` para permissões.

  **A — Arquivo:** `api/src/Domain/Ai/Services/AiAgentDelegationService.php`

  **C — Comportamento:**
  ANTES:
  - Delegação registra `agent_role` a partir de atributo inexistente `role`
  - Permissões podem cair em fallback hardcoded

  DEPOIS:
  - Delegação preserva `agent_id` do agente alvo para validação por `ai_agent_tools`
  - `agent_role` deixa de influenciar autorização de tool
  - Logs/contexto não usam `$agent->role` inexistente

  **E — Evidência:**
  - [ ] `AiAgentDelegationServiceTest` confirma que delegação usa `agent_id` do alvo
  - [ ] Teste confirma que tool não vinculada ao agente alvo é bloqueada
  - [ ] `rg "\\$agent->role|getAttribute\\('role'\\)" api/src/Domain/Ai` não encontra uso em fluxo de permissão runtime
  - [ ] `cd api && composer test -- --filter=AiAgentDelegationServiceTest` passa

  **Status:** ⏳ Pendente

---

### 3.4 — Infrastructure / Jobs / Streams

- [ ] **TASK-3.4.1** ⏳: Publicar runs com snapshot de tools baseado no banco

  **T — Tarefa:** Ajustar `DispatchAutopilotRunJob` para publicar `agent_id` e tool snapshot hidratado por `ai_agent_tools`, mantendo `agent_role` apenas como campo informativo quando existir.

  **A — Arquivo:** `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php`

  **C — Comportamento:**
  ANTES:
  - Job publica `agent_role` usando `metadata.role` ou atributo inexistente `role`
  - Permissão efetiva pode ser recalculada por role no consumo

  DEPOIS:
  - Job publica `agent_id` como identificador obrigatório de autorização
  - Tools publicadas vêm do snapshot DB-backed
  - `agent_role` não é necessário para autorização

  **E — Evidência:**
  - [ ] `DispatchAutopilotRunJob` inclui `agent_id` no payload de run
  - [ ] Teste confirma que payload não depende de `$agent->role`
  - [ ] Teste confirma que agente sem tools gera falha/bloqueio explícito antes de expor fallback
  - [ ] `cd api && composer test -- --filter=DispatchAutopilotRunJob` passa

  **Status:** ⏳ Pendente

---

### 3.5 — HTTP

- [ ] **TASK-3.5.1** ⏳: Refatorar endpoints públicos de tools do agente

  **T — Tarefa:** Alterar endpoints `GET/PUT /api/ai/agents/{id}/tools` para ler e sincronizar `ai_agent_tools` via `AiAgentToolPermissionService`.

  **A — Arquivo:** `api/src/Domain/Ai/Http/Controllers/AiAgentController.php`

  **C — Comportamento:**
  ANTES:
  - `GET /agents/{id}/tools` lê `metadata.tool_names`
  - `PUT /agents/{id}/tools` salva `metadata.tool_names`
  - Catálogo disponível pode ser filtrado por `$agent->role` inexistente

  DEPOIS:
  - `GET /agents/{id}/tools` retorna vínculos de `ai_agent_tools`
  - `PUT /agents/{id}/tools` sincroniza o pivot e remove `metadata.tool_names`
  - Presets seguem disponíveis apenas em `/tools/presets/{role}`
  - Seleção manual do usuário é preservada como permissão final

  **E — Evidência:**
  - [ ] `AiAgentControllerTest` confirma que `PUT /agents/{id}/tools` cria linhas em `ai_agent_tools`
  - [ ] `AiAgentControllerTest` confirma que `GET /agents/{id}/tools` lê do pivot
  - [ ] `AiAgentControllerTest` confirma que `metadata.tool_names` não é escrito
  - [ ] Teste de isolamento: tenant A não altera tools de agente do tenant B
  - [ ] `cd api && composer test -- --filter=AiAgentControllerTest` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-3.5.2** ⏳: Refatorar actions duplicadas de subresources

  **T — Tarefa:** Alterar `AiAgentSubresourceActions` para reutilizar `AiAgentToolPermissionService` nos métodos de listagem e atualização de tools.

  **A — Arquivo:** `api/src/Domain/Ai/Actions/AiAgentSubresourceActions.php`

  **C — Comportamento:**
  ANTES:
  - Action duplica leitura e escrita de `metadata.tool_names`
  - Action usa catálogo filtrado por `$agent->role`

  DEPOIS:
  - Action usa o mesmo serviço de permissão que o controller principal
  - Não há gravação de `metadata.tool_names`
  - Não há dependência de `$agent->role` para listar ou salvar tools

  **E — Evidência:**
  - [ ] Teste de subresource confirma listagem via `ai_agent_tools`
  - [ ] Teste de subresource confirma sync substitutivo no pivot
  - [ ] `rg "metadata\\['tool_names'\\]|data_get\\(\\$metadata, 'tool_names'\\)" api/src/Domain/Ai/Actions api/src/Domain/Ai/Http` não encontra usos ativos
  - [ ] `cd api && composer analyse` retorna 0 erros

  **Status:** ⏳ Pendente

- [ ] **TASK-3.5.3** ⏳: Refatorar endpoints internos de tools

  **T — Tarefa:** Alterar `InternalAiController` para expor definitions e executar tools validando `tenant_id`, `agent_id` e `tool_name` via `ai_agent_tools`.

  **A — Arquivo:** `api/src/Domain/Ai/Http/Controllers/InternalAiController.php`

  **C — Comportamento:**
  ANTES:
  - Endpoint interno de tools lê `metadata.tool_names`
  - `executeTool()` delega autorização por role no dispatcher

  DEPOIS:
  - Endpoint interno de tools lê permissões do pivot
  - `executeTool()` exige `context.agent_id`
  - Tool não atribuída ao agente retorna falha controlada `tool_not_assigned_to_agent`

  **E — Evidência:**
  - [ ] Teste confirma que `/internal/ai/tools/{agentId}` retorna apenas tools vinculadas no pivot
  - [ ] Teste confirma que `/internal/ai/tool/{toolName}` bloqueia tool sem vínculo no pivot
  - [ ] Teste confirma que chamada sem `context.agent_id` falha sem executar handler
  - [ ] `cd api && composer test -- --filter=InternalAiController` passa

  **Status:** ⏳ Pendente

---

### 3.6 — Testes e Qualidade Backend

- [ ] **TASK-3.6.1** ⏳: Consolidar testes backend da refatoração de tools

  **T — Tarefa:** Atualizar e adicionar testes Pest cobrindo migration, service, controller, dispatcher, snapshot, job, internal endpoints e isolamento multi-tenant.

  **A — Arquivo:** `api/tests/Feature/AiAgentControllerTest.php`

  **C — Comportamento:**
  ANTES:
  - Testes esperam persistência em `metadata.tool_names`
  - Testes de dispatcher cobrem allowlist por role

  DEPOIS:
  - Testes esperam persistência em `ai_agent_tools`
  - Testes comprovam que presets não autorizam runtime
  - Testes comprovam bloqueio de agente sem tools

  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter='AiAgentController|ToolDispatcherService|AutopilotRunSnapshotResolver|DispatchAutopilotRunJob|InternalAiController|AiAgentDelegationService'` passa
  - [ ] `cd api && composer analyse` retorna 0 erros
  - [ ] Teste de isolamento: query do tenant A não retorna dados do tenant B
  - [ ] Antes do gate final, executar code review com `.context/SKILLS/code-review-confiavel/`

  **Status:** ⏳ Pendente

---

### Revisão de Fase 3 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| `ai_agent_tools` é a única fonte runtime de permissões | @REVIEWER | ⏳ |
| `metadata.tool_names` foi migrado e removido do fluxo ativo | @REVIEWER | ⏳ |
| Presets não são usados para autorização runtime | @REVIEWER | ⏳ |
| Testes de isolamento multi-tenant cobrem leitura e sync de tools | @QA | ⏳ |
| Code review confiável executado antes do gate final | @REVIEWER | ⏳ |
| Gates passam | @QA | ⏳ |

**Gate de Qualidade Fase 3:** ⏳ Pendente — `cd api && composer gate:all 2>&1`

---

## FASE 5: FRONTEND (app/)

> O frontend mantém o select de preset como setup inicial. A permissão final é a seleção salva pelo usuário.

---

### 5.1 — Tools Tab

- [ ] **TASK-5.1.1** ⏳: Alinhar presets de tools com fonte persistida no backend

  **T — Tarefa:** Ajustar a aba de tools do agente para deixar o preset como preenchimento inicial editável e garantir que o save envie somente a seleção final de `tool_names`.

  **A — Arquivo:** `app/src/app/pages/ai/pages/agents/agent-workspace/tabs/tools-tab.ts`

  **C — Comportamento:**
  ANTES:
  - Preset já adiciona tools à seleção atual
  - Opções de preset incluem valores que podem não existir no backend (`finance`, `routing`)
  - Não há teste explícito garantindo que preset não substitui seleção manual

  DEPOIS:
  - Opções de preset refletem presets suportados pela API
  - Aplicar preset adiciona sugestões sem impedir remoção manual
  - Save global envia a lista final editada pelo usuário para `PUT /agents/{id}/tools`

  **E — Evidência:**
  - [ ] Vitest confirma que `applyPreset()` mescla sugestões sem apagar tools já selecionadas
  - [ ] Vitest confirma que tool removida manualmente não volta ao salvar sem reaplicar preset
  - [ ] Vitest confirma que `updateAgentTools()` recebe a lista final de `linkedToolNames()`
  - [ ] `cd app && pnpm --filter app test -- --run tools-tab` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-5.1.2** ⏳: Atualizar tipos do modelo de AI Agent

  **T — Tarefa:** Ajustar tipos TypeScript de roles/presets e tool links para refletir que `role` não é permissão runtime e que tools vinculadas vêm do backend.

  **A — Arquivo:** `app/src/app/pages/ai/models/ai.model.ts`

  **C — Comportamento:**
  ANTES:
  - `AiAgentRole` mistura presets válidos com valores sem suporte confirmado no backend
  - Tipos podem sugerir que role controla permissão de tool

  DEPOIS:
  - Tipo de preset representa somente valores suportados pela API de presets
  - Comentários/modelos deixam claro que permissão final vem de tools vinculadas
  - `AiAgentPayload` permanece sem `role` de autorização runtime

  **E — Evidência:**
  - [ ] `pnpm --filter app build` não apresenta erro de TypeScript
  - [ ] `pnpm --filter app lint` retorna 0 warnings
  - [ ] Busca por uso de role em AI Agent não encontra autorização frontend baseada em role

  **Status:** ⏳ Pendente

---

### Revisão de Fase 5 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Preset é UX de setup, não permissão final | @REVIEWER | ⏳ |
| Usuário consegue adicionar/remover tools após aplicar preset | @QA | ⏳ |
| Code review confiável executado antes do gate final | @REVIEWER | ⏳ |
| Gates passam | @QA | ⏳ |

**Gate de Qualidade Fase 5:** ⏳ Pendente — `cd app && pnpm --filter app lint && pnpm --filter app test && pnpm --filter app build`

---

## FASE 6: INTEGRAÇÃO E VALIDAÇÃO

---

### 6.1 — API ↔ Gateway

- [ ] **TASK-6.1.1** ⏳: Validar contrato de execução de tool com `agent_id`

  **T — Tarefa:** Validar que o fluxo API → Gateway → API carrega `agent_id` e tool snapshot DB-backed, e que chamadas de tool sem permissão persistida são bloqueadas.

  **A — Arquivo:** `gateway/src/domains/ai/services/tool-executor.service.ts`

  **C — Comportamento:**
  ANTES:
  - Gateway repassa `agent_role` no contexto de tool
  - API pode autorizar com matriz hardcoded por role

  DEPOIS:
  - Gateway continua repassando `agent_id` no contexto
  - `agent_role` permanece apenas observabilidade, se presente
  - API bloqueia tool sem vínculo em `ai_agent_tools`

  **E — Evidência:**
  - [ ] Teste Jest confirma que `ToolExecutorService` envia `agent_id` no contexto
  - [ ] Teste integrado/focado confirma que tool sem vínculo retorna falha controlada
  - [ ] `cd gateway && pnpm --filter gateway test -- --runInBand tool-executor` passa
  - [ ] `cd api && composer test -- --filter=ConsumeToolRequestsCommand` passa

  **Status:** ⏳ Pendente

- [ ] **TASK-6.1.2** ⏳: Validar run bloqueada para agente sem tools

  **T — Tarefa:** Criar validação integrada garantindo que agente sem linhas em `ai_agent_tools` não recebe fallback de preset e a run registra bloqueio explícito.

  **A — Arquivo:** `api/tests/Feature/Ai/AutopilotAgentToolsRuntimeTest.php`

  **C — Comportamento:**
  ANTES:
  - Agente sem tools pode cair em preset hardcoded por role
  - Falha de configuração fica invisível

  DEPOIS:
  - Agente sem tools gera falha/bloqueio com razão `agent_tools_not_configured`
  - Nenhuma tool definition é exposta ao gateway
  - Logs/contexto incluem `tenant_id`, `agent_id`, `run_id` sem payload sensível

  **E — Evidência:**
  - [ ] Teste confirma status bloqueado/falha controlada para agente sem tools
  - [ ] Teste confirma ausência de fallback por `sales_qualifier`, `support_l1` ou `general`
  - [ ] Teste confirma presença de `agent_tools_not_configured`
  - [ ] `cd api && composer test -- --filter=AutopilotAgentToolsRuntimeTest` passa

  **Status:** ⏳ Pendente

---

### Revisão de Fase 6 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Contrato API ↔ Gateway usa `agent_id` para autorização | @REVIEWER | ⏳ |
| Runtime não usa role/preset como fallback de permissão | @REVIEWER | ⏳ |
| Agente sem tools bloqueia de forma auditável | @QA | ⏳ |
| Gates backend e gateway passam | @QA | ⏳ |

**Gate de Qualidade Fase 6:** ⏳ Pendente — `cd api && composer gate:all 2>&1` + `cd gateway && pnpm --filter gateway lint && pnpm --filter gateway test && pnpm --filter gateway build`

---

## Progresso Geral

| Fase | Tasks | Concluídas | Gate |
|------|-------|------------|------|
| 3 — Backend | 12 | 0 | ⏳ |
| 5 — Frontend | 2 | 0 | ⏳ |
| 6 — Integração | 2 | 0 | ⏳ |
| **Total** | **16** | **0** | ⏳ |
