# FEATURE-002 — Corrigir loop de mensagens duplicadas no Autopilot

**Status:** Em desenvolvimento  
**Módulo:** API — `Domain/Chat` + `Domain/Ai`  
**Data:** 2026-05-03

---

## T — Problema e Objetivo

**Problema:** O módulo Autopilot está enviando a mesma mensagem várias vezes para o mesmo ticket. Cada resposta da AI aciona o pipeline de AI novamente, gerando um loop de respostas.

**Objetivo:** Eliminar o ciclo de reprocessamento fazendo com que mensagens outgoing não disparem o roteamento de mensagens inbound.

---

## A — Escopo e Limites

**Root Cause — Ciclo de Reprocessamento:**

```
[Inbound] → ConversationResolverJob → routeInbound() → AI responde
    ↓
SendChatMessageAction cria mensagem outgoing
    ↓
MessagePersisted::dispatch() com direction='outgoing'  ← necessário para timestamps
    ↓
MessagePersistorListener::handle() → ConversationResolverJob::dispatch()  ← BUG: não verifica direction
    ↓
routeInbound() recebe o texto da resposta da AI como se fosse mensagem do cliente
    ↓
AI processa a própria resposta → gera nova resposta → loop
```

**Por que o lock existente não protege:**
- `DispatchAutopilotRunJob` usa `message_id` como chave de lock
- Cada iteração cria uma nova mensagem com UUID diferente
- Portanto cada iteração tem lock key diferente → sem proteção

**Escopo do fix:**
- 1 arquivo modificado: `api/src/Domain/Chat/Listeners/MessagePersistorListener.php`
- Adicionar guard antes do `ConversationResolverJob::dispatch()` verificando `direction !== 'outgoing'`

**Fora do escopo:**
- Alterar `ChatWebhookRouter` (semântica já correta, não precisa mudar)
- Alterar `DispatchAutopilotRunJob` (lock continua válido para duplicatas de webhook)
- Alterar `SendChatMessageAction` (o dispatch de `MessagePersisted` para outgoing é necessário para activity tracking)

---

## C — Critérios de Aceite

- [ ] Autopilot envia **exatamente uma** resposta por mensagem inbound
- [ ] Push notifications para mensagens outgoing continuam funcionando (`dispatchNewMessagePush` não é afetado)
- [ ] Invalidação de summary continua funcionando para mensagens outgoing (`summaryService.invalidateSummary` não é afetado)
- [ ] Mensagens de agente humano (`source=agent`) não são afetadas
- [ ] Logs confirmam que `ConversationResolverJob` não é despachado para `direction='outgoing'`
- [ ] Gate `composer gate:all` passa sem erros

---

## R — Riscos Conhecidos

| Risco | Mitigação |
|-------|-----------|
| O `direction` pode não estar no contexto em outros callers de `MessagePersisted` | Verificado: o único caller que despacha com `direction='outgoing'` é `SendChatMessageAction` (linha 125–138). Outros callers (webhook ingestor) não incluem `direction` no contexto, portanto `($event->context['direction'] ?? '') === 'outgoing'` retorna `false` e o comportamento existente é preservado. |
| Push notification de mensagem outgoing pode parar | `dispatchNewMessagePush` é chamado **antes** do guard, não é afetado. |

---

## Tasks

### TASK-001 — Guard em MessagePersistorListener (Fix Principal)

**Arquivo:** `api/src/Domain/Chat/Listeners/MessagePersistorListener.php`

**Mudança:** Adicionar verificação de `direction` antes de despachar `ConversationResolverJob`:

```php
public function handle(MessagePersisted $event): void
{
    $this->summaryService->invalidateSummary($event->ticketId);
    $this->dispatchNewMessagePush($event);

    if (($event->context['direction'] ?? '') === 'outgoing') {
        return;
    }

    ConversationResolverJob::dispatch(
        tenantId: $event->tenantId,
        ticketId: $event->ticketId,
        body: $event->body,
        context: $event->context,
    );
}
```

**Critério de done:** Mudança aplicada, `composer gate:all` verde.

---

### TASK-002 — Teste de Regressão

**Arquivo:** `api/tests/Feature/Chat/MessagePersistorListenerTest.php` (novo ou existente)

**O que testar:**
- Quando `MessagePersisted` é disparado com `direction='outgoing'`, `ConversationResolverJob` **não** é despachado
- Quando `MessagePersisted` é disparado sem `direction` (inbound padrão), `ConversationResolverJob` **é** despachado
- `summaryService->invalidateSummary()` é chamado em ambos os casos

---

## Verificação E2E

1. Enviar mensagem inbound no WhatsApp para ticket com AI habilitada
2. Aguardar resposta do autopilot
3. Confirmar que **apenas uma** mensagem outgoing foi criada no banco
4. Verificar logs: `ConversationResolverJob` não deve aparecer com `ticket_id` do ticket após a resposta da AI
5. Verificar que o ticket não continua gerando respostas após 30 segundos
