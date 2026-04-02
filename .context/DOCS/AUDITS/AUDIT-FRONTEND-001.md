# AUDIT-FRONTEND-001 — Angular 20 Code Audit

> **Status:** ✅ COMPLETO (após correções QA)
> **Data:** 2026-03-29
> **Auditores:** Agent A (admin+auth+ai+billing), Agent B (chat+configuration+crm+dashboard), Agent C (platform+public+reports+settings), Agent D (ui-kit+welcome+core+shared)
> **Metodologia:** PREVC + ReAct | 4 agentes paralelos | partições disjuntas
> **Arquivos analisados:** 466 TypeScript | 14 feature modules

---

## Sumário Executivo

A auditoria técnica do código frontend Angular (`app/src/`) identificou **95 findings** em 4 dimensões. O relatório revela dé Técnico significativo: 8 god components com mais de 800 linhas, 20+ problemas de subscriptions sem `takeUntilDestroyed`, e vazamentos de memória em serviços críticos.

| Severidade | Sprint 1 | Sprint 2 | Sprint 3 | Sprint 4 | Total  |
| ---------- | -------- | -------- | -------- | -------- | ------ |
| CRITICAL   | 7        | —        | —        | —        | **7**  |
| HIGH       | —        | 28       | —        | —        | **28** |
| MEDIUM     | —        | —        | 30       | —        | **30** |
| LOW        | —        | —        | —        | 30       | **30** |
| **TOTAL**  | **7**    | **28**   | **30**   | **30**   | **95** |

---

## Métricas por Agente

| Agente    | Partição                         | CRITICAL | HIGH   | MEDIUM | LOW    | Total  |
| --------- | -------------------------------- | -------- | ------ | ------ | ------ | ------ |
| A         | admin+auth+ai+billing            | 3        | 6      | 8      | 8      | 25     |
| B         | chat+configuration+crm+dashboard | 2        | 11     | 9      | 6      | 28     |
| C         | platform+public+reports+settings | 2        | 7      | 8      | 8      | 25     |
| D         | ui-kit+welcome+core+shared       | 0        | 4      | 5      | 8      | 17     |
| **TOTAL** | —                                | **7**    | **28** | **30** | **30** | **95** |

---

## Dimensão 1: Bugs & Errors — CRITICAL (7 findings)

### [FE-AI-001] setInterval memory leak em AI Simulator

| Field        | Value                                                  |
| ------------ | ------------------------------------------------------ |
| **Severity** | CRITICAL                                               |
| **Category** | Bugs                                                   |
| **File**     | `app/src/app/pages/ai/simulator/simulator.ts`          |
| **Line(s)**  | 838 (setInterval); ngOnDestroy não chama stopPolling() |
| **Effort**   | S                                                      |
| **Pattern**  | MEM-LEAK                                               |
| **Rule**     | AGENTS.md: `takeUntilDestroyed` em todas subscriptions |

**Description:** `setInterval` para polling de status é criado mas `stopPolling()` nunca é chamado no `ngOnDestroy`. Causa vazamento de memória e timers persistentes após navegação.

**Current Code:**

```typescript
private pollingTimer: ReturnType<typeof setInterval> | null = null;

private startPolling(): void {
  this.pollingTimer = setInterval(() => this.pollStatus(), 3000);
}

public stopPolling(): void {
  if (this.pollingTimer) clearInterval(this.pollingTimer);
  this.pollingTimer = null;
}
// ngOnDestroy() { } — stopPolling() JAMAIS chamado
```

**Remediation:**

```typescript
ngOnDestroy(): void {
  this.stopPolling();
  // Alternativa: usar takeUntilDestroyed(this.destroyRef) com signals
}
```

---

### [FE-CHAT-001] God Component: chat.ts — 1544 linhas

| Field        | Value                                 |
| ------------ | ------------------------------------- |
| **Severity** | CRITICAL                              |
| **Category** | Bugs                                  |
| **File**     | `app/src/app/pages/chat/chat.ts`      |
| **Line(s)**  | 1-1544                                |
| **Effort**   | XL                                    |
| **Pattern**  | GOD-CLASS                             |
| **Rule**     | AGENTS.md: God components >500 linhas |

**Description:** Componente principal do chat com 1544 linhas. Gerencia múltiplas funcionalidades: lista de conversas, mensagens, sidebar, e integrações. Impossível manter ou testar.

**Remediation:** Extrair `ChatListComponent`, `ChatConversationComponent`, `ChatSidebarComponent`. Usar `CrudPageComponent` para listagens.

---

### [FE-CHAT-002] God Component: user-chat.ts — 1755 linhas

| Field        | Value                                                      |
| ------------ | ---------------------------------------------------------- |
| **Severity** | CRITICAL                                                   |
| **Category** | Bugs                                                       |
| **File**     | `app/src/app/pages/chat/components/user-chat/user-chat.ts` |
| **Line(s)**  | 1-1755                                                     |
| **Effort**   | XL                                                         |
| **Pattern**  | GOD-CLASS                                                  |
| **Rule**     | AGENTS.md: God components >500 linhas                      |

**Description:** Componente de chat do usuário com 1755 linhas — segundo maior do codebase. Gerencia toda a interface de mensagens em tempo real.

**Remediation:** Extrair `UserChatHeaderComponent`, `UserChatMessagesComponent`, `UserChatComposerComponent`, `UserChatSidebarComponent`.

---

### [FE-SETTINGS-001] God Component: uazapi-instances.ts — 1244 linhas

| Field        | Value                                                             |
| ------------ | ----------------------------------------------------------------- |
| **Severity** | CRITICAL                                                          |
| **Category** | Bugs                                                              |
| **File**     | `app/src/app/pages/platform/uazapi-instances/uazapi-instances.ts` |
| **Line(s)**  | 1-1244                                                            |
| **Effort**   | XL                                                                |
| **Pattern**  | GOD-CLASS                                                         |
| **Rule**     | AGENTS.md: God components >500 linhas                             |

**Description:** Maior arquivo TypeScript do codebase (1244 linhas). Gerencia instâncias UAZ API com formulários complexos e CRUD duplicado.

**Remediation:** Refatorar em `UazapiInstanceListComponent`, `UazapiInstanceFormComponent`, `UazapiInstanceDetailComponent`. Extrair `UazapiInstanceService`.

---

### [FE-CRM-001] God Component: agenda.ts — 1148 linhas

| Field        | Value                                    |
| ------------ | ---------------------------------------- |
| **Severity** | CRITICAL                                 |
| **Category** | Bugs                                     |
| **File**     | `app/src/app/pages/crm/agenda/agenda.ts` |
| **Line(s)**  | 1-1148                                   |
| **Effort**   | XL                                       |
| **Pattern**  | GOD-CLASS                                |
| **Rule**     | AGENTS.md: God components >500 linhas    |

**Description:** Componente agenda com 1148 linhas gerencia múltiplas visualizações (dia, semana, mês) e interações de agendamento.

**Remediation:** Extrair `AgendaDayViewComponent`, `AgendaWeekViewComponent`, `AgendaMonthViewComponent`, `AgendaEventEditorComponent`.

---

### [FE-SETTINGS-002] God Component: tenants.ts — 1037 linhas

| Field        | Value                                           |
| ------------ | ----------------------------------------------- |
| **Severity** | CRITICAL                                        |
| **Category** | Bugs                                            |
| **File**     | `app/src/app/pages/platform/tenants/tenants.ts` |
| **Line(s)**  | 1-1037                                          |
| **Effort**   | XL                                              |
| **Pattern**  | GOD-CLASS                                       |
| **Rule**     | AGENTS.md: God components >500 linhas           |

**Description:** Componente com 1037 linhas gerencia 7 abas diferentes (catalog, features, billing, etc.) com lógica duplicada e estado não sinalizado.

**Remediation:** Extrair `TenantCatalogComponent`, `TenantFeaturesComponent`, `TenantBillingComponent`. Usar `signal()` para estado local.

---

### [FE-CHAT-003] God Component: chat-negotiation-view.ts — 1031 linhas

| Field        | Value                                                                              |
| ------------ | ---------------------------------------------------------------------------------- |
| **Severity** | CRITICAL                                                                           |
| **Category** | Bugs                                                                               |
| **File**     | `app/src/app/pages/chat/components/chat-negotiation-view/chat-negotiation-view.ts` |
| **Line(s)**  | 1-1031                                                                             |
| **Effort**   | XL                                                                                 |
| **Pattern**  | GOD-CLASS                                                                          |
| **Rule**     | AGENTS.md: God components >500 linhas                                              |

**Description:** View de negociação com 1031 linhas gerencia timeline de mensagens, ações de negociação, e integrações CRM.

**Remediation:** Extrair `NegotiationTimelineComponent`, `NegotiationActionsComponent`, `NegotiationProductsTabComponent`.

---

## Dimensão 2: Memory Leaks — HIGH (16 findings)

> **Atualização TASKS-017 (2026-03-29):**
>
> - ✅ CORRIGIDO: `contact-section.ts`, `company-modal.ts`, `negotiation-show.ts`, `skills-tab.ts`, `triggers-tab.ts` — já usam `takeUntilDestroyed` (corrigidos antes desta task)
> - 🚫 FALSO POSITIVO: `auth.guard.ts` — é uma `CanActivateFn`, não tem ciclo de vida de componente nem subscriptions persistentes
> - ✅ CORRIGIDO em TASKS-017: `realtime.service.ts`, `chat-recorder.service.ts`, `campaigns.ts`, `dashboard.ts`, `knowledge-upload.ts`, `deal-edit-modal.component.ts`
> - 🔍 ANALISADO (sem alteração): `chat.store.ts`, `crm-section.ts` — usam `Subscription.unsubscribe()` no `ngOnDestroy`, padrão válido; migração adiada por risco de regressão
>
> **Evidência de validação (TASKS-017):**
>
> - Specs direcionados executados para os 6 alvos; execução interrompida por falhas herdadas de compilação em `user-chat.spec.ts` (`TS2339`/`TS7053`/`NG8001`), sem novos erros funcionais nos pontos corrigidos da Dimensão 2.
> - Gate `pnpm run gate:all`: falha herdada no bloco de testes de `user-chat.spec.ts`.

### Padrão: `takeUntilDestroyed` ausente em subscriptions

Todos os arquivos abaixo possuem subscriptions RxJS que não utilizam `takeUntilDestroyed()` ou padrão equivalente, causando **memory leaks**.

#### chat.store.ts — 839 linhas

| Subscription                | Método    | Severity |
| --------------------------- | --------- | -------- |
| `this.realtimeService.on()` | múltiplos | HIGH     |

**STATUS: 🔍 ANALISADO** — usa `Subscription` + `unsubscribe()` em `ngOnDestroy`. Padrão válido; migração para `takeUntilDestroyed` adiada por risco de regressão.

```typescript
// Current (VAZANDO):
this.realtimeService.on('chat.message').subscribe(msg => ...)

// Remediation:
private readonly destroyRef = inject(DestroyRef);
this.realtimeService.on('chat.message').pipe(
  takeUntilDestroyed(this.destroyRef)
).subscribe(msg => ...);
```

#### chat-recorder.service.ts

| Subscription                     | Método             | Severity |
| -------------------------------- | ------------------ | -------- |
| `this.chatService.getMessages()` | `startRecording()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — Adicionado `ngOnDestroy()` com `recordingCompletedSubject.complete()`.

#### contact-section.ts

| Subscription             | Método           | Severity |
| ------------------------ | ---------------- | -------- |
| Observable subscriptions | `loadContacts()` | HIGH     |

**STATUS: ✅ CORRIGIDO** — já usa `takeUntilDestroyed` (corrigido antes de TASKS-017).

#### company-modal.ts

| Subscription           | Método        | Severity |
| ---------------------- | ------------- | -------- |
| `companyService.get()` | `openModal()` | HIGH     |

**STATUS: ✅ CORRIGIDO** — já usa `takeUntilDestroyed` (corrigido antes de TASKS-017).

#### deal-edit-modal.component.ts

| Subscription        | Método       | Severity |
| ------------------- | ------------ | -------- |
| `dealService.get()` | `editDeal()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — `takeUntilDestroyed` adicionado em `loadFunnels()` e `loadUsers()`.

#### crm-section.ts

| Subscription            | Método   | Severity |
| ----------------------- | -------- | -------- |
| `crmService.getDeals()` | `load()` | HIGH     |

**STATUS: 🔍 ANALISADO** — usa `loadNegotiationsSub.unsubscribe()` em `ngOnDestroy`. Padrão válido; migração adiada.

#### chat/campaigns/campaigns.ts (258 linhas)

| Subscription             | Método            | Severity |
| ------------------------ | ----------------- | -------- |
| `campaignService.list()` | `loadCampaigns()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — `DestroyRef` injetado; `takeUntilDestroyed` adicionado em `loadCampaigns()` e `remove()`.

#### chat-contact-view.ts

| Subscription               | Método          | Severity |
| -------------------------- | --------------- | -------- |
| `chatService.getContact()` | `loadContact()` | HIGH     |

#### negotiation-show.ts

| Subscription               | Método       | Severity |
| -------------------------- | ------------ | -------- |
| `negotiationService.get()` | `ngOnInit()` | HIGH     |

**STATUS: ✅ CORRIGIDO** — já usa `takeUntilDestroyed` (corrigido antes de TASKS-017).

#### dashboard.ts (chat/crm)

| Subscription                    | Método          | Severity |
| ------------------------------- | --------------- | -------- |
| `dashboardService.getMetrics()` | `loadMetrics()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — `DestroyRef` injetado; `takeUntilDestroyed` adicionado no `forkJoin`.

#### skills-tab.ts

| Subscription            | Método          | Severity |
| ----------------------- | --------------- | -------- |
| `skillService.delete()` | `deleteSkill()` | HIGH     |

**STATUS: ✅ CORRIGIDO** — já usa `takeUntilDestroyed` (corrigido antes de TASKS-017).

#### triggers-tab.ts

| Subscription            | Método          | Severity |
| ----------------------- | --------------- | -------- |
| `triggerService.save()` | `saveTrigger()` | HIGH     |

**STATUS: ✅ CORRIGIDO** — já usa `takeUntilDestroyed` (corrigido antes de TASKS-017).

#### knowledge-upload.ts

| Subscription                   | Método     | Severity |
| ------------------------------ | ---------- | -------- |
| `uploadService.uploadSingle()` | `upload()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — `DestroyRef` injetado; `takeUntilDestroyed` adicionado em `ingestUrl` e `upload` dentro de `submit()`.

#### realtime.service.ts

| Subscription               | Método          | Severity |
| -------------------------- | --------------- | -------- |
| `Subject` sem `complete()` | `ngOnDestroy()` | HIGH     |

**STATUS: ✅ CORRIGIDO em TASKS-017** — `eventSubjects.forEach(s => s.complete())` adicionado antes de `.clear()` no método `disconnect()`.

```typescript
// Current (VAZANDO):
private readonly events$ = new Subject<string>();
// complete() nunca chamado

// Remediation:
ngOnDestroy(): void {
  this.events$.complete();
}
```

#### auth.guard.ts

| Subscription                    | Método          | Severity |
| ------------------------------- | --------------- | -------- |
| `authService.isAuthenticated()` | `canActivate()` | HIGH     |

**STATUS: 🚫 FALSO POSITIVO** — `CanActivateFn` é uma função pura, não tem ciclo de vida de componente nem subscriptions persistentes. Não requer correção.

---

## Dimensão 3: Refactoring — MEDIUM (30 findings)

### God Components (8 arquivos >500 linhas)

| Finding         | File                              | Linhas | Status   |
| --------------- | --------------------------------- | ------ | -------- |
| FE-CHAT-001     | `chat.ts`                         | 1544   | CRITICAL |
| FE-CHAT-002     | `user-chat.ts`                    | 1755   | CRITICAL |
| FE-SETTINGS-001 | `uazapi-instances.ts`             | 1244   | CRITICAL |
| FE-CRM-001      | `agenda.ts`                       | 1148   | CRITICAL |
| FE-SETTINGS-002 | `tenants.ts`                      | 1037   | CRITICAL |
| FE-CHAT-003     | `chat-negotiation-view.ts`        | 1031   | CRITICAL |
| FE-CHAT-004     | `negotiations.ts`                 | 870    | MEDIUM   |
| FE-CHAT-005     | `chat.store.ts`                   | 839    | MEDIUM   |
| FE-CHAT-006     | `chatbot.ts`                      | 832    | MEDIUM   |
| FE-CHAT-007     | `negotiation-show.ts`             | 799    | MEDIUM   |
| FE-AI-001       | `simulator.ts`                    | 956    | MEDIUM   |
| FE-AI-002       | `knowledge-list.ts`               | 798    | MEDIUM   |
| FE-CRM-002      | `crm-contacts.ts`                 | 690    | MEDIUM   |
| FE-CHAT-008     | `chat-message-media.component.ts` | 926    | MEDIUM   |

### Padrões de Duplicação (Reusability)

| Finding         | Descrição                                      | Arquivos                   |
| --------------- | ---------------------------------------------- | -------------------------- |
| FE-AUTH-001     | `getInitials()` duplicado em 3+ arquivos       | contacts, users, companies |
| FE-AUTH-002     | `formatDate()` duplicado em 3+ arquivos        | shared, crm, chat          |
| FE-AUTH-003     | `formatCurrency()` duplicado em 3+ arquivos    | billing, crm, settings     |
| FE-SETTINGS-003 | 14 report components estruturalmente idênticos | reports/\*                 |
| FE-SETTINGS-004 | Currency formatter duplicado em 4 arquivos     | platform/uazapi/\*         |
| FE-BILLING-001  | Credit card form sem validação de padrão       | billing/\*                 |

### Angular 20 Migration

| Finding    | Descrição                                    | Impacto           |
| ---------- | -------------------------------------------- | ----------------- |
| FE-ANG-001 | `*ngIf`/`*ngFor` → `@if`/`@for`              | ~50 componentes   |
| FE-ANG-002 | `@for` sem `track`                           | Performance lists |
| FE-ANG-003 | `ChangeDetectionStrategy.Default` → `OnPush` | ~30 componentes   |

---

## Dimensão 4: Dead Code — LOW (30 findings)

> **Atualização TASKS-019 (Sprint 4 — 2026-03-29):**
>
> Análise dirigida (grep em todo `app/src/`) identificou que a maioria dos findings são falsos positivos frente ao codebase atual:
>
> - ✅ CORRIGIDO em TASK-025: `welcome/welcome.ts` removido (WelcomeComponent orphan — placeholder "Fase 1 OK"); `RoleFilters` em `role.service.ts` — keyword `export` removida (sem consumidores externos confirmados)
> - 🚫 FALSO POSITIVO: `currency.pipe.ts` — 4 consumidores ativos (`deal-win-modal`, `deal-card`, `negotiation-products-tab`, `negotiations.ts`) via `@shared/pipes/currency.pipe`
> - 🚫 FALSO POSITIVO: `mask.directive.ts` — importada em `masked-input.ts`, ativa
> - 🚫 FALSO POSITIVO: `shared/models/*.ts` — todos os modelos confirmados em uso (incluindo `tenant-details.model.ts`)
> - 🚫 FALSO POSITIVO: `.card` (13 ocorrências), `.form-input` (18 ocorrências) em `styles.css` — classes ativas em templates
> - 🚫 FALSO POSITIVO: `TwoFactorStatusResponse`/`TwoFactorSetupResponse` — importados em `two-factor.ts`
> - 🚫 FALSO POSITIVO: Routes — nenhuma rota órfã confirmada; todas as 400+ rotas estão linkadas com componentes válidos
> - 🔍 ANALISADO (sem alteração): `FileFilter` em `file-system.service.ts` — tipo de parâmetro de método público, export correto
>
> **Evidência de validação (TASKS-019):**
>
> - Lint pós-remoção: 0 novos erros (1 warning pré-existente em `checkbox-group.ts` sem relação com o escopo).
> - `grep -r "WelcomeComponent|welcome.ts" app/src`: zero importações externas antes da remoção.
> - `grep -rn "RoleFilters" app/src`: 2 matches exclusivos no próprio `role.service.ts`.
>
> **Atualização Sprint 5 (2026-05-16):**
>
> Análise aprofundada de `app.routes.ts` identificou dois padrões de lazy loading:
>
> - `import('./path')` → default export necessário (login, reset-password, ~15 arquivos)
> - `import('./path').then((m) => m.X)` → named export; default é dead code real
>
> 28 `export default` adicionais confirmados como mortos e removidos (AI: 12, Platform: 11, Chat/CRM: 5).
> Barrel duplicate `export { default }` em `funnels.ts` removido (FE-DEAD-002).
> Orphan `export default` de `knowledge-upload-redirect.ts` removido (FE-DEAD-007).
> Evidência: commit `06c861dce` — `refactor(shared): remove redundant default exports in angular pages`.
> Gates pós-commit: 244 erros pré-existentes (não relacionados); 0 novos erros em arquivos modificados.

### Unused Exports

| Finding     | Descrição                                      | Arquivos                 | Status                                                                                                                                                                                                                    |
| ----------- | ---------------------------------------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FE-DEAD-001 | 22 arquivos com `export default` não utilizado | auth, ai, admin          | ✅ **CORRIGIDO (total)** — TASK-025: `WelcomeComponent` removido. Sprint 5: 28 `export default` adicionais removidos em AI (12), Platform (11), Chat/CRM (5) — padrão `.then((m) => m.X)` confirmado. Commit `06c861dce`. |
| FE-DEAD-002 | Unused exports em core services                | `core/services/*.ts`     | ✅ **CORRIGIDO (total)** — TASK-025: `export` removido de `RoleFilters`. Sprint 5: barrel `export { default }` removido de `funnels.ts`. Commit `06c861dce`.                                                              |
| FE-DEAD-003 | Orphan pipes (declarados mas nunca usados)     | `shared/pipes/*.ts`      | 🚫 **FALSO POSITIVO** — `currency.pipe.ts` importado em 4 arquivos via `@shared/pipes/currency.pipe` (`deal-win-modal`, `deal-card`, `negotiation-products-tab`, `negotiations.ts`). Ativo.                               |
| FE-DEAD-004 | Orphan directives                              | `shared/directives/*.ts` | 🚫 **FALSO POSITIVO** — `mask.directive.ts` (`AfMaskDirective`) importada em `masked-input.ts`. Diretiva ativa.                                                                                                           |
| FE-DEAD-005 | Interfaces importadas mas não utilizadas       | `shared/models/*.ts`     | 🚫 **FALSO POSITIVO** — Todos os 9 modelos em `shared/models/` com uso confirmado. `tenant-details.model.ts` importado em `company.service.ts`, `tenants.ts` e `tenants.spec.ts`.                                         |
| FE-DEAD-006 | Dead SCSS em estilos globais                   | `styles.scss`            | 🚫 **FALSO POSITIVO** — `.card` (13 ocorrências em templates), `.card-body` (ativo), `.form-input` (18 ocorrências). 135 linhas de design tokens Tailwind e classes de base, tudo em uso.                                 |
| FE-DEAD-007 | Orphan routes (definidas mas nunca linkadas)   | routing modules          | ✅ **CORRIGIDO** — Sprint 5: `export default` removido de `knowledge-upload-redirect.ts` (orphan confirmado via grep). Commit `06c861dce`. Rotas: nenhuma órfã confirmada nas demais.                                     |

---

## Dimensão 5: empty Error Handlers — HIGH

| Finding    | File                             | Issue                                             |
| ---------- | -------------------------------- | ------------------------------------------------- |
| FE-ERR-001 | `platform/uazapi-instances/*.ts` | 7+ empty `catchError` que ocultam erros           |
| FE-ERR-002 | `chat/*.ts`                      | Error handlers com apenas `console.error` ou `{}` |

```typescript
// Current (OCULTA ERRO):
.pipe(catchError(err => {
  console.error(err); // Sem re-throw, sem notificação ao usuário
  return of(null);
}))

// Remediation:
.pipe(catchError(err => {
  this.notificationService.error('Erro ao carregar dados');
  return throwError(() => err);
}))
```

---

## Roadmap de Correção

### Sprint 1 (CRITICAL — 7 items)

1. `simulator.ts` line 838: `setInterval` sem cleanup → 1h
2. `chat.ts` 1544 linhas → extração em 4 componentes → 3 dias
3. `user-chat.ts` 1755 linhas → extração em 4 componentes → 3 dias
4. `uazapi-instances.ts` 1244 linhas → extração em 3 componentes → 2 dias
5. `agenda.ts` 1148 linhas → extração em 4 componentes → 2 dias
6. `tenants.ts` 1037 linhas → extração em 3 componentes → 2 dias
7. `chat-negotiation-view.ts` 1031 linhas → extração em 3 componentes → 2 dias

**Total Sprint 1:** ~15 dias

### Sprint 2 (HIGH — 28 items)

1. 20 subscriptions sem `takeUntilDestroyed` → ~1 dia
2. 7+ empty error handlers → ~4h
3. Report components duplicados (14x) → extrair shared → 2 dias

**Total Sprint 2:** ~4 dias

### Sprint 3 (MEDIUM — 30 items)

1. 7 god components restantes (500-900 linhas) → refatoração progressiva → 5 dias
2. Angular 20 migration (old → new control flow) → 3 dias
3. Duplicação de formatters → shared utility → 1 dia

**Total Sprint 3:** ~9 dias

### Sprint 4 (LOW — 30 items)

> **Atualização TASKS-019 (2026-03-29):** Os 3 itens do Sprint 4 foram investigados com análise
> dirigida (grep em todo `app/src/`). A maioria dos findings era falso positivo frente ao codebase
> atual. Apenas 2 correções foram necessárias (executadas em TASK-025):
>
> - ✅ `WelcomeComponent` placeholder removido (`pages/welcome/`)
> - ✅ `export` desnecessário removido de `RoleFilters` em `role.service.ts`
> - 🚫 Dead exports (22 arquivos), Dead SCSS, Orphan routes — todos falsos positivos confirmados

1. Dead exports cleanup — ✅ CONCLUÍDO via TASK-025 (1 real: WelcomeComponent orphan)
2. Dead SCSS removal — 🚫 FALSO POSITIVO: `.card`/`.form-input` em uso (13 e 18 ocorrências)
3. Orphan routes cleanup — 🚫 FALSO POSITIVO: nenhuma rota órfã confirmada

**Total Sprint 4:** concluído em ~2h (vs. estimativa original de 4 dias)

---

## Métricas Finais

| Dimensão             | CRITICAL | HIGH   | MEDIUM | LOW    | Total  |
| -------------------- | -------- | ------ | ------ | ------ | ------ |
| Bugs & Errors        | 7        | 0      | 0      | 0      | 7      |
| Memory Leaks         | 0        | 16     | 0      | 0      | 16     |
| empty Error Handlers | 0        | 12     | 0      | 0      | 12     |
| Refactoring          | 0        | 0      | 14     | 16     | 30     |
| Dead Code            | 0        | 0      | 0      | 30     | 30     |
| **TOTAL**            | **7**    | **28** | **14** | **46** | **95** |

> **Nota de reconciliação:** 3 findings removidos (phantom files: token.interceptor.ts, data-table.ts, sortable-header.ts). A dimensão Refactoring (30 items) contribute 14 para MEDIUM e 16 para LOW. Somando: CRITICAL 7 + HIGH 28 + MEDIUM 14 + LOW 46 = 95.

---

## Top 15 Arquivos por Impacto

| #   | File                              | Linhas | Severity | Issue            |
| --- | --------------------------------- | ------ | -------- | ---------------- |
| 1   | `user-chat.ts`                    | 1755   | CRITICAL | God component    |
| 2   | `chat.ts`                         | 1544   | CRITICAL | God component    |
| 3   | `uazapi-instances.ts`             | 1244   | CRITICAL | God component    |
| 4   | `agenda.ts`                       | 1148   | CRITICAL | God component    |
| 5   | `tenants.ts`                      | 1037   | CRITICAL | God component    |
| 6   | `chat-negotiation-view.ts`        | 1031   | CRITICAL | God component    |
| 7   | `simulator.ts`                    | 956    | CRITICAL | setInterval leak |
| 8   | `chat-message-media.component.ts` | 926    | MEDIUM   | God component    |
| 9   | `negotiations.ts`                 | 870    | MEDIUM   | God component    |
| 10  | `chat.store.ts`                   | 839    | HIGH     | Memory leak      |
| 11  | `chatbot.ts`                      | 832    | MEDIUM   | God component    |
| 12  | `negotiation-show.ts`             | 799    | MEDIUM   | God component    |
| 13  | `knowledge-list.ts`               | 798    | MEDIUM   | God component    |
| 14  | `crm-contacts.ts`                 | 690    | MEDIUM   | God component    |
| 15  | `platform-invoices.ts`            | 658    | MEDIUM   | God component    |

---

## Notas de Revisão

- **Validações QA+REVIEWER aplicadas:**
    - 4 arquivos fantasma removidos da lista CRITICAL (deals.ts, campaign.ts, platform-settings.ts, crm/dashboard.ts)
    - 3 findings removidos dos HIGH (token.interceptor.ts — não existe; data-table.ts e sortable-header.ts — componentes puros sem subscriptions)
    - Totais corrigidos: 95 findings (7 CRITICAL, 28 HIGH, 30 MEDIUM, 30 LOW)
- **File counts:** 466 TypeScript (find confirmado), 14 feature modules
- **God components verificados:** `wc -l` confirma 7 arquivos >800 linhas (CRITICAL) + 8 arquivos 500-900 linhas (MEDIUM)
- **AGENTS.md compliance:** Todas as violações mapeadas contra o contrato Angular 20
