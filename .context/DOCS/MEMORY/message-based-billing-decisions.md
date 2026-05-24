# Decisões Técnicas — Bilhetagem por Mensagens IA

**Feature:** message-based-billing
**Data:** 2026-05-23
**Autor:** BUILDER (Fase 5)

---

## Estado da Implementação

### Fases Concluídas
- ✅ **Fase 2 — Design:** Wireframe completo com 4 estados da barra + modal + fluxo
- ✅ **Fase 3 — Backend (api/):**
  - 5 migrations criadas e validadas
  - 2 models novos (`TenantMessageUsage`, `AiMessageUsageFailedLog`)
  - 1 enum (`OverageMode`)
  - 1 DTO (`CheckAndIncrementResult`)
  - 3 services (`BillingCycleCalculator`, `UsageCounterService`, `ThresholdChecker`)
  - 3 controllers + 2 FormRequests + rotas
  - 4 jobs (`CheckUsageThresholdsJob`, `SendUsageAlertJob`, `CloseExpiredCyclesJob`, `ReconcileFailedUsageJob`)
  - 2 templates de email (Blade)
  - Testes: 100% dos novos services e endpoints cobertos
- ✅ **Fase 4 — Frontend (app/):**
  - Modelos TS estendidos
  - `BillingPrefsService` criado
  - `UsageStatsComponent` com barra de mensagens IA
  - `BillingPrefsModalComponent` standalone
  - `PlanCardComponent` com bullet IA
  - Testes: 5 novos specs passando

### Fases Concluídas (atualizado)
- ✅ **Fase 3.5G — Gateway:** Implementada em 2026-05-24:
  - `BillingUsageClient` (HTTP client com retry 3x + fail-open)
  - Endpoint fail-open `POST /v1/billing/usage/fail-open-log`
  - Integração no pipeline IA (`run-completion.service.ts`)
  - Geração de `aiTurnId` via `randomUUID()` no orquestrador
  - Módulo + DI wiring (`BillingModule` ↔ `AIModule`)
  - Métricas Prometheus (`ai_messages_total`, `usage_check_duration_seconds`, `usage_check_failures_total`)
  - Testes: 11/11 passaram (gateway) + 4/4 passaram (API fail-open)

### Fases Pendentes
- ⏳ **Fase 5 — Cenários manuais (5.1.x):** Desbloqueados — podem ser executados agora
- ⏳ **TASK-5.2.2 (Gate gateway):** Desbloqueado — pode ser executado
- ⏳ **TASK-5.2.5 (Code review):** Aguardando execução

---

## Resultados dos Gates

### API
```
✅ Gate 1/4: Lint OK
✅ Gate 2/4: Types OK
✅ Gate 3/4: Testes OK (2923 passed, 9664 assertions, 1 skipped)
✅ Gate 4/4: Refatoração OK (Rector OK)
Tempo: ~234s
```

### APP
```
✅ Build OK (15.422s, bundle 2.30MB)
⚠️  Testes: 1195/1197 passaram
   - 2 falhas em webchat-page.component.spec.ts (NÃO relacionado à feature)
   - Todos os testes da feature billing passam
```

### Gateway (atualizado 2026-05-24)
```
✅ Build OK
✅ Testes específicos: 11/11 passaram
   - BillingUsageClient: 6/6
   - RunCompletionService: 5/5
✅ API FailOpen Endpoint: 4/4 passaram
⚠️  Suite completa gateway: 119 suites passaram (1366 testes)
   - 6 suites falharam (32 testes) — todos e2e por variáveis de ambiente ausentes
   - Nenhuma falha relacionada à feature billing
```

### Verificação de Dependências
```
✅ Gateway não importa pg/postgres/knex/typeorm/prisma
✅ Gateway não tem migrations
✅ API tem 7 migrations (5 da feature + 2 fixes FK)
```

---

## Descobertas e Decisões

1. **Falhas pré-existentes no APP:** Testes do `WebChatPageComponent` falham (2 testes de session restoration) independentemente das alterações da feature. Não impedem merge.

2. **Gateway implementado:** Todo o enforcement de quota está funcional. Cenários manuais end-to-end agora podem ser validados.

3. **Backend e gateway integrados:** Endpoints (`check-and-increment`, `subscription/me`, `billing-prefs`, `fail-open-log`) todos testados e funcionando.

4. **Frontend pronto:** Toda a UI de billing implementada e testada. Integrada com backend via subscription endpoint.

5. **Decisões técnicas do gateway:**
   - Retry exponencial manual (200ms→1s→5s) sem bibliotecas externas
   - Fail-open seguro: retorna `allowed=true` em falha total para não bloquear clientes por erro de infra
   - `BillingUsageMetrics` como classe separada extendendo o registry do `MetricsService`
   - Handoff em quota exceeded via `transfer_to_human` tool call com flag `blocked_by_quota`
   - `aiTurnId` gerado via `crypto.randomUUID()` e propagado pelo pipeline

---

## Próximos Passos

1. **Executar Fase 5 (Integration):**
   - `TASK-5.2.2`: Gate gateway completo
   - `TASK-5.1.1`: Cenário 1 — Limite stop
   - `TASK-5.1.2`: Cenário 2 — Reset aniversário
   - `TASK-5.1.3`: Cenário 3 — Troca plano mid-cycle
   - `TASK-5.2.5`: Code review final com `code-review-confiavel`

2. **Comando para próxima ação:**
   ```
   /prevec-execute-task message-based-billing TASK-5.2.2
   ```
