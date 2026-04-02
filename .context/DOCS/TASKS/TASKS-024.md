# TASKS-024 — Bugfix: Status "Digitando" não aparece ao receber webhook de typing da UazAPI

**Entregas:** 2 | **Tasks:** 10

| Entrega | Descrição                                                   | Tasks                       | Status |
| ------- | ----------------------------------------------------------- | --------------------------- | ------ |
| 1       | Gateway: Normalizar typing/composing e emitir `chat.typing` | TASK-024.1.1 - TASK-024.1.6 | todo   |
| 2       | Backend: Adicionar emitTyping e validar subevent + testes   | TASK-024.2.1 - TASK-024.2.3 | done   |

---

## Entrega 1 — Gateway: Normalizar typing/composing e emitir `chat.typing` ✅ testável

**Entrega:** Webhook UazAPI com typing/composing gera evento `chat.typing` no WebSocket | **Agente:** @DEV

**Gates:** `cd gateway && pnpm lint && pnpm test`

### TASK-024.1.1 — Adicionar `TYPING` em `CHAT_EVENTS`

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Adicionar a constante `TYPING: 'chat.typing'` em `gateway.constants.ts` em `CHAT_EVENTS`, seguindo o padrão existente de outros eventos.

**Constraints**

- Manter `readonly` na interface
- Seguir padrão de nomenclatura existente

**Context References**

- `gateway/src/shared/constants/gateway.constants.ts` _(required in context)_

**Etapas**

- [ ]   1. Editar `gateway/src/shared/constants/gateway.constants.ts` — adicionar `TYPING: 'chat.typing'` em `CHAT_EVENTS`
- [ ]   2. Verificar `cd gateway && pnpm lint`

**Critérios de conclusão**

- [ ] Constante `CHAT_EVENTS.TYPING` existe e é `'chat.typing'`

---

### TASK-024.1.2 — Atualizar tipos `NormalizedUazapiEvent` e `EventType`

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Atualizar os tipos TypeScript para suportar eventos de presence:

1. Incluir `'presence'` no union type `EventType` do DTO
2. Adicionar campos `presence?`, `is_typing?` e `number?` em `NormalizedUazapiEvent` (model)
3. Adicionar campo `number?` no `UazapiWebhookDto` (senão `ValidationPipe(whitelist:true)` remove o campo)

> ⚠️ **Nota de arquitetura:** `ticket_id` NÃO deve ser retornado de `normalize()`. O `ticket_id` é resolvido posteriormente em `emitTypingEvent()` via `resolveTicketIdForRemoteJid()`, seguindo o mesmo padrão usado nos eventos de mensagem.

**Context References**

- `gateway/src/domains/chat/providers/uazapi/uazapi.dto.ts` _(required in context)_
- `gateway/src/domains/chat/models/uazapi.model.ts` _(required in context)_

**Code Context — uazapi.dto.ts**

```typescript
// Atualizar EventType
export type EventType = 'messages' | 'messages_update' | 'connection' | 'presence';

// Adicionar campo number no DTO (para ValidationPipe whitelist)
@IsOptional()
@IsString()
/** Telefone do contato (para webhooks de presence/composing). */
number?: string;
```

**Code Context — uazapi.model.ts**

```typescript
// Atualizar NormalizedUazapiEvent
export type NormalizedUazapiEvent = {
    provider: 'uazapi';
    event_type: 'messages' | 'messages_update' | 'connection' | 'presence';
    // ... existing fields
    /** Presença do contato: composing | recording | paused. */
    presence?: 'composing' | 'recording' | 'paused';
    /** true para composing/recording, false para paused. */
    is_typing?: boolean;
    /** Telefone do contato (para resolução do ticket). */
    number?: string;
};
```

**Etapas**

- [ ]   1. Editar `gateway/src/domains/chat/providers/uazapi/uazapi.dto.ts` — adicionar `'presence'` em `EventType` e campo `number?: string`
- [ ]   2. Editar `gateway/src/domains/chat/models/uazapi.model.ts` — atualizar `NormalizedUazapiEvent` (event_type union + campos presence, is_typing, number)
- [ ]   3. Verificar `cd gateway && pnpm lint`

**Critérios de conclusão**

- [ ] `EventType` aceita `'presence'`
- [ ] `NormalizedUazapiEvent` tem campos `presence?`, `is_typing?`, `number?`
- [ ] `UazapiWebhookDto` tem campo `number?: string`
- [ ] `ValidationPipe(whitelist:true)` não remove campos de presence do payload

---

### TASK-024.1.3 — Normalizar presence/composing no `UazapiProvider::normalize()`

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Atualizar o método `normalize()` do `UazapiProvider` para extrair os campos de presence/composing do payload do webhook UazAPI. Quando o webhook contiver dados de typing (ex: `{ EventType: 'presence', presence: 'composing', number: '5511999999999' }`), o `NormalizedUazapiEvent` deve incluir `presence`, `is_typing` e `number`.

> ⚠️ **Importante:** `ticket_id` NÃO deve ser retornado de `normalize()`. A resolução do `ticket_id` pelo número do contato acontece em `emitTypingEvent()` via `resolveTicketIdForRemoteJid()`, seguindo o mesmo padrão usado em `emitRealtime()` para eventos de mensagem.

**Constraints**

- Manter backward compatibility com payloads existentes
- Preservar todos os campos já normalizados (message, media, etc.)
- Usar `event_type: 'presence' as const` para type safety
- Logger por service

**Context References**

- `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` _(required in context)_

**Code Context**

```typescript
// Expected — adicionar extração de presence no normalize()
// INSERIR ANTES do return final (como case early-return)
const presence = (payload as Record<string, unknown>).presence as string | undefined;
if (presence && ['composing', 'recording', 'paused'].includes(presence)) {
    return {
        provider: 'uazapi' as const,
        event_type: 'presence' as const,
        presence,
        is_typing: presence === 'composing' || presence === 'recording',
        number: (payload as Record<string, unknown>).number as string | undefined,
        instance_webhook_token: token,
        // ... demais campos existentes preservados
    };
}
```

**Etapas**

- [ ]   1. Editar `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` — método `normalize()` — adicionar extração de `presence` como early-return antes do return padrão
- [ ]   2. Garantir que campos existentes não sejam sobrescritos (usar spread se necessário)
- [ ]   3. Verificar `cd gateway && pnpm lint`

**Critérios de conclusão**

- [ ] `normalize()` extrai `presence` de webhook payload UazAPI com `EventType: 'presence'`
- [ ] `presence` aceitável: `composing`, `recording`, `paused`
- [ ] `is_typing` é `true` para `composing`/`recording`, `false` para `paused`
- [ ] `number` é preservado para resolução do ticket em `emitTypingEvent()`
- [ ] Payload message existente (sem presence) não é afetado

---

### TASK-024.1.4 — Adicionar caso `presence semantic` no PayloadSemanticsResolver + type

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Atualizar o `PayloadSemanticsResolver` para retornar `semanticType: 'presence'` quando `eventType === 'presence'`, e atualizar o tipo `PayloadSemanticMetadata` para incluir `'presence'` na union.

> ⚠️ Sem atualizar o tipo, o TypeScript reportará erro naassign de `semanticType: 'presence'`.

**Context References**

- `gateway/src/domains/chat/services/payload-semantics-resolver.service.ts` _(required in context)_
- `gateway/src/domains/chat/services/chat-webhook.types.ts` _(required in context — `PayloadSemanticMetadata`)_

**Code Context**

```typescript
// chat-webhook.types.ts — atualizar PayloadSemanticMetadata
export type PayloadSemanticMetadata = {
    semanticType: 'create' | 'update' | 'connection' | 'presence' | 'unknown';
    // ...
};
```

```typescript
// payload-semantics-resolver.service.ts — adicionar case
if (eventType === 'presence') {
    return {
        semanticType: 'presence' as const,
        normalizedEventType: 'presence',
        description: 'Contact typing indicator',
        isIdempotent: true,
    };
}
```

**Etapas**

- [ ]   1. Editar `gateway/src/domains/chat/services/chat-webhook.types.ts` — adicionar `'presence'` à union `semanticType` em `PayloadSemanticMetadata`
- [ ]   2. Editar `gateway/src/domains/chat/services/payload-semantics-resolver.service.ts` — `resolvePayloadSemantics()` — adicionar case para `eventType === 'presence'`
- [ ]   3. Verificar `cd gateway && pnpm lint`

**Critérios de conclusão**

- [ ] `PayloadSemanticMetadata.semanticType` aceita `'presence'`
- [ ] `resolvePayloadSemantics()` retorna `semanticType: 'presence'` para event type `presence`

---

### TASK-024.1.5 — Adicionar case typing em `emitRealtime()` + emitTypingEvent()

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Atualizar `emitRealtime()` para detectar `semanticType === 'presence'` e emitir `chat.typing`, criar o método `emitTypingEvent()`, e adicionar bypass de idempotência para presence (igual a connection events — typing é transient e deve sempre ser emitido).

**Constraints**

- Preservar o padrão de `emitConnectionEvent`/`emitMessageStatusEvent`
- Presence events devem fazer bypass de idempotência (semelhante a connection events)
- Validar que `ticket_id` existe antes de emitir
- Se não encontrar ticket, emitir sem `ticket_id` (frontend ignora se não encontrar)
- Logger por service

**Context References**

- `gateway/src/domains/chat/services/chat-webhook.service.ts` _(required in context)_
- `gateway/src/domains/chat/services/webhook-realtime-emitter.service.ts` _(referência — padrão de emissão)_

**Code Context**

```typescript
// chat-webhook.service.ts — processStreamPayload() idempotency bypass
// LOCALIZAR a linha: const isConnectionEvent = idempotencyDescriptor.normalizedEventType.startsWith('connection');
// ANTES DO: if (!isConnectionEvent) { await this.ensureIdempotentWithTimeout(...) }
// ATUALIZAR PARA:
const isConnectionEvent = idempotencyDescriptor.normalizedEventType.startsWith('connection');
const isPresenceEvent = idempotencyDescriptor.normalizedEventType === 'presence';
if (!isConnectionEvent && !isPresenceEvent) {
    const isNew = await this.ensureIdempotentWithTimeout(/* ... */);
    if (isNew === false) {
        return;
    }
}
```

```typescript
// chat-webhook.service.ts — emitRealtime() adicionar case
if (semanticType === 'presence') {
    await this.emitTypingEvent(payload, precomputedSemantics);
    return;
}
```

```typescript
// chat-webhook.service.ts — emitTypingEvent() (novo método privado)
private async emitTypingEvent(
  payload: UazapiStreamPayload,
  semantics: PayloadSemanticMetadata,
): Promise<void> {
  const logger = this.logger;
  const number = payload.number;
  if (!number) {
    logger.warn('Presence event without number, skipping');
    return;
  }

  // Resolver ticket ativo pelo número do contato
  const ticket = await this.resolveTicketIdForRemoteJid(
    payload.tenant_id,
    `${number}@s.whatsapp.net`,
  );

  const isTyping = payload.is_typing ?? true;
  const typingPayload = {
    ticket_id: ticket?.ticket_id,
    is_typing: isTyping,
    presence: payload.presence,
    number,
  };

  // Emitir para sala do tenant
  await this.webhookRealtimeEmitter.emitToRoom(
    `tenant:${payload.tenant_id}`,
    CHAT_EVENTS.TYPING,
    typingPayload,
  );

  // Emitir para sala do ticket (se existir)
  if (ticket?.ticket_id) {
    await this.webhookRealtimeEmitter.emitToRoom(
      `ticket:${ticket.ticket_id}`,
      CHAT_EVENTS.TYPING,
      typingPayload,
    );
  }

  logger.log(`Emitted chat.typing: ${isTyping ? 'start' : 'stop'} for ${number}`);
}
```

**Etapas**

- [ ]   1. Editar `gateway/src/domains/chat/services/chat-webhook.service.ts` — `processStreamPayload()` — adicionar bypass de idempotência para `presence` events
- [ ]   2. Editar `gateway/src/domains/chat/services/chat-webhook.service.ts` — `emitRealtime()` — adicionar case `semanticType === 'presence'` → `emitTypingEvent()`
- [ ]   3. Criar método privado `emitTypingEvent()` seguindo o padrão acima
- [ ]   4. Verificar `cd gateway && pnpm lint && pnpm test`

**Critérios de conclusão**

- [ ] Eventos presence fazem bypass de idempotência (sempre emitidos)
- [ ] `emitRealtime()` chama `emitTypingEvent()` para semanticType `'presence'`
- [ ] `emitTypingEvent()` resolve ticket pelo número do contato
- [ ] `emitTypingEvent()` emite `chat.typing` para tenant room e ticket room
- [ ] `emitTypingEvent()` sem número não crasha (log + early return)
- [ ] `emitTypingEvent()` sem ticket não crasha (emite sem `ticket_id`)

---

## Entrega 2 — Backend: emitTyping e validar subevent ✅ testável

**Entrega:** ChatBroadcastService emite `chat.typing` | **Agente:** @BACKEND

**Gate:** `cd api && composer gate:all`

### TASK-024.2.1 — Adicionar `emitTyping()` em `ChatBroadcastService`

**Status:** done

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Adicionar o método `emitTyping()` em `ChatBroadcastService.php` para emitir o evento `chat.typing` via GatewayBroadcastService. Este método permite que o backend emita typing events se necessário (ex: via gateway relay).

**Constraints**

- `declare(strict_types=1)`
- phpDoc obrigatório no método
- Seguir padrão existente dos outros métodos `emit*()`
- O evento deve ser emitido via WebSocket para a room do tenant

**Context References**

- `api/src/Domain/Chat/Services/ChatBroadcastService.php` _(required in context)_

**Code Context**

```php
// Expected — método emitTyping()
// Assinatura real de GatewayBroadcastService::broadcastEvent():
//   broadcastEvent(string $event, array $data, ?string $room = null)
//   → $room é opcional e identifica a sala; se null, emite para todas

/**
 * Emitir evento de digitação para a sala do tenant.
 *
 * @param  string  $tenantId  ID do tenant.
 * @param  string  $ticketId  ID do ticket.
 * @param  bool  $isTyping  true = digitando, false = parou.
 * @param  string|null  $presence  'composing' | 'recording' | 'paused' | null.
 */
public function emitTyping(string $tenantId, string $ticketId, bool $isTyping, ?string $presence = null): void
{
    $this->gateway->broadcastEvent(
        'chat.typing',
        [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'is_typing' => $isTyping,
            'presence' => $presence,
        ],
        "tenant:{$tenantId}",
    );
}
```

**Etapas**

- [x]   1. Editar `api/src/Domain/Chat/Services/ChatBroadcastService.php` — adicionar método `emitTyping()`
- [x]   2. Verificar `cd api && composer gate:all`

**Critérios de conclusão**

- [x] `ChatBroadcastService::emitTyping()` existe e é callable
- [x] Emite `chat.typing` via GatewayBroadcastService
- [x] PHPStan não reporta erros

---

### TASK-024.2.2 — Adicionar `typing` em `VALID_SUBEVENT_TYPES`

**Status:** done

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Adicionar `'typing'` à constante `VALID_SUBEVENT_TYPES` em `ChatActivityBroadcastService.php` para permitir que typing events sejam broadcastados como atividades.

**Context References**

- `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php` _(required in context)_

**Etapas**

- [x]   1. Editar `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php` — adicionar `'typing'` em `VALID_SUBEVENT_TYPES`
- [x]   2. Verificar `cd api && composer gate:all`

**Critérios de conclusão**

- [x] `'typing'` está em `VALID_SUBEVENT_TYPES`
- [x] PHPStan não reporta erros

### TASK-024.2.3 — Testes para `emitTyping()` no backend

**Status:** done

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Escrever testes unitários para o método `emitTyping()` em `ChatBroadcastService`.

**Etapas**

- [x]   1. Criar/atualizar teste em `tests/Unit/Domain/Chat/Services/ChatBroadcastServiceTest.php`:
    - `test_emit_typing_calls_broadcast_event_with_correct_params` — verifica que `broadcastEvent` é chamado com `'chat.typing'`, `data` contendo `tenant_id`, `ticket_id`, `is_typing`, `presence`, e `room` correto
    - `test_emit_typing_with_null_presence` — presencia null não crasha
- [x]   2. Verificar `cd api && composer gate:all`

---

## Notas

**Critérios de conclusão**

- [ ] `emitRealtime()` chama `emitTypingEvent()` para semanticType `'presence'`
- [ ] `emitTypingEvent()` resolve ticket pelo número do contato
- [ ] `emitTypingEvent()` emite `chat.typing` para tenant room e ticket room
- [ ] `emitTypingEvent()` sem número não crasha (log + early return)
- [ ] `emitTypingEvent()` sem ticket não crasha (emite sem `ticket_id`)

### TASK-024.1.6 — Cenários de teste para fluxo typing (Gateway)

**Status:** todo

**Plano origem:** PLAN-024-bugfix-uazapi-typing-inbound

**Goal**

Escrever testes unitários cobrindo o fluxo completo de typing no gateway.

**Context References**

- `gateway/src/domains/chat/services/chat-webhook.service.ts` _(required in context)_

**Etapas**

- [ ]   1. Criar/atualizar teste `chat-webhook.service.spec.ts` com cenários:
    - `test_emits_chat_typing_when_presence_webhook_arrives_composing` — presença `composing` gera `chat.typing` com `is_typing: true`
    - `test_emits_chat_typing_when_presence_webhook_arrives_paused` — presença `paused` gera `chat.typing` com `is_typing: false`
    - `test_presence_without_ticket_does_not_crash` — webhook sem ticket correspondente não lança exceção
    - `test_presence_event_bypasses_idempotency` — eventos presence duplicados são emitidos (bypass confirmado)
    - `test_normalizes_presence_event_correctly` — `UazapiProvider.normalize()` extrai presence fields corretamente
- [ ]   2. Verificar `cd gateway && pnpm test`

---

## Notas

- A infraestrutura frontend já existe e NÃO precisa de modificação
- O fluxo OUTBOUND (agente → contato via `composing`) já funciona e NÃO deve ser alterado
- A entrega 1 (Gateway) e entrega 2 (Backend) podem ser executadas em paralelo
- UazAPI DEVE enviar webhook de presence/composing para que o fluxo completo funcione — verificar com a equipe se o provider suporta este tipo de evento
