# PLAN-030-auditoria-modulo-autopilot — Auditoria de Erros, Performance e Token Economy do Módulo Autopilot

## Objetivo

Realizar uma auditoria completa do módulo Autopilot (AI) em todas as 3 camadas (API Laravel, Gateway NestJS) buscando:

- Bugs e erros lógicos
- Problemas de performance (N+1, queries desnecessárias, memory bloat)
- Desperdício de tokens/fluxo (payloads oversized, chamadas LLM redundantes, contexto duplicado)
- Oportunidades de refatoração (god classes, DRY violations, acoplamento)
- Gaps de segurança (tenant isolation, authorization, injection)

Corrigir todos os achados classificados como CRITICAL e HIGH. Achados MEDIUM ficam como follow-up.

## Módulo relacionado

Ai | Gateway

## PRD relacionado: PRD-AI-001

## Escopo

### Incluído

- **Backend (API):** AutopilotRunDispatcherListener, AiAutopilotRunActions, AiRunExecutionJob, AiContextBuilderService, ToolDispatcherService, GuardrailEvaluatorService, TokenBudgetService, AiAgentDelegationService
- **Gateway:** AiRunOrchestratorService, AiCompletionConsumer, ToolExecutorService, ToolCallLoopService, GuardrailEvaluatorService, StreamHandlerService, OpenAIProviderAdapter, RunCompletionService
- Correções de segurança (tenant isolation, idempotency, ReDoS)
- Correções de performance (N+1, payload bloat, caching)
- Correções de token economy (duplicação de prompts, tool definitions inflacionadas, context window growth)

### Excluído

- Frontend Angular (sem alterações de UI)
- Knowledge Base / RAG (fora do escopo de autopilot run)
- Prompt hierarchy (Master/Plan/Segment/Tenant) — funcionalidade estável
- Migrations de banco (apenas índices pontuais se necessário)
- Novos features — apenas correções e refatorações

---

## Achados da Auditoria

### Resumo Executivo

| Severidade | Backend (API) | Gateway (NestJS) | Total  |
| ---------- | ------------- | ---------------- | ------ |
| CRITICAL   | 2             | 3                | **5**  |
| HIGH       | 5             | 5                | **10** |
| MEDIUM     | 4             | 6                | **10** |
| **Total**  | **11**        | **14**           | **25** |

---

### CRITICAL — Correção obrigatória

| ID   | Camada  | Arquivo                                     | Achado                                                                                                                                                 |
| ---- | ------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| C-01 | API     | TokenBudgetService.php L87-98               | Race condition em cache: read+write não-atômico. Budget pode ser ultrapassado por workers concorrentes. Usar `INCRBYFLOAT` atômico do Redis.           |
| C-02 | API     | AutopilotRunDispatcherListener.php L522-580 | Payload de stream oversized (~60KB por run). Inclui prompts completos, file contents e 30 mensagens. Gateway deveria fazer lazy-load via Internal API. |
| C-03 | Gateway | ai-completion.consumer.ts L100-130          | `void this.consumeLoop()` fire-and-forget sem error boundary. Se `processMessage()` lança exceção não-tratada, consumer para silenciosamente.          |
| C-04 | Gateway | ai-run-orchestrator.service.ts L240-280     | `Promise.allSettled()` mascara falhas de ferramentas. Tool exceptions absorvidas silenciosamente; modelo recebe contexto incompleto.                   |
| C-05 | Gateway | tool-executor.service.ts L109-175           | Race condition em validação de delegação circular. Validação local (não-transacional) permite ciclos A→B, B→A concorrentes.                            |

### HIGH — Correção prioritária

| ID   | Camada  | Arquivo                                        | Achado                                                                                                                                                |
| ---- | ------- | ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| H-01 | API     | AiAgentDelegationService.php L14-60            | **SEGURANÇA:** Método `delegate()` sem `authorize()`. Sem validação que source/target agents pertencem ao tenant. Cross-tenant delegation possível.   |
| H-02 | API     | AutopilotRunDispatcherListener.php L490-520    | N+1: `buildContextSnapshot()` carrega 30 mensagens adicionais quando ContextBuilderService já carregou 15. Total: 45 objetos + N+1 em `extended`.     |
| H-03 | API     | AutopilotRunDispatcherListener.php L120,420    | System prompt enviado DUPLICADO no stream: uma vez em `agent_system_prompt` e outra no `prompt_snapshot`. +1-5KB por run.                             |
| H-04 | API     | AutopilotRunDispatcherListener.php L594-600    | Agent files enviados como conteúdo completo (strings) no stream, não como referências. Agente com 50MB de docs = 50MB no Redis por run.               |
| H-05 | API     | AiAgentDelegationService.php (sem idempotency) | Sem idempotency key na delegação. Retry de rede cria child runs duplicados, duplicando consumo de tokens.                                             |
| H-06 | Gateway | ai-run-orchestrator.service.ts L183-215        | Token budget bypass: quando budget excedido, `send_message` executa sem compactação (`false`). Contexto cresce além do limite.                        |
| H-07 | Gateway | ai-run-orchestrator.service.ts L265-280        | Message history cresce unbounded no tool call loop. Iteração 5 envia mensagens das iterações 1-4 completas. 3-5x token waste em multi-iteration runs. |
| H-08 | Gateway | guardrail-evaluator.service.ts L13-35          | Pattern matching fraco (substring). False positives (`"token generation"` bloqueado) e false negatives (`"apikey"` passa). Sem regex/word boundary.   |
| H-09 | Gateway | tool-executor.service.ts L69-108               | **SEGURANÇA:** Delegation não valida tenant ownership do target agent no Gateway. Cross-tenant data leakage possível.                                 |
| H-10 | Gateway | tool-executor.service.ts L278-305              | Redis reply key sem namespace de tenant. Keys expirados não têm TTL. Memory leak em Redis + risco de cross-tenant response leakage.                   |

### MEDIUM — Follow-up

| ID   | Camada  | Arquivo                                     | Achado                                                                                                                     |
| ---- | ------- | ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| M-01 | API     | GuardrailEvaluatorService.php L84-90        | JSON encode repetido 5x nos checks de condição. Deveria encodar 1x no topo.                                                |
| M-02 | API     | GuardrailEvaluatorService.php L114-120      | ReDoS: regex user-supplied sem timeout/limits. Evil regex em 60KB subject = CPU spike.                                     |
| M-03 | API     | ToolDispatcherService.php L36-60            | `getToolDefinitions()` instancia handler de toda ferramenta (50+) para obter descrição. Deveria lazy-load.                 |
| M-04 | API     | AutopilotRunDispatcherListener.php L267-297 | Cache key de playbook sem versão/status. 3600s TTL = stale data. firstOrCreate com nome hardcoded.                         |
| M-05 | Gateway | ai-run-orchestrator.service.ts L548-561     | Streaming com chunk fixo 120 chars. Pode cortar UTF-8. 500+ Redis PUBLISH por resposta grande.                             |
| M-06 | Gateway | openai-provider.adapter.ts L248-265         | Tool definitions enviadas integrais em toda iteração. Deveria filtrar por tools já usadas. 10-15% token waste.             |
| M-07 | Gateway | ai-run-orchestrator.service.ts L35-57       | ToolCallLoopService e RunCompletionService instanciados no constructor (não injetados). Não testáveis e não monitoráveis.  |
| M-08 | Gateway | tool-executor.service.ts L69-104            | Sem circuit breaker em tool execution. Tool lenta (2min) bloqueia toda a run sem timeout.                                  |
| M-09 | Gateway | context-window.service.ts L86               | TTL de 300s (5min) muito curto para contexto de ticket. Cache hit ~70%. Deveria ser 1800s (30min).                         |
| M-10 | Gateway | tool-call-loop.service.ts L137-220          | Bug de indexação em priority enforcement. `skippedByPriority` usa índices de `eligibleCalls` mas itera sobre `preActions`. |

---

## Etapas propostas

### Sprint 1 — Segurança e Bugs Críticos (CRITICAL + HIGH Security)

1. **C-01:** Corrigir race condition no `TokenBudgetService` — usar `INCRBYFLOAT` atômico
2. **H-01:** Adicionar authorization check + tenant validation no `AiAgentDelegationService`
3. **H-05:** Implementar idempotency key no `delegate()` via unique constraint
4. **H-09:** Adicionar tenant ownership validation no Gateway `ToolExecutorService`
5. **H-10:** Adicionar tenant namespace + TTL nas reply keys do Redis RPC
6. **C-05:** Mover validação de ciclo de delegação para backend com lock atômico
7. **C-03:** Adicionar error boundary no consumer loop com backoff e recovery

### Sprint 2 — Performance e Token Economy (CRITICAL + HIGH Performance)

8. **C-02:** Refatorar stream payload para minimal (run_id, agent_id, ticket_id, input_text). Gateway faz lazy-load via Internal API
9. **H-02:** Eliminar `buildContextSnapshot()` duplicado; reusar contexto já construído
10. **H-03:** Remover duplicação de system prompt no stream payload
11. **H-04:** Enviar file IDs (não conteúdo) no stream; Gateway busca via Internal API
12. **H-07:** Implementar context window sliding no tool call loop (não acumular mensagens)
13. **H-06:** Forçar compactação quando budget excedido (`shouldCompactToolResults = true`)

### Sprint 3 — Refatoração e Melhorias (HIGH refatoração + MEDIUM)

14. **H-08:** Refatorar guardrail pattern matching com RegExp + word boundary
15. **M-01:** Otimizar JSON encode único no GuardrailEvaluatorService
16. **M-02:** Adicionar timeout/limites em regex user-supplied (ReDoS protection)
17. **M-03:** Lazy-load tool handlers no ToolDispatcherService
18. **M-05:** Aumentar chunk size para 512 chars + UTF-8 safe splitting
19. **M-06:** Filtrar tool definitions por tools relevantes em iterações subsequentes
20. **M-07:** Registrar ToolCallLoopService e RunCompletionService como providers injetáveis
21. **M-08:** Implementar circuit breaker + timeout por tool no Gateway
22. **M-09:** Aumentar TTL de contexto de ticket para 1800s
23. **M-10:** Corrigir bug de indexação no priority enforcement do ToolCallLoopService

---

## Entregas derivadas

**Entregas:** 3 | **Tasks:** 7

| Entrega | Descrição                   | Tasks                       | Esforço | Status |
| ------- | --------------------------- | --------------------------- | ------- | ------ |
| 1       | Segurança e Bugs Críticos   | TASK-036.1.1 — TASK-036.1.3 | M       | todo   |
| 2       | Performance e Token Economy | TASK-036.2.1 — TASK-036.2.2 | M       | todo   |
| 3       | Refatoração e Melhorias     | TASK-036.3.1 — TASK-036.3.2 | M       | todo   |

### Entrega 1 — Segurança e Bugs Críticos

| Task         | Descrição                                                                        | Agente   | Paralelo com |
| ------------ | -------------------------------------------------------------------------------- | -------- | ------------ |
| TASK-036.1.1 | Fix race condition TokenBudget + auth delegation (C-01, H-01, H-05)              | @BACKEND | TASK-036.1.2 |
| TASK-036.1.2 | Fix consumer error boundary + tenant isolation Gateway (C-03, C-05, H-09, H-10)  | @DEV     | TASK-036.1.1 |
| TASK-036.1.3 | Fix race condition delegação circular — backend lock atômico (C-05 backend side) | @BACKEND | —            |

### Entrega 2 — Performance e Token Economy

| Task         | Descrição                                                                             | Agente   | Paralelo com |
| ------------ | ------------------------------------------------------------------------------------- | -------- | ------------ |
| TASK-036.2.1 | Refatorar stream payload para minimal + eliminar duplicações (C-02, H-02, H-03, H-04) | @BACKEND | TASK-036.2.2 |
| TASK-036.2.2 | Fix tool call loop context growth + budget compaction (H-06, H-07)                    | @DEV     | TASK-036.2.1 |

### Entrega 3 — Refatoração e Melhorias

| Task         | Descrição                                                                          | Agente          | Paralelo com |
| ------------ | ---------------------------------------------------------------------------------- | --------------- | ------------ |
| TASK-036.3.1 | Refatorar guardrails + tool dispatcher + ReDoS protection (H-08, M-01, M-02, M-03) | @BACKEND + @DEV | TASK-036.3.2 |
| TASK-036.3.2 | Streaming UTF-8, circuit breaker, DI fixes, cache TTL (M-05 a M-10)                | @DEV            | TASK-036.3.1 |

---

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                                                        | Ação      | Achados Relacionados         |
| -------------------------------------------------------------- | --------- | ---------------------------- |
| api/src/Domain/Ai/Services/TokenBudgetService.php              | modificar | C-01                         |
| api/src/Domain/Ai/Services/AiAgentDelegationService.php        | modificar | H-01, H-05, C-05             |
| api/src/Domain/Ai/Listeners/AutopilotRunDispatcherListener.php | modificar | C-02, H-02, H-03, H-04, M-04 |
| api/src/Domain/Ai/Services/GuardrailEvaluatorService.php       | modificar | M-01, M-02                   |
| api/src/Domain/Ai/Services/ToolDispatcherService.php           | modificar | M-03                         |
| api/src/Domain/Ai/Services/AiContextBuilderService.php         | modificar | H-02                         |

### Gateway (NestJS)

| Arquivo                                                        | Ação      | Achados Relacionados         |
| -------------------------------------------------------------- | --------- | ---------------------------- |
| gateway/src/domains/ai/services/ai-completion.consumer.ts      | modificar | C-03                         |
| gateway/src/domains/ai/services/ai-run-orchestrator.service.ts | modificar | C-04, H-06, H-07, M-05, M-07 |
| gateway/src/domains/ai/services/tool-executor.service.ts       | modificar | C-05, H-09, H-10, M-08       |
| gateway/src/domains/ai/services/tool-call-loop.service.ts      | modificar | M-10                         |
| gateway/src/domains/ai/services/guardrail-evaluator.service.ts | modificar | H-08                         |
| gateway/src/domains/ai/services/stream-handler.service.ts      | modificar | M-05                         |
| gateway/src/domains/ai/adapters/openai-provider.adapter.ts     | modificar | M-06                         |
| gateway/src/domains/ai/services/context-window.service.ts      | modificar | M-09                         |
| gateway/src/domains/ai/ai.module.ts                            | modificar | M-07                         |

---

## Riscos e dependências

### Riscos

| Risco                                       | Probabilidade | Impacto | Mitigação                                                        |
| ------------------------------------------- | ------------- | ------- | ---------------------------------------------------------------- |
| Stream payload refactor quebra Gateway      | Alta          | Alto    | Feature flag para payload v2; manter v1 em paralelo por 1 sprint |
| Race condition fix no budget causa deadlock | Média         | Alto    | Usar `INCRBYFLOAT` (lock-free) em vez de pessimistic lock        |
| Consumer error boundary mascara bugs        | Média         | Médio   | Logging detalhado + dead-letter queue para mensagens falhadas    |
| Circuit breaker muito agressivo             | Baixa         | Médio   | Threshold conservador (10 falhas, 60s open)                      |

### Dependências

- Internal API (`/internal/ai/*`) deve suportar lazy-load de context/prompt/tools individualmente (já existe)
- Redis 7+ para `INCRBYFLOAT` em chaves com TTL (já disponível)
- Nenhuma migration de banco necessária

---

## Estimativa

| Item                          | Valor                                              |
| ----------------------------- | -------------------------------------------------- |
| Complexidade                  | Alta                                               |
| Camadas afetadas              | Backend + Gateway                                  |
| Migrações necessárias         | Não (apenas índices opcionais)                     |
| Impacto em módulos existentes | Sim (Chat consume events, Reports agrega métricas) |

---

## Impacto Estimado

| Métrica                    | Antes           | Depois estimado       | Melhoria     |
| -------------------------- | --------------- | --------------------- | ------------ |
| Payload Redis Stream/run   | ~60KB           | ~2KB                  | **97% ↓**    |
| Tokens por multi-iter run  | 3-5x overhead   | 1.2x overhead         | **60-75% ↓** |
| RAM Redis (100k runs/dia)  | ~6GB            | ~200MB                | **97% ↓**    |
| N+1 queries por run        | 45+ messages    | 15 messages (1 query) | **67% ↓**    |
| Tool handler instanciação  | 50+ por request | 1-3 por request       | **94% ↓**    |
| Gaps de segurança CRITICAL | 3               | 0                     | **100% ↓**   |
