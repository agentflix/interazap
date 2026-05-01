# Tasks: Decomposição do Host `chat.ts`

> Decomposição T.A.C.E das tasks da FEAT-049

---

## Feature: Decomposição do Host `chat.ts`

**ID:** FEAT-049
**Bounded Context:** Chat (Frontend)
**Total Tasks:** 6
**Concluídas:** 6

---

## 🔄 FASE 4: FRONTEND

### Tasks

#### TASK-049.1 ✅: Extrair ChatRealtimeListenerService

**T — Tarefa:** Mover os 3 listeners de realtime (`message.received`, `chat.activity`, `chat.ticket.new`) e seus 3 handlers (`handleIncomingMessage`, `handleActivityEvent`, `handleNewTicketEvent`) do host para um service novo.

**A — Arquivo:**

- Criar: `app/src/app/pages/chat/services/chat-realtime-listener.service.ts`
- Editar: `app/src/app/pages/chat/chat.ts` (remover métodos privados + chamada `setupRealtimeListeners()`)

**C — Comportamento:**

```
ANTES:
- Host conecta `realtime`, faz subscribe inline, mantém constantes de cooldown e
  logica de notificação sonora dentro do componente.

DEPOIS:
- Service `ChatRealtimeListenerService.start({ ticketList, chatSound })` faz subscribe
  com `takeUntilDestroyed` injetado, expõe os 3 listeners. Host apenas chama `start()`
  no constructor.
- Cooldowns (`NOTIFICATION_COOLDOWN_MS`, `TICKET_LIST_REFRESH_COOLDOWN_MS`) viram
  constantes do service.
- Comportamento de tocar som / refresh de lista preservado byte-equivalente.
```

**E — Evidência:**

- [x] `chat.ts` não importa `RealtimeService` nem `tocarNotificacao`
- [x] `chat.spec.ts` 6/6 passa sem alteração
- [x] Service novo tem spec mínimo verde
- [x] Lint limpo nos 2 arquivos

**Status:** ✅ Concluída

---

#### TASK-049.2 ✅: Extrair ChatTicketTransferService

**T — Tarefa:** Mover o fluxo de transferência (abrir/fechar modal, signals `isTransferModalOpen`/`isTransferLoading`/`transferError`, handler `onTransferConfirmed` com `switchMap`) para um service.

**A — Arquivo:**

- Criar: `app/src/app/pages/chat/services/chat-ticket-transfer.service.ts`
- Editar: `app/src/app/pages/chat/chat.ts`

**C — Comportamento:**

```
ANTES:
- Host gerencia 3 signals + 3 métodos para transfer flow.

DEPOIS:
- Service expõe signals (`isModalOpen`, `isLoading`, `error`) e métodos
  (`openModal`, `closeModal`, `confirm`).
- Host expõe getters readonly que delegam ao service para preservar template.
- O fix `switchMap` aplicado anteriormente é preservado.
```

**E — Evidência:**

- [x] Fluxo de transferência funciona (testes manuais via `chat.spec.ts`)
- [x] Service tem spec testando happy-path + erro
- [x] Template `chat.html` não muda
- [x] Lint limpo

**Status:** ✅ Concluída

---

#### TASK-049.3 ✅: Extrair ChatTicketCloseService

**T — Tarefa:** Mover o fluxo de fechamento de ticket (signals `isClosingTicket`/`isCloseTicketConfirmOpen`, métodos `openCloseTicketConfirm`, `closeCloseTicketConfirm`, `confirmCloseSelectedTicket` incluindo update otimista de `tickets`/`counts`) para um service.

**A — Arquivo:**

- Criar: `app/src/app/pages/chat/services/chat-ticket-close.service.ts`
- Editar: `app/src/app/pages/chat/chat.ts`

**C — Comportamento:**

```
ANTES:
- Host detém os signals + lógica completa de fechamento (≈ 80 linhas).

DEPOIS:
- Service recebe `tickets` e `counts` writable signals do `ticketList` para
  manipular otimisticamente, expõe signals de UI e método `confirm(ticketId)`.
- Host apenas delega.
- Mensagens de toast e navegação preservadas.
```

**E — Evidência:**

- [x] Fechamento de ticket continua otimista e reverte em erro
- [x] Spec do service com happy-path + rollback em erro
- [x] `chat.spec.ts` 6/6 passa
- [x] Lint limpo

**Status:** ✅ Concluída

---

#### TASK-049.4 ✅: Extrair ChatRecordingDispatcher

**T — Tarefa:** Mover o método `handleRecordingCompleted` (que faz upload + send via `switchMap`) e o signal `isSendingAudio` para um service dedicado.

**A — Arquivo:**

- Criar: `app/src/app/pages/chat/services/chat-recording-dispatcher.service.ts`
- Editar: `app/src/app/pages/chat/chat.ts`

**C — Comportamento:**

```
ANTES:
- Host detém handler + signal isSendingAudio. Subscribe vive em construtor.

DEPOIS:
- Service expõe `isSending: Signal<boolean>` e método `dispatch(blob, ticketId)`.
- Host plumba: `recorder.recordingCompleted$ → dispatcher.dispatch(...)`.
- Pipeline `switchMap(uploadMedia → send)` preservado.
```

**E — Evidência:**

- [x] Service tem spec verificando upload→send em sequência via `switchMap`
- [x] Erro de upload e erro de envio são tratados com toast
- [x] `chat.spec.ts` 6/6 passa
- [x] Lint limpo

**Status:** ✅ Concluída

---

#### TASK-049.5 ✅: Integrar services no host e reduzir `chat.ts`

**T — Tarefa:** Atualizar `chat.ts` para injetar e delegar aos 4 services. Remover métodos extraídos, signals duplicados, imports órfãos. Validar contagem final ≤ 700 linhas.

**A — Arquivo:**

- Editar: `app/src/app/pages/chat/chat.ts`

**C — Comportamento:**

```
ANTES:
- 1.333 linhas, 12 dependências injetadas, ~30 métodos privados/públicos.

DEPOIS:
- ≤ 700 linhas, 4 services novos no construtor.
- Signals de transfer/close/recording são getters readonly delegando aos services.
- Template não muda.
```

**E — Evidência:**

- [ ] `wc -l chat.ts` ≤ 700  _(parcial: 1.078 — ver nota CA-001)_
- [x] `chat.spec.ts` 6/6 passa
- [x] Lint limpo
- [x] Smoke test manual: abrir lista, selecionar ticket, transferir, fechar, gravar áudio

**Status:** ✅ Concluída (CA-001 parcial documentado)

---

#### TASK-049.6 ✅: Specs para cada service extraído

**T — Tarefa:** Criar spec mínimo (Vitest + TestBed) para os 4 services novos cobrindo happy-path e principais erros.

**A — Arquivo:**

- Criar: `chat-realtime-listener.service.spec.ts`
- Criar: `chat-ticket-transfer.service.spec.ts`
- Criar: `chat-ticket-close.service.spec.ts`
- Criar: `chat-recording-dispatcher.service.spec.ts`

**C — Comportamento:**

```
- Cada spec faz mock dos services dependentes (CalledService, RealtimeService, etc.)
  via `provideValue` ou `vi.fn()`.
- Asserts cobrem: chamada da API correta, atualização de signals esperada,
  toast disparado, erro tratado.
```

**E — Evidência:**

- [x] 4 specs verdes (17/17 incluindo regressão de remount)
- [x] Coverage local dos services novos ≥ 70%

**Status:** ✅ Concluída

---

## Revisão de Tasks

| Task       | Status | Validada por      | Data       |
| ---------- | ------ | ----------------- | ---------- |
| TASK-049.1 | ✅     | REVIEWER + QA     | 2026-04-29 |
| TASK-049.2 | ✅     | REVIEWER + QA     | 2026-04-29 |
| TASK-049.3 | ✅     | REVIEWER + QA     | 2026-04-29 |
| TASK-049.4 | ✅     | REVIEWER + QA     | 2026-04-29 |
| TASK-049.5 | ✅     | REVIEWER + QA     | 2026-04-29 |
| TASK-049.6 | ✅     | REVIEWER + QA     | 2026-04-29 |

---

## Progresso

- [6/6] Tasks concluídas
- [x] Feature **parcial** completa — redução de linhas ficou abaixo do CA-001 (1.078 vs ≤ 700). Serviços extraídos com testes; redução adicional requer feature follow-up (`FEAT-050`) para extrair fluxo de mídia/anexos e route-sync.
