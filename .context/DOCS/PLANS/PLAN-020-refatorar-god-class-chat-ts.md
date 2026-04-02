# PLAN-020-refatorar-god-class-chat-ts — Decompor god-class chat.ts (1541 linhas)

## Objetivo

Decompor `app/src/app/pages/chat/chat.ts` (god-class CRITICAL com 1541 linhas) extraindo `ChatTransferModalComponent`, `ChatContactFormComponent` e `ChatTicketListService`, eliminando duplicação com `ChatRecorderService` já existente e convertendo o page component em orquestrador puro. **Nota:** `ChatStore` em `chat.store.ts` já gerencia `selectedCalledId`/`selectedCalled` — o novo `ChatTicketListService` deve gerenciar apenas a **lista** de tickets (`tickets[]`, `counts`, `loadTickets`, `hydrateTicket`, etc.) sem expor `selectedTicketId`.

## Módulo relacionado

**Frontend** — Angular 20 / TypeScript 5.9 (`app/src/`)

## PRD relacionado: N/A

## Escopo

### Incluído

- Substituir a duplicação inline de `MediaRecorder`/`audioStream`/`recordingTimer` em `chat.ts` pelo `ChatRecorderService` já existente (`chat/services/chat-recorder.service.ts`).
- Extrair `ChatTransferModalComponent` standalone com `@Input() isOpen`, `@Output() closed`, `@Output() confirmed`.
- Extrair `ChatContactFormComponent` standalone com `@Input() contact`, `@Output() saved` e `@Output() contactUpdated`.
- Criar `ChatTicketService` em `chat/services/` extraindo toda lógica de domínio de tickets (`loadTickets`, `hydrateTicket`, `startSelectedTicket`, `confirmCloseSelectedTicket`, `transferTicket`, `deriveCounts`, `transientStatusOverride`).
- Converter `chat.ts` em orquestrador puro (injeta serviços, passa `@Input()`s, delega events).
- Atualizar `chat.html` removendo templates inline dos modais extraídos e referenciando os novos componentes.
- Garantir que todos os `@Output()` events continuar funcionando após a extração.

### Excluído

- Alterar contratos de API backend ou gateway.
- Modificar `ChatConversationComponent`, `ChatListComponent`, `ChatSidebarComponent` — esses já são componentes separados.
- Refatorar `user-chat.ts`, `chat-negotiation-view.ts` ou qualquer outro componente do módulo Chat fora de `chat.ts`.
- Redesign visual ou mudança de comportamento funcional.

## Evidências da Codebase

### Chat Recorder Service (JÁ EXISTE — duplicação a remover)

- [x] `app/src/app/pages/chat/services/chat-recorder.service.ts` — encapsula `MediaRecorder`, `mediaStream`, `recordingInterval`, `state`, `duration`, `start()`, `pause()`, `resume()`, `stop()`, `cancel()`, `recordingCompleted$` ✅
- [x] `app/src/app/pages/chat/services/chat-recorder.service.spec.ts` — 100% coverage ✅
- **Problema**: `chat.ts` (~linhas 247-766) tem implementação inline idêntica duplicada!

### Componentes existentes já extraídos

- [x] `app-chat-conversation-component` — wrapper de conversa (ChatConversationComponent)
- [x] `app-chat-list-component` — wrapper de lista (ChatListComponent)
- [x] `app-chat-sidebar-component` — wrapper sidebar (ChatSidebarComponent)
- [x] `app-media-preview` — modal de preview de mídia (MediaPreviewComponent)

### Componentes existentes no escopo (NÃO duplicar)

- [x] `ChatStore` (`chat.store.ts`) — gerencia `selectedCalledId`/`selectedCalled`, `startAttendance()`, `fetchCalled()`, `updateContact()`. **Manter como está. Não migrar.**
- [x] `ChatContactViewComponent` (`chat-contact-view/chat-contact-view.ts`) — formulário completo de contato com WhatsApp, role e custom_fields. **Não é o mesmo que o contact form de chat.ts.** São componentes distintos.

### Componentes a extrair de chat.ts

| Componente                    | Linhas aproximadas | Estado atual                              |
| ---------------------------- | ------------------ | ----------------------------------------- |
| `ChatTransferModalComponent` | ~80                | HTML inline em chat.html (linhas 130-179) |
| `ChatContactFormComponent`   | ~120               | Lógica inline em chat.ts                  |
| `ChatTicketListService`      | ~200               | Métodos inline em chat.ts                 |

### ChatTicketListService — fronteira com ChatStore

O `ChatTicketListService` gerencia **apenas** a lista de tickets e contadores:

```
ChatTicketListService (NOVO)     ChatStore (JÁ EXISTE)
├── tickets[]                   ├── selectedCalledId
├── counts                      ├── selectedCalled
├── loadingTickets              ├── startAttendance()
├── loadTickets()              ├── fetchCalled()
├── hydrateTicket()            ├── updateContact()
├── deriveCounts()             └── canSendMessage
├── transientStatusOverride
└── applyTransientStatusOverrides
```

**Não pode expor `selectedTicketId`** — esse pertence ao `ChatStore`.

### Files a criar

| Arquivo                                                                          | Ação  |
| -------------------------------------------------------------------------------- | ----- |
| `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.ts`   | criar |
| `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.spec.ts` | criar |
| `app/src/app/pages/chat/components/chat-contact-form/chat-contact-form.ts`       | criar |
| `app/src/app/pages/chat/components/chat-contact-form/chat-contact-form.spec.ts` | criar |
| `app/src/app/pages/chat/services/chat-ticket-list.service.ts`                    | criar |
| `app/src/app/pages/chat/services/chat-ticket-list.service.spec.ts`               | criar |

### Files a modificar

| Arquivo                          | Ação                |
| -------------------------------- | ------------------- |
| `app/src/app/pages/chat/chat.ts` | refatorar (usar ChatRecorderService, ChatTicketListService, componentes extraídos) |
| `app/src/app/pages/chat/chat.html` | refatorar (remover HTML inline dos modais) |

## Etapas propostas

### Fase 1 — ChatRecorderService (eliminar duplicação)

1. Identificar todos os métodos e signals duplicados em `chat.ts` (linhas 247-766) que correspondem ao `ChatRecorderService`.
2. Substituir uso inline por `inject(ChatRecorderService)` com `state()`, `duration()`, `start()`, `pause()`, `resume()`, `cancel()`, `stop()`, `recordingCompleted$`.
3. Conectar `recordingCompleted$` no construtor para enviar áudio via `calledMessageService.uploadMedia()`.
4. Remover signals e métodos duplicados: `mediaRecorder`, `audioChunks`, `audioStream`, `recordingTimer`, `startRecordingTimer`, `stopRecordingTimer`, `releaseAudioStream`, `cleanupRecording`, `formatRecordingTime`, `isRecording`, `isRecordingPaused`, `recordingDuration`, `isSendingAudio`.
5. Validar com spec existente de `chat-recorder.service.ts` + spec de `chat.ts`.

### Fase 2 — ChatTicketListService (extrair lógica de domínio)

1. Criar `ChatTicketListService` com os métodos e state de **lista** de tickets. **Não expor `selectedTicketId`** — esse é gerenciado pelo `ChatStore`.
2. Migrar: `loadTickets`, `hydrateTicket`, `deriveCounts`, `setTransientStatusOverride`, `applyTransientStatusOverrides`, `syncSelectedTicketFromRoute`.
3. Migrar signals: `tickets`, `counts`, `loadingTickets`.
4. Migrar: `transientStatusOverrides`, `transientStatusOverrideTimers`.
5. **Não migrar** `selectedTicketId`, `isStartingTicket`, `isClosingTicket` — esses ficam no page component ou usam `ChatStore`.
6. Manter no `ChatPage` apenas signals de UI que não são estado de domínio (ex: `isTransferModalOpen`, `isCloseTicketConfirmOpen`).
7. Criar spec `chat-ticket-list.service.spec.ts` cobrindo fluxos principais.

### Fase 3 — ChatTransferModalComponent

1. Criar componente standalone `ChatTransferModalComponent`.
2. Mover `transferUsers`, `transferUserControl`, `transferUserOptions`, `isTransferModalOpen`, `isTransferLoading`, `isTransferUsersLoading`, `transferUsersLoadError`.
3. Mover `loadTransferUsers`, `confirmTransferSelectedTicket`, `closeTransferModal`, `openTransferModal`.
4. Inputs: `isOpen`, `ticket` (para mostrar info do ticket).
5. Outputs: `closed`, `confirmed`.
6. Template: mover HTML inline de `chat.html` (linhas 130-179).
7. Criar spec.

### Fase 4 — ChatContactFormComponent

1. Criar componente standalone `ChatContactFormComponent`.
2. Mover `contactForm`, `fillContactForm`, `saveContactForm`, `contactCompanies`, `contactCompanyOptions`, `isSavingContact`, `contactSaveError`, `contactSaveSuccess`.
3. Inputs: `contact` (CalledContactSummary), `isActive`.
4. Outputs: `saved`, `contactUpdated`.
5. Mover `contactService` usage.
6. Template: inline no componente (formulário de edição de contato da tab contact).
7. Criar spec.

### Fase 5 — ChatPage como orquestrador puro

1. Refatorar `chat.ts` para usar os serviços e componentes extraídos.
2. Remover toda lógica de domínio e estado de UI especializado.
3. Manter apenas: injeção de serviços, wiring de `@Input()`/`@Output()`, efeitos de sincronização.
4. Atualizar `chat.html` para importar e usar os novos componentes.
5. Atualizar spec `chat.spec.ts` se necessário.

### Fase 6 — Validação

1. Executar `pnpm run gate:all`.
2. Verificar que 0 regressões nos módulos Chat, CRM e AI.
3. QA review.
4. Code review.

## Tasks derivadas

| Task     | Descrição                                                        | Agente   | Status |
| -------- | ---------------------------------------------------------------- | -------- | ------ |
| TASK-027 | Fase 1 — Substituir duplicação MediaRecorder pelo ChatRecorderService | FRONTEND | todo   |
| TASK-028 | Fase 2 — Criar ChatTicketListService e migrar lógica de domínio  | FRONTEND | todo   |
| TASK-029 | Fase 3 — Extrair ChatTransferModalComponent (depende: TASK-028) | FRONTEND | todo   |
| TASK-030 | Fase 4 — Extrair ChatContactFormComponent (depende: TASK-028)   | FRONTEND | todo   |
| TASK-031 | Fase 5 — ChatPage como orquestrador puro (depende: TASK-029,030) | FRONTEND | todo   |
| TASK-032 | Fase 6 — Validação: gates, QA, review                           | FRONTEND | todo   |

## Riscos e dependências

### Riscos

| Risco                                                         | Probabilidade | Impacto | Mitigação                                                                 |
| ------------------------------------------------------------- | ------------ | ------- | ------------------------------------------------------------------------- |
| Conflito de estado entre `ChatStore.selectedCalledId` e `ChatTicketListService.selectedTicketId` | **Alta** | **Crítico** | ChatTicketListService NÃO expõe `selectedTicketId`; apenas `ChatStore` gerencia seleção |
| TASK-029/030 dependem de TASK-028 ter sido concluída          | Média        | Alto    | Executar TASK-029 e TASK-030 apenas após TASK-028 (ChatTicketListService pronto) |
| ChatContactViewComponent vs ChatContactFormComponent           | Baixa        | Médio   | ChatContactView é formulário completo (WhatsApp, role, custom_fields); ChatContactForm é mais simples e vem do ticket. São distintos. |

### Dependências

- `chat-recorder.service.ts` — já existe, não criar
- `chat.store.ts` — já existe, **não modificar** (gerencia seleção de ticket)
- `ChatContactViewComponent` — já existe, **não substituir** (é mais completo que o contact form de chat.ts)
- `ChatConversationComponent`, `ChatListComponent`, `ChatSidebarComponent` — não modificar
- `CalledService`, `CalledMessageService`, `UserService`, `ContactService`, `CRMCompanyService` — consumir via DI
- TASK-028 concluída é pré-requisito para TASK-029 e TASK-030
- TASK-029 e TASK-030 concluídas é pré-requisito para TASK-031

## Estimativa

| Item                          | Valor    |
| ----------------------------- | -------- |
| Complexidade                  | Alta     |
| Camadas afetadas              | Frontend |
| Migrações necessárias         | Não      |
| Impacto em módulos existentes | Sim (Chat, CRM) |
