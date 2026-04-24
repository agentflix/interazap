# Tasks: Correção WebSocket Realtime — Chat Público

> Decomposição T.A.C.E das tasks da feature

---

## Feature: Correção WebSocket Realtime — Chat Público

**ID:** FEAT-043
**Bounded Context:** Chat (Laravel API + NestJS Gateway)
**Total Tasks:** 6
**Concluídas:** 0

---

## 🔄 FASE 3: BACKEND

### Tasks

#### TASK-3.043.1 ⏳: Adicionar broadcast realtime quando contato envia mensagem no webchat

**T — Tarefa:** No `WebChatMessageController`, após criar a mensagem do contato, emitir `emitNewMessageEvent` via `ChatActivityBroadcastService` para que o agente veja a mensagem em tempo real sem F5.

**A — Arquivo:**

- `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`
- `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php`
- `api/src/Domain/Chat/Actions/ProcessChatMessageAction.php` (para emitir evento)

**C — Comportamento:**

```text
ANTES:
- Contato envia mensagem via POST /api/webchat/messages
- Mensagem é persistida no banco
- Agente no console interno NÃO recebe atualização realtime
- Agente precisa pressionar F5 para ver nova mensagem

DEPOIS:
- Contato envia mensagem via POST /api/webchat/messages
- Mensagem é persistida no banco
- Backend emite emitNewMessageEvent para tenant room e ticket room
- Agente recebe chat.message.new via WebSocket em tempo real
```

**E — Evidência:**

- [ ] POST /api/webchat/messages com mensagem válida persiste e retorna 201
- [ ] Após persistência, `emitNewMessageEvent` é chamado com payload correto
- [ ] Evento `chat.activity` com subevent `msg.received` é publicado no Redis `ws.events`
- [ ] Teste unitário cobre emissão de broadcast em WebChatMessageController

**Dependências:** Nenhuma.

**Status:** ⏳ Pendente

---

#### TASK-3.043.2 ⏳: Emitir broadcast para mensagens outgoing do agente

**T — Tarefa:** Em `SendChatMessageAction`, quando um agente envia mensagem (direction=outgoing, source=agent), emitir `emitNewMessageEvent` para que o contato no webchat público receba em tempo real.

**A — Arquivo:**

- `api/src/Domain/Chat/Actions/SendChatMessageAction.php`
- `api/src/Domain/Chat/Actions/ProcessChatMessageAction.php`

**C — Comportamento:**

```text
ANTES:
- Agente envia mensagem via chat interno
- Mensagem é persistida e enviada ao gateway externo (WhatsApp)
- Contato no webchat público só recebe quando gateway confirma via webhook
- Contato não vê mensagem em tempo real

DEPOIS:
- Agente envia mensagem via chat interno
- Após persistência e envio ao gateway, emitNewMessageEvent é chamado
- Contato no webchat público recebe chat.message.new via WebSocket
- Contato vê mensagem imediatamente (antes mesmo da confirmação do gateway)
```

**E — Evidência:**

- [ ] Mensagem outgoing com source=agent persiste e retorna para o emitente
- [ ] `emitNewMessageEvent` é chamado após persistência da mensagem outgoing
- [ ] Evento chega na tenant room e ticket room corretas
- [ ] Teste unitário cobre emissão para mensagens outgoing

**Dependências:** Nenhuma.

**Status:** ⏳ Pendente

---

#### TASK-3.043.3 ⏳: Corrigir event name de webchat.ai_response para usar dois pontos

**T — Tarefa:** O `WebChatRedisPublisher` emite `webchat.ai_response` (com ponto), mas o frontend `WebChatService` escuta `webchat:ai_response` (com dois pontos). Corrigir o publisher para usar `webchat:ai_response`.

**A — Arquivo:**

- `api/src/Domain/Chat/Services/WebChatRedisPublisher.php`

**C — Comportamento:**

```text
ANTES:
- Laravel publica evento 'webchat.ai_response' (DOT)
- Frontend webchat escuta 'webchat:ai_response' (COLON)
- Resposta da IA não chega ao frontend webchat

DEPOIS:
- Laravel publica evento 'webchat:ai_response' (COLON)
- Frontend webchat recebe evento corretamente
- Resposta da IA aparece em tempo real no webchat público
```

**E — Evidência:**

- [ ] `WebChatRedisPublisher` emite evento com nome `webchat:ai_response`
- [ ] Teste unitário valida que o nome do evento está correto
- [ ] Frontend webchat recebe resposta da IA via WebSocket

**Dependências:** Nenhuma.

**Status:** ⏳ Pendente

---

#### TASK-3.043.4 ⏳: Investigar e corrigir gateway webchat (webchat:join handler)

**T — Tarefa:** O `WebChatService` conecta em `/ws/webchat` e emite `webchat:join`, mas o `EventsGateway` só tem path `/ws`. Investigar se existe gateway webchat separado ou se precisa ser adicionado handler `webchat:join` ao gateway existente.

**A — Arquivo:**

- `gateway/src/domains/realtime/gateways/events.gateway.ts`
- `gateway/src/domains/realtime/realtime.module.ts`
- `gateway/src/main.ts`

**C — Comportamento:**

```text
ANTES:
- WebChatService conecta em gatewayUrl com path '/ws/webchat'
- Emite 'webchat:join' com sessionId após conexão
- EventsGateway não tem namespace '/ws/webchat' configurado
- Eventos webchat não são transmitidos corretamente

DEPOIS (opção A - gateway único):
- EventsGateway registrado com namespace '/ws/webchat' adicional
- Handler 'webchat:join' faz join na room 'session:{sessionId}'
- Eventos 'webchat:ai_response' são emitidos para room da sessão

DEPOIS (opção B - gateway separado):
- Novo WebChatGateway criado com path '/ws/webchat'
- Handler 'webchat:join' processa corretamente
```

**E — Evidência:**

- [ ] WebSocket do webchat conecta sem erro de namespace
- [ ] `webchat:join` é processado e cliente entra na room correta
- [ ] Resposta da IA chega ao cliente webchat via WebSocket
- [ ] Teste e2e valida fluxo completo webchat:join → receive message

**Dependências:** TASK-3.043.3 (pode impactar qual evento o gateway precisa processar)

**Status:** ⏳ Pendente

---

## 🔄 FASE 4: FRONTEND

### Tasks

#### TASK-4.043.1 ⏳: Validar que WebChatService escuta evento correto após correção do backend

**T — Tarefa:** Após corrigir o event name no backend (TASK-3.043.3), validar que o `WebChatService` escuta o evento correto `webchat:ai_response` com dois pontos.

**A — Arquivo:**

- `app/src/app/pages/webchat/services/webchat.service.ts`

**C — Comportamento:**

```text
ANTES:
- WebChatService escuta 'webchat:ai_response' (esperado pelo bug #3)

DEPOIS:
- Backend emite 'webchat:ai_response' (corrigido)
- Frontend continua escutando 'webchat:ai_response'
- Match de nome de evento confirmado
```

**E — Evidência:**

- [ ] Listener `webchat:ai_response` está presente no `bindSocketListeners`
- [ ] Resposta da IA aparece no chat window quando disparada

**Dependências:** TASK-3.043.3.

**Status:** ⏳ Pendente

---

## 🔄 FASE 5: INTEGRAÇÃO

### Tasks

#### TASK-5.043.1 ⏳: Teste E2E do fluxo completo realtime

**T — Tarefa:** Criar teste E2E que valida o fluxo completo: contato envia mensagem no webchat público → agente recebe via WebSocket → agente responde → contato recebe resposta via WebSocket.

**A — Arquivo:**

- `api/tests/Feature/Chat/WebChatRealtimeFlowTest.php`
- `gateway/src/domains/realtime/gateways/events.gateway.spec.ts`

**C — Comportamento:**

```text
ANTES:
- Não existe teste automatizado para o fluxo realtime completo

DEPOIS:
- Teste E2E cobre: POST mensagem → broadcast Redis → fan-out NestJS → receive WebSocket
- Teste valida que mensagem do contato chega ao agente sem F5
- Teste valida que resposta do agente chega ao contato sem refresh
```

**E — Evidência:**

- [ ] Teste passando: contato envia → agente recebe
- [ ] Teste passando: agente responde → contato recebe
- [ ] Latência de entrega < 2 segundos

**Dependências:** TASK-3.043.1, TASK-3.043.2, TASK-3.043.3, TASK-3.043.4.

**Status:** ⏳ Pendente

---

## Revisão de Tasks

| Task | Status | Validada por | Data |
|------|--------|--------------|------|
| TASK-3.043.1 | ⏳ | - | - |
| TASK-3.043.2 | ⏳ | - | - |
| TASK-3.043.3 | ⏳ | - | - |
| TASK-3.043.4 | ⏳ | - | - |
| TASK-4.043.1 | ⏳ | - | - |
| TASK-5.043.1 | ⏳ | - | - |

---

## Progresso

- [0/6] Tasks concluídas
- [ ] Feature completa

---

## Dependências Entre Tasks

```
TASK-3.043.1 ──────────────────┐
                                │
TASK-3.043.2 ──────────────────┤──► TASK-5.043.1
                                │
TASK-3.043.3 ──┬───────────────┤
               │               │
               ▼               │
TASK-3.043.4 ──┴──► TASK-4.043.1 ─┘
```

---

## Bugs Corrigidos por Esta Feature

| Bug | Descrição | Task |
|-----|-----------|------|
| #1 | Contato envia mensagem → agente não vê realtime | TASK-3.043.1 |
| #2 | Agente envia mensagem → contato não vê realtime | TASK-3.043.2 |
| #3 | Event name mismatch `webchat.ai_response` vs `webchat:ai_response` | TASK-3.043.3 |
| #4 | EventsGateway sem handler `webchat:join` | TASK-3.043.4 |
