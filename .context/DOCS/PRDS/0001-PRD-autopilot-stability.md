# 0001-PRD-autopilot-stability

**Versão:** 1.1
**Data:** 2026-05-20
**Autor:** Rafael Silva
**Status:** [ ] Rascunho | [x] Em revisão | [ ] Aprovado

---

## Visão Geral

Corrigir 15 gaps de estabilidade, segurança e observabilidade identificados no módulo Autopilot via análise estática do codebase (api/src/Domain/Ai, gateway/src/domains/ai, app/src/app/pages/reports), tornando-o apto para produção sem risco de runs zombie, dispatches duplicados, guardrails silenciosos e métricas ausentes.

## Problema

Análise do módulo Autopilot (`api/src/Domain/Ai/`, `gateway/src/domains/ai/`) revelou falhas estruturais que comprometem confiabilidade em produção:

1. **Race condition Redis lock**: TTL=60s (`DispatchAutopilotRunJob.php:528-529`) < job timeout=300s (linha 49) → janela de 240s onde webhook duplicado redispacha run.
2. **Runs zombie**: sem watchdog ativo; run travada em `running` indefinidamente se gateway falha ou trava (apesar de existir config `ai.stale_run_threshold_minutes`, não há job consumindo).
3. **Dead-letter ausente**: `DispatchAutopilotRunJob` sem método `failed(Throwable $e)`. Após 3 retries falha silenciosamente; padrão correto existe em `AiPromptGuardianJob`.
4. **Guardrails inoperantes**: `GuardrailEvaluatorService::resolveGuardrails()` (linha 60-65) lê apenas `config('ai.autopilot.static_guardrails')` — model `AiAutopilotGuardrail` (DB) nunca consultado por tenant.
5. **Approval órfã**: `AiAutopilotApproval` sem `expires_at`. Run bloqueada eternamente esperando HITL inexistente.
6. **Observabilidade parcial**: `MetricsService` (`api/src/Domain/Shared/Services/MetricsService.php:21`) tem `recordAutopilotWebhookDuration` e `recordAutopilotGuardrailBlock`, mas faltam métricas críticas (run duration end-to-end, tool iterations, approval wait time, lock contention). 5 dashboards Grafana sem dados completos.
7. **Rastreabilidade quebrada**: sem `correlation_id` propagado Laravel → NestJS → Gemini. Impossível correlacionar logs entre serviços.
8. **Código morto**: `AiAutopilotTriggerLog` model referencia tabela dropada em migration `2026_03_04_150000_drop_ai_autopilot_trigger_logs_table`. Insert em `DispatchAutopilotRunJob.php:496-514` mascarado por `class_exists()`.
9. **Cobertura testes**: cenários de falha ausentes (gateway timeout, tool exception, guardrail BLOCK, approval rejected, concurrent runs no mesmo msg_id).
10. **Permissões coarse**: única permissão `ai.autopilots.manage` aplicada universalmente (`AiAgentPolicy.php:13-33`). Sem diferenciação view/execute/admin.
11. **Prompt injection**: message body cru entra em `AiContextBuilderService` sem sanitização documentada.
12. **Snapshot sem cache**: `AutopilotRunSnapshotResolver` reconstrói por run, gerando N+1 queries em conversation_history + tools + agent_files.
13. **Cancelamento sem propagação**: `DELETE /api/ai/runs/{id}` marca status=cancelled local; gateway continua executando até completar/falhar (work desperdiçado + custo LLM).
14. **Documentação de schema incompleta**: `playbook_id` nullable em `ai_autopilot_runs` (migration `2026_03_22`) com motivo registrado apenas no PHPDoc da migration ("V2 ad-hoc runs e simulator"). Decisão ausente do código de domínio.

## Solução

Cinco fases priorizadas por impacto em produção:

- **Fase 1 — Estabilidade Runtime**: corrigir race condition lock, implementar `failed()` handler, watchdog de runs zombie, propagação de cancelamento ao gateway.
- **Fase 2 — Observabilidade**: `correlation_id` distribuído, expansão do `MetricsService` existente, logs estruturados.
- **Fase 3 — Testes**: suite cobrindo todos caminhos de falha e concorrência.
- **Fase 4 — Segurança e Limpeza**: guardrails DB-driven, approval TTL, permissions granulares, remoção `AiAutopilotTriggerLog`, prompt sanitização.
- **Fase 5 — Polimento**: cache snapshot resolver, documentação do contrato playbook nullable, remoção `migrations_backup_20260216/`.

## Usuários

- **Primário:** Operadores de atendimento — confiam que respostas automáticas chegam sem duplicatas ou falhas silenciosas.
- **Secundário:** Admins de tenant — precisam de guardrails funcionais para controlar comportamento do agente por regra de negócio.
- **Interno:** Equipe de engenharia — observabilidade e rastreabilidade para debug em produção.

## Requisitos Funcionais

1. [RF01] `DispatchAutopilotRunJob` deve implementar `failed(Throwable $e)` que: marca run como `failed`, registra log estruturado (`run_id`, `tenant_id`, `correlation_id`, exception class/message), e dispara evento `AiRunFailed` existente (reuso do canal `ai.run.failed`).
2. [RF02] Redis lock em `acquireMessageDispatchLock()` (`DispatchAutopilotRunJob.php:517-532`) deve usar TTL = `$this->timeout` (300s). Justificativa: evita janela de 240s onde duplicate webhook recria run.
3. [RF03] Job `AutopilotZombieRunCleanupJob` deve ser registrado em scheduler (cron 1min) e marcar como `failed` todas runs com `status=running` e `started_at < now() - config('ai.stale_run_threshold_minutes', 6) minutes`. Reutilizar config existente `ai.stale_run_threshold_minutes`.
4. [RF04] `GuardrailEvaluatorService::resolveGuardrails($tenantId)` deve consultar `AiAutopilotGuardrail::where('tenant_id', $tenantId)->where('is_active', true)->get()` e fazer merge com config estático. Resultado cacheado por tenant (Redis, TTL=300s, invalidado em update de guardrail via observer).
5. [RF05] Migration adiciona coluna `expires_at` em `ai_autopilot_approvals`. Backfill: `UPDATE ai_autopilot_approvals SET expires_at = LEAST(created_at + INTERVAL '24 hours', NOW()) WHERE status='pending'`. Approvals com `created_at + 24h < now()` ficam expirados imediatamente — cleanup job marca como `expired` (não `rejected`, para distinguir cause). Default em novas approvals: `created_at + 24h`.
6. [RF06] Job `AutopilotApprovalExpiryJob` (cron hourly): para cada approval `status=pending` com `expires_at < now()` → status=`expired`, run associada → `status=failed` + log estruturado.
7. [RF07] UUID `correlation_id` gerado no `AutopilotTriggerFired` deve ser:
   - Persistido em `ai_autopilot_runs.correlation_id` (nova coluna nullable, indexed).
   - Propagado em payload `ai.run.request` Redis/BullMQ → gateway.
   - Adicionado em todos `Log::*` calls do job/listener (via `Log::withContext`).
   - Logado em gateway (`gateway/src/domains/ai/`) em todos eventos do run lifecycle.
   - Propagado em request HTTP de volta ao api se houver callback.
8. [RF08] **Expandir** `MetricsService` existente (`api/src/Domain/Shared/Services/MetricsService.php`) com novos métodos:
   - `recordAutopilotRunDuration(float $seconds, array $labels)` — emitido em `failed()`/completion.
   - `recordAutopilotToolIterations(int $count, array $labels)`.
   - `recordAutopilotApprovalWaitTime(float $seconds, array $labels)`.
   - `recordAutopilotLockContention(array $labels)` — incrementado em `acquireMessageDispatchLock` quando retorna false.
   - Validar dashboards Grafana (`observability/grafana/dashboards/autopilot-*.json`) consumindo métricas.
9. [RF09] Testes Feature/E2E novos:
   - `test_run_marked_failed_when_gateway_timeout()` — mock gateway timeout > stale threshold.
   - `test_tool_handler_exception_marks_action_failed()`.
   - `test_guardrail_block_aborts_run_with_reason()`.
   - `test_approval_expired_marks_run_failed()`.
   - `test_approval_rejected_marks_run_failed()`.
   - `test_concurrent_webhooks_create_single_run()` — Redis real, 2 dispatches paralelos.
   - `test_correlation_id_persisted_and_logged()`.
10. [RF10] Criar permissões granulares no seeder de permissions:
    - `ai.autopilots.view` (GET endpoints).
    - `ai.autopilots.run` (POST run/cancel).
    - `ai.autopilots.manage` (mantida — admin: playbooks, guardrails, tools).
    - Roles existentes devem receber `view` + `run` por padrão. Refatorar `AiAgentPolicy` + `AiAutopilotRunController` + FormRequests para usar gate correto.
11. [RF11] Remover `AiAutopilotTriggerLog`:
    - Deletar model `api/src/Domain/Ai/Models/AiAutopilotTriggerLog.php`.
    - Deletar bloco `DispatchAutopilotRunJob.php:496-514`.
    - Grep completo: `grep -rn AiAutopilotTriggerLog api/` deve retornar zero.
12. [RF12] `AiContextBuilderService` deve sanitizar `message_body` antes de incluir no prompt:
    - Truncar a 4000 chars (config-driven).
    - Escapar delimitadores de system prompt (`<<<`, `>>>`, marcadores típicos).
    - Strip de instruções meta (regex contra padrões "Ignore previous instructions", "System:", etc — lista expansível).
    - Wrappear input em delimitador explícito (`<<<USER_INPUT>>>...<<<END>>>`).
13. [RF13] `AutopilotRunSnapshotResolver` deve cachear resultado por `tenant:agent:ticket` (Redis, TTL=60s). Invalidar em:
    - Novo `MessageReceived` para o ticket.
    - Update de `AiAgent` (observer).
    - Update de `AiAutopilotTool` linked ao agent.
14. [RF14] Documentar contrato `playbook_id` nullable:
    - PHPDoc em `AiAutopilotRun::$playbook_id` referenciando "V2 ad-hoc runs e simulator".
    - Adicionar `AiAutopilotRun::isAdHoc(): bool { return $this->playbook_id === null; }`.
    - Comentário em `DispatchAutopilotRunJob` no ponto que cria run sem playbook.
15. [RF15] Cancelamento de run propagado ao gateway:
    - `DELETE /api/ai/runs/{id}` publica evento `ai.run.cancel_requested` no canal Redis/BullMQ.
    - Gateway escuta canal, aborta tool loop iteração atual, marca completion como `cancelled`.
    - Run permanece com `status=cancelled` (não `failed`).
16. [RF16] Remover diretório `api/database/migrations_backup_20260216/` se schema atual estável. Documentar decisão no commit.

## Requisitos Não-Funcionais

1. [RNF01] Nenhuma run deve permanecer em `status=running` por mais de `ai.stale_run_threshold_minutes` (default 6 min) sem watchdog atuar.
2. [RNF02] Lock idempotente deve prevenir duplicate dispatch em janela ≥ 300s (igual job timeout).
3. [RNF03] Guardrails por tenant aplicados em 100% das execuções com latência adicional < 50ms (devido a cache Redis TTL 5min).
4. [RNF04] `correlation_id` deve aparecer em 100% dos logs de uma run (Laravel + gateway).
5. [RNF05] Snapshot resolver não deve gerar N+1 queries — eager load + cache hit ratio > 80% em workload típico.
6. [RNF06] Cada fix mantém retrocompatibilidade com runs históricas (sem schema destrutivo sem backfill).
7. [RNF07] Permissões split não devem quebrar sessões ativas — migration de roles em deploy zero-downtime.
8. [RNF08] Métricas adicionais não devem aumentar latência p99 do dispatch em mais de 5ms.

## Critérios de Aceite

Cada critério com comando/verificação concreta:

- [ ] **Lock TTL corrigido**: `grep -n "'EX'" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` mostra `300` (não `60`). Teste `test_concurrent_webhooks_create_single_run` passa.
- [ ] **failed() implementado**: `phpunit --filter=DispatchAutopilotRunJobFailedHandlerTest` passa. Run em DLQ após 3 tries tem `status=failed`.
- [ ] **Watchdog ativo**: `phpunit --filter=AutopilotZombieRunCleanupJobTest` passa. `Schedule::job(AutopilotZombieRunCleanupJob::class)->everyMinute()` registrado.
- [ ] **Guardrails DB**: criar `AiAutopilotGuardrail` via factory com `rule_type=BLOCK` para tenant X → run desse tenant aborta. Cache TTL=300s validado via `redis-cli`.
- [ ] **Approval TTL**: nova coluna `expires_at` existe (`SELECT column_name FROM information_schema.columns WHERE table_name='ai_autopilot_approvals'`). Cleanup job marca approvals stale como `expired`.
- [ ] **Correlation ID end-to-end**: `grep "correlation_id=<uuid>" /var/log/laravel.log gateway.log` mostra mesmo UUID em ambos serviços para uma run.
- [ ] **Métricas expandidas**: `MetricsService` tem 4 novos métodos. `curl /api/metrics` retorna `autopilot_run_duration_seconds`, `autopilot_lock_contention_total`, etc.
- [ ] **7 novos testes failure path**: `phpunit --testsuite=Feature --filter=Autopilot` cobre cenários RF09.
- [ ] **Permissions split**: `grep -rn "'ai.autopilots.view'" api/` retorna ≥ 5 ocorrências. Teste `AutopilotRoutesAuthTest` cobre 3 perms.
- [ ] **TriggerLog removido**: `grep -rn AiAutopilotTriggerLog api/` retorna 0.
- [ ] **Sanitização prompt**: `phpunit --filter=AiContextBuilderSanitizationTest` passa com payload contendo "Ignore previous instructions".
- [ ] **Snapshot cache**: segunda chamada idêntica em < 60s não dispara queries (`DB::enableQueryLog`).
- [ ] **Playbook nullable documentado**: PHPDoc + método `isAdHoc()` presente em model.
- [ ] **Cancelamento propagado**: gateway loga `cancel_requested` ao receber DELETE. Run final = `cancelled` (não `failed`).
- [ ] **Backup migrations removido**: `ls api/database/migrations_backup_20260216` retorna "No such file".

## Wireframes / Fluxos

Sem UI nova. Diagrama do fluxo Autopilot estabilizado:

```
[WhatsApp/Webchat] → ChatWebhookController
  → AutopilotTriggerFired { correlation_id = UUID() }
  → AutopilotRunDispatcherListener
  → DispatchAutopilotRunJob (timeout=300s, tries=3, backoff=[10,60])
    ├─ acquireMessageDispatchLock(TTL=300s)  ← FIX RF02
    ├─ AiContextBuilderService.sanitize(message_body)  ← FIX RF12
    ├─ AutopilotRunSnapshotResolver (cache Redis 60s)  ← FIX RF13
    ├─ GuardrailEvaluator (static config + DB por tenant)  ← FIX RF04
    ├─ persist run { correlation_id }  ← FIX RF07
    ├─ MetricsService.record* (duration, iterations)  ← FIX RF08
    └─ publish ai.run.request { correlation_id }
       → [Gateway NestJS]
         → Gemini Provider (@google/generative-ai)  [NOTE: provider real, não Claude]
         → tool loop (max 5 iter, max 800 tokens)
         → publish ai.run.completed | ai.run.failed | ai.run.blocked
  
  on failure → DispatchAutopilotRunJob::failed()  ← FIX RF01
  on cancel → ai.run.cancel_requested → gateway aborta  ← FIX RF15
  
Background:
  → AutopilotZombieRunCleanupJob (cron 1min)  ← FIX RF03
  → AutopilotApprovalExpiryJob (cron 1h)  ← FIX RF06
```

## Dependências

- `ai-tool-parameter-resolution` — já implementado.
- Schema `ai_autopilot_approvals` com `tenant_id NOT NULL` — migration 2026-05-10 aplicada.
- `MetricsService` existente em `api/src/Domain/Shared/Services/` — expandir (não substituir).
- Eventos `ai.run.*` já registrados em config — reusar canais.
- Config `ai.stale_run_threshold_minutes` já existe — consumir.

## Riscos

| ID | Risco | Probabilidade | Impacto | Mitigação | Responsável |
|---|---|---|---|---|---|
| R1 | TTL=300s bloqueia retry legítimo da mesma msg | Baixa | Médio | Trade-off documentado: idempotência > retry manual em janela 5min | Backend |
| R2 | Lock multi-pod sem SET NX atômico | Baixa | Alto | Já usa `SET NX EX` (atômico) — validar em staging com Redis Sentinel | Backend |
| R3 | Guardrails DB-driven aumenta latência | Baixa | Médio | Cache Redis TTL=5min, invalidado por observer | Backend |
| R4 | Backfill `expires_at` afeta approvals históricas | Alta | Baixo | Migration usa `LEAST()` + status=`expired` para stale antigas | Backend |
| R5 | Permission split quebra deploy zero-downtime | Média | Alto | Migration adiciona perms, mantém `manage` como superset temporário; remoção em fase 2 | Backend |
| R6 | Remover TriggerLog tem referência não encontrada | Baixa | Médio | Grep completo + busca por string em factories/seeders | Backend |
| R7 | Cancelamento propagado sem ack do gateway | Média | Médio | Implementar com timeout + retry; aceita inconsistência transiente | Gateway |
| R8 | Sanitização de prompt remove conteúdo legítimo | Média | Baixo | Lista de regex versionada via config + observabilidade de matches | Backend |

## Cronograma Estimado

- Fase 1 — Estabilidade Runtime: 3 dias (RF01, RF02, RF03, RF15)
- Fase 2 — Observabilidade: 2 dias (RF07, RF08)
- Fase 3 — Testes: 2 dias (RF09)
- Fase 4 — Segurança + Limpeza: 3 dias (RF04, RF05, RF06, RF10, RF11, RF12)
- Fase 5 — Polimento: 1 dia (RF13, RF14, RF16)
- Subtotal: 11 dias
- Buffer (20%): 2 dias
- **Total: ~13 dias úteis**

Complexidade: **G (Grande)** — 16 RFs, alteração em api + gateway, migration de permissions + schema.

## Revisões

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-20 | Rafael Silva | Criação baseada em análise estática do módulo |
| 2026-05-20 | Rafael Silva | v1.1: correção provider Gemini (não Claude); MetricsService expansão (não criação); RF13 → RF14 documentação playbook nullable; novos RF15 (cancel propagation) e RF16 (cleanup backup); critérios verificáveis com comandos; cronograma +20% buffer; riscos com responsável |
