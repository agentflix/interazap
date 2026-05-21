# Feature: autopilot-stability

**Status:** [x] Em planejamento | [ ] Em execução | [ ] Concluída
**Data:** 2026-05-20
**PRD:** `.context/DOCS/PRDS/0001-PRD-autopilot-stability.md`

## Metadados
- ID: FEAT-001
- PRD: .context/DOCS/PRDS/0001-PRD-autopilot-stability.md
- Bounded Context: ai/autopilot (api/src/Domain/Ai + gateway/src/domains/ai)
- Complexidade: G
- Status: 🟡 Em Planning

## Visão Geral

Corrigir 16 gaps de estabilidade, segurança e observabilidade no módulo Autopilot identificados via análise estática. Elimina runs zombie, race conditions no Redis lock, guardrails inoperantes, métricas incompletas, código morto (`AiAutopilotTriggerLog`) e cancelamento não propagado. Prepara módulo para produção em escala sem regressões.

## Módulos Afetados

- [x] api/ (Laravel 12) — maioria dos fixes
- [x] gateway/ (NestJS 11) — correlation_id propagation + cancel listener
- [ ] app/ (Angular 20) — sem mudanças
- [ ] Infraestrutura — sem mudanças

## Resumo

Plano correção sólido em 5 fases (Estabilidade → Observabilidade → Testes → Segurança/Limpeza → Polimento). Provider AI é **Gemini** (`@google/generative-ai` no gateway). Reusa `MetricsService` existente, eventos `ai.run.*` registrados e config `ai.stale_run_threshold_minutes`.

## Escopo

### Incluído
- [x] Lock Redis TTL = 300s (igual job timeout)
- [x] `DispatchAutopilotRunJob::failed()` handler + DLQ
- [x] `AutopilotZombieRunCleanupJob` (cron 1min)
- [x] `GuardrailEvaluatorService` consulta `AiAutopilotGuardrail` por tenant (cache 5min)
- [x] `expires_at` em approvals + `AutopilotApprovalExpiryJob` (cron hourly)
- [x] `correlation_id` UUID propagado api ↔ gateway
- [x] `MetricsService` expandido (+4 métodos)
- [x] 7 testes failure path
- [x] Permissions split: `ai.autopilots.view` + `.run` + `.manage`
- [x] Remover `AiAutopilotTriggerLog` (model + referências)
- [x] Sanitização message_body em `AiContextBuilderService`
- [x] Cache snapshot resolver (Redis TTL 60s)
- [x] Documentar `playbook_id` nullable (PHPDoc + `isAdHoc()`)
- [x] Cancelamento propagado: `ai.run.cancel_requested` → gateway aborta
- [x] Remover `api/database/migrations_backup_20260216/`

### Fora de Escopo
- Redesign UI `autopilot-performance-report`
- Novas tools, playbooks ou triggers
- Troca de provider AI (Gemini → outro)
- Refactor de módulos não relacionados (Chat, RAG, Knowledge)
- Otimização de cost LLM (token budgets já implementado)

## Dependências

- **Features**: `ai-tool-parameter-resolution` (já implementado)
- **Módulos**: api/Ai, api/Shared (MetricsService), gateway/ai, Redis 7, PostgreSQL 17
- **Externas**: nenhuma — provider Gemini permanece

## Princípios de Arquitetura

| Princípio | Decisão |
|---|---|
| Tenant isolation | `AiAutopilotGuardrail::where('tenant_id', ...)` em todo lookup DB |
| Gateway sem PostgreSQL | `correlation_id` propagado via payload Redis/BullMQ, não query |
| Idempotência | Lock TTL = job timeout (300s) — sem janela de race |
| Observabilidade | Reusar `MetricsService` existente (não criar paralelo) |
| Compatibilidade | Migrations não destrutivas; backfill `expires_at` com `LEAST()` |
| Zero-downtime | Permission split mantém `manage` como superset temporário |

## Critérios de Aceite

- [ ] `grep -n "'EX'" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` mostra `300`
- [ ] `phpunit --filter=DispatchAutopilotRunJobFailedHandlerTest` passa
- [ ] `phpunit --filter=AutopilotZombieRunCleanupJobTest` passa + scheduler ativo
- [ ] `phpunit --filter=GuardrailEvaluatorServiceDbTest` passa (factory BLOCK aborta run)
- [ ] Migration `expires_at` aplicada — `\d ai_autopilot_approvals` lista coluna
- [ ] `phpunit --filter=AutopilotApprovalExpiryJobTest` passa
- [ ] `correlation_id` aparece em logs Laravel + gateway para mesma run
- [ ] `MetricsService` com 4 novos métodos — `phpunit --filter=MetricsServiceAutopilotTest` passa
- [ ] 7 testes failure path passando
- [ ] `phpunit --filter=AutopilotRoutesAuthTest` cobre 3 perms
- [ ] `grep -rn AiAutopilotTriggerLog api/` retorna 0
- [ ] `phpunit --filter=AiContextBuilderSanitizationTest` passa
- [ ] Segunda chamada snapshot < 60s não dispara queries
- [ ] Model `AiAutopilotRun` tem `isAdHoc()` + PHPDoc
- [ ] Gateway aborta tool loop em `ai.run.cancel_requested`
- [ ] `ls api/database/migrations_backup_20260216` retorna "No such file"

## Fases Estimadas

- [x] **Fase 1 — Planning** ✅
- [ ] **Fase 2 — Design** — N/A (sem UI nova)
- [ ] **Fase 3 — Backend** (api): runtime fixes, guardrails, approvals, metrics, sanitização, cleanup
- [ ] **Fase 4 — Gateway** (NestJS): correlation_id consumer, cancel listener
- [ ] **Fase 5 — Integration** (E2E): testes failure path cross-camada

## Tasks

Ver `.context/DOCS/TASKS/autopilot-stability-tasks.md`

## Notas

### Decisões técnicas relevantes
- TTL Redis = job timeout — trade-off: retry legítimo mesmo msg em 5min bloqueado (idempotência > retry)
- Approval expirado tem status `expired` (não `rejected`) para distinguir cause
- Cache guardrails invalidado via observer em update (Redis pub/sub futuro)
- Permission split com `manage` superset evita break em deploy zero-downtime

### Referências
- Análise inicial: conversa de planning 2026-05-20
- Migration histórico: `2026_03_04` (drop legacy), `2026_03_13` (restore playbooks), `2026_03_22` (nullable), `2026_05_10` (tenant_id Approvals NOT NULL)
- Padrão `failed()` handler: `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php`
- Padrão Rich Format tasks: `.context/DOCS/TASKS/ai-tool-parameter-resolution-tasks.md`
