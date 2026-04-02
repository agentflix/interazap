# PLAN-031-auditoria-modulo-chat — Auditoria Completa do Módulo Chat

## Objetivo

Executar auditoria técnica cross-layer (Backend + Gateway + Frontend) do módulo Chat, identificando erros, oportunidades de refatoração, problemas de performance, falhas de segurança e economia de fluxo/token. Gerar achados categorizados por severidade com plano de correção rastreável.

## Módulo relacionado

Chat | Gateway | Shared

## PRD relacionado (se existir): N/A — Auditoria técnica

## Escopo

### Incluído

- **Backend (api/src/Domain/Chat/)**: 114+ arquivos — Controllers, Actions, Models, DTOs, Services, Policies, Routes, Migrations
- **Gateway (gateway/src/domains/chat/)**: 40+ arquivos — Controllers, Services, DTOs, Providers, Outbound pipeline
- **Frontend (app/src/app/pages/chat/)**: 50+ arquivos — Components, Store, Services, Models, Types
- **Core Services Frontend (app/src/app/core/services/chat-\*.ts)**: 16 serviços
- **Testes**: Feature tests (BE), Spec files (FE), Service specs (GW)

### Excluído

- Módulo Ai (interseção via ChatAutopilotResponder — auditoria separada em PLAN-030)
- Módulo CRM (interseção via ChatContactView — estável)
- Infraestrutura Docker/Redis/PostgreSQL
- Refatoração de god components (TASK-020 já cobre chat.ts)
- Mudanças de schema/migrations (sem breaking changes)

## Evidências da Codebase

### Backend (Chat) — 114+ arquivos, ~15K LOC

- ✅ **100% conformidade AGENTS.md**: strict_types, final class, UUID, $fillable, authorize(), BelongsToTenant
- ✅ 15 Controllers (todos final, 100% authorized)
- ✅ 24 Actions (todos final)
- ✅ 15 Models (todos com BelongsToTenant, UUID, $fillable)
- ✅ 13 DTOs (todos readonly com fromRequest/fromArray)
- ✅ 23 FormRequests (todos com authorize() + rules())
- ✅ 7 Policies (todas com tenant_id check)
- ✅ 10 Resources (eager loading correto)
- ✅ 9 Migrations (partitioned, indexed, FK constraints)
- ✅ 25+ Feature tests
- ⚠️ ChatMessageActions.php — ~1094 LOC (god action, deveria ser decomposta em 3 actions menores)
- ⚠️ ChatWebhookIngestor.php — 200+ LOC (lógica densa de event routing)
- ⚠️ ChatMessageContactRequest — autorização fraca (só verifica tenant_id)
- ⚠️ Eager loading sem seleção de colunas em ChatMessageResource (carrega campos desnecessários do user)

### Gateway (Chat) — 40+ arquivos

- ✅ Circuit breaker em chamadas externas (UazapiClient, ZapiClient)
- ✅ Webhook ACK < 150ms (fire-and-forget + timeout protection)
- ✅ Idempotência via Redis SETNX (TTL 120s pre-ACK, 600s processing)
- ✅ Instance caching multi-nível (memory → Redis active → Redis stale)
- ✅ Provider factory pattern (extensível)
- ✅ Comprehensive test suites
- ❌ Guards ausentes: ChatWebhookController, UazapiInstancesController, UazapiMessagesController, ChatOutboundController
- ❌ Rate limiting ausente em endpoints de webhook público
- ❌ Webhook token exposto em file logger (sem masking)
- ⚠️ ChatWebhookService — 1800+ LOC (god service)
- ⚠️ WebhookEventDto — validação insuficiente (raw sem size limit, sem validação de ao menos um event_type)
- ⚠️ QueueModule importado mas não utilizado
- ⚠️ Logger ausente em ChatOutboundController

### Frontend (Chat) — 50+ arquivos

- ✅ 95% componentes com OnPush
- ✅ 100% inject() em vez de constructor injection
- ✅ 100% takeUntilDestroyed em subscriptions
- ✅ 90% uso de shared components
- ✅ 20+ test files
- ❌ chat.ts — God component (~1189 LOC, 15 services injetados, 10+ realtime listeners) **(excluído do escopo — ver TASK-020)**
- ❌ chat.store.ts — ~3-5 asserções `as unknown as` inseguras (validar contagem exata durante execução)
- ❌ Cache duplicado entre chat-message-cache, chat.store e chat-ticket-list.service
- ⚠️ 70% @for com track (30% sem track)
- ⚠️ Sorting de mensagens executado em cada merge (não incremental)
- ⚠️ Modelos não centralizados em shared/models/ (espalhados em types/, models/, services/)
- ⚠️ chat-message-media — 500+ LOC (componente grande, sem uso de ui-kit)

## Etapas propostas

### Sprint 1 — Segurança & Compliance (P0 CRITICAL)

1. **[GW-SEC-001]** Adicionar guards em controllers desprotegidos do Gateway (XS)
2. **[GW-SEC-002]** Implementar rate limiting em webhook endpoints (XS)
3. **[GW-SEC-003]** Mascarar webhook tokens no file logger (XS)
4. **[GW-VAL-001]** Corrigir validação do WebhookEventDto (S)
5. **[BE-SEC-001]** Fortalecer autorização do ChatMessageContactRequest (XS)

### Sprint 2 — Refatoração & Code Quality (P1 HIGH)

6. **[BE-REF-001]** Decompor ChatMessageActions (~1094 LOC) em 3 actions menores (M)
7. **[GW-REF-001]** Decompor ChatWebhookService em 2-3 services menores (M)
8. **[FE-TYPE-001]** Substituir asserções `unknown` no chat.store.ts por types/guards (S)
9. **[GW-CLEAN-001]** Remover QueueModule não utilizado (XS)
10. **[GW-LOG-001]** Adicionar Logger no ChatOutboundController (XS)

### Sprint 3 — Performance & Economia (P1 HIGH)

11. **[FE-PERF-001]** Unificar camadas de cache (message-cache + store + ticket-list) (M)
12. **[FE-PERF-002]** Otimizar sorting de mensagens (binary insert vs full sort) (S)
13. **[BE-PERF-001]** Adicionar seleção de colunas em eager loading do ChatMessageResource (XS)
14. **[FE-PERF-003]** Adicionar `track` nos 30% de @for faltantes (XS)

### Sprint 4 — Melhorias & Manutenibilidade (P2 MEDIUM)

15. **[FE-ARCH-001]** Centralizar modelos chat em shared/models/ ou pages/chat/models/ (S)
16. **[GW-TEST-001]** Adicionar testes para controllers sem cobertura (S)
17. **[FE-TEST-001]** Expandir testes de integração minimal (S)

## Entregas derivadas

**Entregas:** 4 | **Tasks:** 17

| Entrega | Descrição                    | Tasks                       | Esforço | Status |
| ------- | ---------------------------- | --------------------------- | ------- | ------ |
| 1       | Segurança & Compliance       | TASK-031.1.1 — TASK-031.1.5 | S       | todo   |
| 2       | Refatoração & Code Quality   | TASK-031.2.1 — TASK-031.2.5 | M       | todo   |
| 3       | Performance & Economia       | TASK-031.3.1 — TASK-031.3.4 | M       | todo   |
| 4       | Melhorias & Manutenibilidade | TASK-031.4.1 — TASK-031.4.3 | S       | todo   |

## Technical Approach

### Skills obrigatórios (para tasks FE)

- `.claude/skills/design/SKILL.md` — Tokens visuais
- `.claude/skills/frontend-flow/SKILL.md` — Workflow de implementação
- `.claude/skills/angular-architect/SKILL.md` — Padrões Angular 20+
- `.claude/skills/coding-guidelines/SKILL.md` — Disciplina de código

### Agentes sugeridos por entrega

| Task                    | Agente           | Paralelo com          |
| ----------------------- | ---------------- | --------------------- |
| Sprint 1 (GW-SEC-\*)    | @DEV             | Sprint 1 (BE-SEC-\*)  |
| Sprint 1 (BE-SEC-\*)    | @BACKEND         | Sprint 1 (GW-SEC-\*)  |
| Sprint 2 (BE-REF-\*)    | @BACKEND         | Sprint 2 (FE-TYPE-\*) |
| Sprint 2 (GW-REF-\*)    | @DEV             | —                     |
| Sprint 2 (FE-TYPE-\*)   | @FRONTEND        | Sprint 2 (BE-REF-\*)  |
| Sprint 2 (GW-CLEAN/LOG) | @DEV             | Sprint 2 (qualquer)   |
| Sprint 3 (FE-PERF-\*)   | @FRONTEND        | Sprint 3 (BE-PERF-\*) |
| Sprint 3 (BE-PERF-\*)   | @BACKEND         | Sprint 3 (FE-PERF-\*) |
| Sprint 4 (todos)        | @FRONTEND / @DEV | Paralelo entre si     |

## Riscos e dependências

### Riscos

| Risco                                                       | Probabilidade | Impacto | Mitigação                                                                                              |
| ----------------------------------------------------------- | ------------- | ------- | ------------------------------------------------------------------------------------------------------ |
| Refatoração do ChatWebhookService quebrar webhook flow      | Média         | Alto    | Testes E2E de webhook antes e depois                                                                   |
| Decomposição do ChatMessageActions afetar cursor pagination | Baixa         | Médio   | Testes focados em ChatInitAndCursorPaginationTest                                                      |
| Unificação de cache FE causar regressão em realtime         | Média         | Alto    | Feature flag (chat-rewrite-rollout) para rollback                                                      |
| Guards no webhook bloquearem providers legítimos            | Baixa         | Alto    | Rate limit generoso (1000/min) + token validation (não guard de API key)                               |
| Guards novos no GW quebrarem fluxo interno BE→GW            | Baixa         | Alto    | Validar que InternalApiKeyGuard já é usado nos endpoints chamados pelo BE antes de adicionar nos novos |
| TASK-020 em progresso conflitar com Sprint 3 FE             | Média         | Médio   | Sequenciar: Sprint 3 FE deve aguardar TASK-020 completar ou coordenar merge                            |

### Dependências

- TASK-020 (god-class chat.ts) — Sprint 3/FE depende de não conflitar com refatoração em andamento
- PLAN-030 (Autopilot audit) — Interseção via ChatAutopilotResponder

## Validação e Gates

- [ ] Backend: `composer gate:all` em api/
- [ ] Frontend: `pnpm run gate:all` em app/
- [ ] Gateway: `pnpm lint && pnpm test` em gateway/

## Estimativa

| Item                          | Valor                        |
| ----------------------------- | ---------------------------- |
| Complexidade                  | Média                        |
| Camadas afetadas              | Backend / Gateway / Frontend |
| Migrações necessárias         | Não                          |
| Impacto em módulos existentes | Baixo (isolado ao Chat)      |

## Achados Consolidados

### Resumo por Severidade

| Severidade  | Backend | Gateway | Frontend | Total  |
| ----------- | ------- | ------- | -------- | ------ |
| 🔴 CRITICAL | 0       | 3       | 0        | **3**  |
| 🟠 HIGH     | 2       | 3       | 3        | **8**  |
| 🟡 MEDIUM   | 2       | 2       | 4        | **8**  |
| Total       | 4       | 8       | 7        | **19** |

### Score por Camada

| Camada    | Score      | Nota                                                    |
| --------- | ---------- | ------------------------------------------------------- |
| Backend   | **9.2/10** | Excelente — 100% compliance AGENTS.md, tech debt mínimo |
| Gateway   | **7.0/10** | Bom core, mas falhas de segurança e god service         |
| Frontend  | **7.4/10** | Boa base, mas god component e cache fragmentado         |
| **Média** | **7.9/10** | **Sólido — foco em segurança GW e performance FE**      |
