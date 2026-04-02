# TASKS-020 — Decompor god-class chat.ts (PLAN-020)

---

# TASK-027 — Fase 1: Substituir duplicação MediaRecorder pelo ChatRecorderService

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Eliminar a duplicação inline de `MediaRecorder`/`audioStream`/`recordingTimer` em `chat.ts` (~linhas 247-766) substituindo pelo `ChatRecorderService` já existente. O componente deve usar `state()`, `duration()`, `start()`, `pause()`, `resume()`, `cancel()`, `stop()` e `recordingCompleted$` do service.

## Constraints

- Não alterar o comportamento funcional de gravação e envio de áudio.
- Preservar todos os `@Output()` events que o template usa.
- Manter `ChangeDetectionStrategy.OnPush` após a refatoração.
- `takeUntilDestroyed` em todas as subscriptions.

## Context

- Módulos afetados: Chat
- Dependências: nenhuma (ChatRecorderService já existe e tem spec)
- Referências:
    - `app/src/app/pages/chat/services/chat-recorder.service.ts` — service a consumir
    - `app/src/app/pages/chat/services/chat-recorder.service.spec.ts` — spec existente
    - `app/src/app/pages/chat/chat.ts` — linhas ~247-766 (duplicação a remover)
    - `app/src/app/pages/chat/chat.html` — usar outputs do service

## Etapas

- [ ] Mapear todos os signals/métodos duplicados em chat.ts que correspondem ao ChatRecorderService.
- [ ] Injetar `ChatRecorderService` via `inject()`.
- [ ] Substituir uso inline por `service.state()`, `service.duration()`, `service.start()`, `service.pause()`, `service.resume()`, `service.cancel()`, `service.stop()`.
- [ ] Conectar `service.recordingCompleted$` no construtor para piping de upload/envio de áudio.
- [ ] Remover signals e métodos duplicados de chat.ts.
- [ ] Executar spec `chat-recorder.service.spec.ts` + spec de chat.ts.
- [ ] Atualizar documentação.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-028 — Fase 2: Criar ChatTicketListService e migrar lógica de domínio

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Criar `ChatTicketListService` em `chat/services/chat-ticket-list.service.ts` extraindo lógica de **lista** de tickets que hoje vive em `chat.ts`. **Importante:** `ChatStore` (`chat.store.ts`) já gerencia `selectedCalledId`/`selectedCalled` — este serviço NÃO deve expor `selectedTicketId`.

## Constraints

- Manter `ChangeDetectionStrategy.OnPush` e sinais (`signal()`, `computed()`).
- **Não expor `selectedTicketId`** — esse pertence ao `ChatStore`.
- Não migrar state de UI (modals, dropdowns) — apenas estado de domínio de tickets.
- Preservar todos os contratos de `CalledService`, `CalledMessageService`, `ChatRefreshService`, `RealtimeService`.
- Incluir `takeUntilDestroyed` em todas as subscriptions.

## Context

- Módulos afetados: Chat
- Dependências: TASK-027
- Arquitetura de estado do Chat:
  - `ChatStore` — seleção de ticket (`selectedCalledId`, `selectedCalled`, `startAttendance()`, `fetchCalled()`, `updateContact()`) — **NÃO MODIFICAR**
  - `ChatTicketListService` (este) — lista de tickets (`tickets[]`, `counts`, `loadTickets`, `hydrateTicket`) — **CRIAR**
  - `chat.ts` — estado de UI pura (modals, dropdowns, drag state) — **REFATORAR**
- Referências:
    - `app/src/app/pages/chat/chat.store.ts` — **não modificar**
    - `app/src/app/pages/chat/chat.ts` — métodos de domínio a migrar
    - `app/src/app/core/services/called.service.ts`
    - `app/src/app/core/services/chat-message-cache.service.ts`
    - `app/src/app/core/services/chat-refresh.service.ts`
    - `app/src/app/core/services/realtime.service.ts`

## Etapas

- [ ] Criar `chat/services/chat-ticket-list.service.ts` com `inject()` dos serviços dependidos.
- [ ] Migrar signals: `tickets`, `counts`, `loadingTickets`.
- [ ] Migrar métodos: `loadTickets`, `hydrateTicket`, `deriveCounts`, `setTransientStatusOverride`, `applyTransientStatusOverrides`, `syncSelectedTicketFromRoute`.
- [ ] **Não migrar** `startSelectedTicket`, `confirmCloseSelectedTicket`, `transferTicket` — esses existem no `ChatConversationComponent` via `@Output()` e no `ChatPage` como handlers. `ChatStore.startAttendance()` também cobre parte desse fluxo.
- [ ] Migrar `transientStatusOverrides` e `transientStatusOverrideTimers`.
- [ ] **Não migrar** `selectedTicketId`, `isStartingTicket`, `isClosingTicket` — esses ficam no ChatPage ou usam ChatStore.
- [ ] Manter no ChatPage apenas signals de UI (`isTransferModalOpen`, `isCloseTicketConfirmOpen`, `isEmergencyMenuOpen`, etc).
- [ ] Criar spec `chat-ticket-list.service.spec.ts`.
- [ ] Executar `pnpm run gate:all`.
- [ ] Atualizar documentação.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-029 — Fase 3: Extrair ChatTransferModalComponent

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Extrair `ChatTransferModalComponent` standalone de `chat.ts`, eliminando o HTML inline do template (linhas 130-179 de `chat.html`) e convertendo em componente com `@Input() isOpen`, `@Output() closed`, `@Output() confirmed`.

## Constraints

- Usar componentes compartilhados existentes (`ModalComponent`, `SelectInputComponent`, `ButtonComponent`).
- Manter `ChangeDetectionStrategy.OnPush`.
- Manter `takeUntilDestroyed` em todas as subscriptions.
- Seguir padrão de extração de componentes do módulo Chat já estabelecido.

## Context

- Módulos afetados: Chat
- Dependências: **TASK-028** (ChatTicketListService deve estar pronto para que o modal possa buscar informações do ticket)
- Referências:
    - `app/src/app/pages/chat/chat.html` — HTML inline a migrar (linhas 130-179)
    - `app/src/app/pages/chat/chat.ts` — signals e métodos a migrar
    - `app/src/app/shared/components/modal/modal.ts`
    - `app/src/app/shared/components/inputs/select-input/select-input.ts`

## Etapas

- [ ] Criar `components/chat-transfer-modal/chat-transfer-modal.ts` standalone.
- [ ] Mover signals e métodos relacionados ao transfer (conforme mapeamento do plano).
- [ ] Definir inputs: `isOpen`, `ticket` (opcional).
- [ ] Definir outputs: `closed`, `confirmed`.
- [ ] Mover HTML inline do modal para o template do novo componente.
- [ ] Criar spec `chat-transfer-modal.spec.ts`.
- [ ] Atualizar `chat.html` para usar o novo componente no lugar do HTML inline.
- [ ] Atualizar `chat.ts` para remover lógica transfer e usar o novo componente.
- [ ] Executar `pnpm run gate:all`.
- [ ] Atualizar documentação.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-030 — Fase 4: Extrair ChatContactFormComponent

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Extrair `ChatContactFormComponent` standalone de `chat.ts`, encapsulando o formulário de edição de contato visível na tab `contact` com `@Input() contact`, `@Output() saved`, `@Output() contactUpdated`.

## Constraints

- Usar componentes compartilhados existentes (`TextInputComponent`, `SelectInputComponent`, `ButtonComponent`).
- Manter `ChangeDetectionStrategy.OnPush`.
- Manter `takeUntilDestroyed` em todas as subscriptions.
- Preservar validação existente do formulário.
- **Nota:** `ChatContactViewComponent` (`chat-contact-view/chat-contact-view.ts`) **já existe** com campos mais completos (WhatsApp, role, custom_fields). Este novo `ChatContactFormComponent` é mais simples e específico para edição inline no contexto do ticket — não substitui `ChatContactView`.

## Context

- Módulos afetados: Chat, CRM
- Dependências: **TASK-028** (ChatTicketListService deve estar pronto)
- Referências:
    - `app/src/app/pages/chat/chat.ts` — formulário de contato a migrar
    - `app/src/app/pages/chat/chat.html` — onde o form é renderizado
    - `app/src/app/shared/components/inputs/text-input/text-input.ts`
    - `app/src/app/shared/components/inputs/select-input/select-input.ts`
    - `app/src/app/core/services/crm-contact.service.ts`
    - `app/src/app/pages/chat/components/chat-contact-view/chat-contact-view.ts` — **não substituir** (mais completo)

## Etapas

- [ ] Criar `components/chat-contact-form/chat-contact-form.ts` standalone.
- [ ] Mover `contactForm`, `fillContactForm`, `saveContactForm`, `contactCompanies`, `contactCompanyOptions`, `isSavingContact`, `contactSaveError`.
- [ ] Definir inputs: `contact` (CalledContactSummary).
- [ ] Definir outputs: `saved`, `contactUpdated`.
- [ ] Template inline no componente usando shared inputs.
- [ ] Criar spec `chat-contact-form.spec.ts`.
- [ ] Atualizar `chat.html` para usar o novo componente.
- [ ] Atualizar `chat.ts` para usar o novo componente.
- [ ] Executar `pnpm run gate:all`.
- [ ] Atualizar documentação.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-031 — Fase 5: ChatPage como orquestrador puro

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Converter `chat.ts` em orquestrador puro — injetar `ChatTicketListService` e `ChatRecorderService`, usar componentes extraídos, manter apenas efeitos de sincronização e wiring de `@Input()`/`@Output()`.

## Constraints

- Não adicionar lógica de domínio nova.
- Preservar todos os `@Output()` events que os componentes filhos emitem.
- Manter `ChangeDetectionStrategy.OnPush`.
- O template `chat.html` deve referenciar apenas os componentes extraídos e os wrappers existentes.
- **Usar `ChatStore` (`selectedCalledId`) para ler ticket selecionado** — não duplicar esse estado.

## Context

- Módulos afetados: Chat
- Dependências: **TASK-028, TASK-029, TASK-030** (todos concluídos)
- Referências:
    - `app/src/app/pages/chat/chat.ts` — arquivo a refatorar
    - `app/src/app/pages/chat/chat.store.ts` — **não modificar, usar via DI**
    - `app/src/app/pages/chat/chat.html` — template a atualizar

## Etapas

- [ ] Refatorar `chat.ts` para usar `inject(ChatTicketListService)`, `inject(ChatRecorderService)` e `inject(ChatStore)`.
- [ ] Remover todos os métodos e signals migrados para serviços/componentes.
- [ ] Manter apenas signals de UI pura (modals, dropdowns, drag state).
- [ ] **Ler `selectedTicketId` via `ChatStore.selectedCalledId`** — não manter sinal duplicado.
- [ ] Atualizar `chat.html` para referenciar `ChatTransferModalComponent` e `ChatContactFormComponent`.
- [ ] Remover HTML inline restante dos modais.
- [ ] **Verificar que `chat.spec.ts` cobre o novo comportamento do page component** — atualizar spec se necessário (remover mocks de lógica migrada, adicionar mocks de serviços injetados).
- [ ] Executar `pnpm run gate:all`.
- [ ] Atualizar documentação.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-032 — Fase 6: Validação — gates, QA, review

## Status: todo

## Plano origem: PLAN-020-refatorar-god-class-chat-ts

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Executar validação completa do refactor: gates, QA review e code review. Confirmar que não houve regressão funcional nos módulos Chat, CRM e AI.

## Constraints

- Suite completa de testes do módulo Chat deve passar.
- Lint e build sem erros.
- QA e review devem validar que o comportamento funcional está preservado.

## Context

- Módulos afetados: Chat, CRM, AI
- Dependências: TASK-027, TASK-028, TASK-029, TASK-030, TASK-031
- Referências:
    - `app/src/app/pages/chat/`
    - `app/src/app/pages/crm/`
    - `app/src/app/pages/ai/`

## Etapas

- [ ] Executar `pnpm run gate:all` completo (lint, test, build).
- [ ] Executar specs direcionadas de Chat (`chat.spec.ts`, `chat-recorder.service.spec.ts`, specs dos novos componentes).
- [ ] Executar specs de CRM que dependem de Chat (`negotiations.spec.ts`, `negotiation-show.spec.ts`).
- [ ] QA review sem critical blockers.
- [ ] Code review aprovado.
- [ ] Atualizar `AUDIT-FRONTEND-001.md` removendo `chat.ts` dos god-components CRITICAL.
- [ ] Commit final da refatoração.

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] Testes escritos e passando
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates:
- QA Review:
- Code Review:
- Commit:
