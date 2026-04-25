# PROGRESS — Refatoração de Performance do Autopilot

> Iniciado: 2026-04-23
> Objetivo: ganhar **velocidade** (latência menor entre agents, estilo n8n) e **confiabilidade** (delegação correta entre agents) no módulo Autopilot. Sem aumentar custo de tokens.

## Legenda

- [x] Feito e validado
- [~] Em andamento
- [ ] Pendente
- [!] Bloqueado / precisa decisão

---

## QW1 — Snapshot publishing no `DispatchAutopilotRunJob`

Eliminar 2–3 round-trips HTTP `gateway → api/internal/ai/{prompt,context,tools}` por run.

- [x] Criar `AutopilotRunSnapshotResolver` (hidrata prompt/context/tools, tolerante a falhas)
- [x] Injetar resolver em `DispatchAutopilotRunJob::handle()`
- [x] Injetar `prompt`, `context`, `tools`, `hydrated_at` no payload do XADD
- [x] Extrair publish para `AutopilotRunStreamPublisher` (reuso entre dispatch e delegação)
- [x] `get_errors` limpo
- [x] **Validação real via tinker** — `prompt_len=3556`, `context_keys=ticket_id,tenant_id,status,subject`, `tools_count=5`, `hydrated_at=2026-04-24T00:21:47+00:00`
- [x] **Publish real validado** — `XADD ai.run.request` confirmado por `XREVRANGE`
- [x] Pest 18/18 passando no escopo (DispatchAutopilotRunJob + AiAgentDelegationService + AutopilotRunDispatcherListener)
- [ ] Medir p50/p95 antes vs depois em produção

**Arquivos:**

- `api/src/Domain/Ai/Services/AutopilotRunSnapshotResolver.php` (novo)
- `api/src/Domain/Ai/Services/AutopilotRunStreamPublisher.php` (novo)
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (refatorado)

---

## CRÍTICO — Delegação entre agents (child runs) — RESOLVIDO

**Antes:** delegação (Atendimento → Vendas) chamava `AiRunExecutionJob::dispatch()` no caminho PHP lento (sem snapshot, sem paralelismo de tools, paga 2–3 RTT HTTP).

**Depois:** `AiAgentDelegationService::publishChildRunToStream()` publica direto em `ai.run.request` com snapshot já hidratado, `parent_run_id`, `delegation_depth` e `delegation_stack`. Mesmo caminho rápido do dispatch inicial. **Fallback automático** para `AiRunExecutionJob` se o publish falhar (confiabilidade preservada).

- [x] Mapeado fluxo completo (`AiAgentDelegationService::delegate` → `AiRunExecutionJob`)
- [x] Refatorar para publicar em `ai.run.request` com `parent_run_id` + `delegation_depth` + `delegation_stack`
- [x] Snapshot hidratado por agente alvo (mesmo resolver compartilhado)
- [x] Fallback gracioso para `AiRunExecutionJob` em caso de falha do publisher
- [x] Fix bonus: `createsCircularDelegation` lia coluna inexistente `agent_id` em `ai_autopilot_runs` — agora usa apenas `input_context['agent_id']`
- [x] Pest 6/6 do `AiAgentDelegationServiceTest` passando
- [ ] Teste real end-to-end com conversa que delega Atendimento → Vendas (precisa cliente real ou mock de webhook)

**Arquivos:**

- `api/src/Domain/Ai/Services/AiAgentDelegationService.php` (modificado: construtor com 2 deps, novo método `publishChildRunToStream`, fix `createsCircularDelegation`)

---

## Falhas pré-existentes (não causadas por esta sessão)

Confirmado via inspeção de `git status` que estes arquivos não estão na minha lista de mudanças:

- `AiAutopilotRunActionsTokenTest > marks run as failed when completion throws` — `executeWithEvents` no branch develop perdeu try/catch interno; teste espera o catch que só existe no `AiRunExecutionJob`. **Responsabilidade do autor da refatoração de `AiAutopilotRunActions`.**
- `AiRunCostControlConfigurationTest` — Mockery sem expectation para `$redis->set(lockKey, 1, [EX,NX])` adicionado em `acquireMessageDispatchLock`. **Mesma origem.**

---

## QW2 — Stable prefix do system prompt (cache de tokens OpenAI)

Manter parte estática do prompt no início, variável (contexto, ticket) no fim. Aumenta `cached_prompt_tokens`.

- [ ] Auditar `AiPromptResolverService::resolve()` — verificar ordem `[STATIC] [VARIABLE]`
- [ ] Auditar `composeFirstCallPrompt` no orchestrator do gateway
- [ ] Medir `cached_prompt_tokens` antes vs depois (precisa telemetria primeiro)

> **Bloqueado em telemetria.** Sem medir é otimização cega. **Decisão:** adiar.

---

## QW3 — Sliding window de tool history (PHP)

Caminho PHP `AiAutopilotRunActions` envia histórico inteiro de tool messages. Gateway já tem `MAX_TOOL_HISTORY_MESSAGES=10`.

- [ ] **Decisão:** se delegação for movida pro gateway (ver CRÍTICO), QW3 vira obsoleto. Se ficar no PHP, mirror do sliding window.

---

## Telemetria — Snapshot hits no gateway

Sem isso não dá pra provar que QW1 funcionou.

- [ ] Counter `ai_consumer_snapshot_total{slice="prompt|context|tools",result="hit|miss|stale"}` em `prompt-assembler.service.ts` e `context-window.service.ts`
- [ ] Histogram `ai_consumer_run_handle_duration_seconds` (verificar se já existe)
- [ ] Logs estruturados com `[snapshot=hit|miss]`

---

## Validação end-to-end

- [ ] tinker: disparar `AutopilotTriggerFired` manualmente em ticket sintético
- [ ] tinker: disparar conversa que delega Atendimento → Vendas e medir tempo total
- [ ] Verificar log do gateway por chamadas a `/internal/ai/{prompt,context,tools}` (deve cair pra ~zero)
- [ ] Verificar via Redis `XLEN ai.run.request` se payload tem `prompt`, `context`, `tools`, `hydrated_at`

---

## Gates e documentação final

- [ ] `cd api && composer gate:all`
- [ ] CHANGELOG do dia (já adicionado parcial — completar com validações reais)
- [ ] MEMORY: já criado `2026-04-23-autopilot-snapshot-publishing.md`. Adicionar segundo doc se delegação for movida.
- [ ] `git commit` semântico via @GIT_COMMIT
