---
name: autopilot
description: >-
  Deep knowledge of the InteraZap Autopilot module, including its architecture,
  data flow, key files, caching layers, and known invariants. Use when working
  on autopilot bugs, features, or analysis. Triggers on: "autopilot",
  "AI agent", "DispatchAutopilotRunJob", "AiContextBuilderService",
  "conversation_history", "ai.run.request", "snapshot", or any task involving
  the AI response flow from inbound message to AI reply. Do NOT use for general
  Laravel/NestJS questions unrelated to the autopilot pipeline.
license: CC-BY-4.0
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# Autopilot Module

O autopilot é o pipeline que transforma uma mensagem inbound em uma resposta de AI. Atravessa três workspaces: `api/` (Laravel), `gateway/` (NestJS), `app/` (Angular).

## Fluxo Canônico

```
Mensagem chega (WhatsApp/Webchat)
  → AutopilotRunDispatcherListener   [api - síncrono, max 30s]
  → DispatchAutopilotRunJob          [api - fila 'ai', timeout 300s]
      ├── resolve agent + trigger
      ├── AutopilotRunSnapshotResolver → prompt + tools
      ├── AiContextBuilderService.build() → contexto rico com conversation_history
      └── AutopilotRunStreamPublisher → XADD ai.run.request
  → AiRunRequestConsumer             [gateway - consumer group]
  → AiRunOrchestratorService.execute()
      ├── ContextWindowService.resolveContext()   → usa snapshot se isRecent (<90s)
      ├── PromptAssemblerService.resolvePrompt()
      └── buildMessages() → [system, context, history..., user]
  → OpenAI API (stream)
  → tool calls se necessário (send_message, search_knowledge, etc.)
  → Gateway emite WebSocket webchat:ai_response / whatsapp:message
```

## Mapa de Arquivos Críticos

### api/ (Laravel)

| Arquivo | Papel |
|---------|-------|
| `Domain/Ai/Jobs/DispatchAutopilotRunJob.php` | Ponto central do dispatch. Resolve agent, monta snapshot, publica no stream. **Injetar AiContextBuilderService aqui.** |
| `Domain/Ai/Services/AiContextBuilderService.php` | Constrói contexto rico: ticket, contact, `conversation_history` (últimas 15 msgs DESC→reverse), current_input, user_context. |
| `Domain/Ai/Services/AutopilotRunSnapshotResolver.php` | Resolve prompt + tools para hydration. **Não inclui conversation_history** — só {ticket_id, tenant_id, status, subject}. |
| `Domain/Ai/Services/AutopilotRunStreamPublisher.php` | XADD no stream `ai.run.request`. `normalizeStreamFields()` serializa arrays como JSON string. |
| `Domain/Ai/Http/Controllers/InternalAiController.php` | Endpoints fallback para gateway: `/internal/ai/context/{id}`, `/internal/ai/prompt/{id}`, `/internal/ai/tools/{id}`. Retorna contexto mínimo (sem history). Cacheia 5min. |
| `Domain/Ai/Listeners/AutopilotRunDispatcherListener.php` | Escuta eventos de mensagem, despacha o job. |
| `Domain/Chat/Models/ChatMessage.php` | ID = orderedUUID. Campos chave: `direction` (incoming/outgoing), `is_from_contact`, `content`, `ticket_id`, `created_at`. |

### gateway/ (NestJS)

| Arquivo | Papel |
|---------|-------|
| `domains/ai/consumers/ai-completion.consumer.ts` | Lê stream Redis. `getContextSnapshot()` chama `parseJsonObject()` na string JSON do campo `context`. |
| `domains/ai/services/ai-run-orchestrator.service.ts` | Orquestra prefetch (prompt+context+tools em paralelo) → `buildMessages()` → loop de tool calls. |
| `domains/ai/services/context-window.service.ts` | Resolve context com 3 camadas: snapshot (90s) → Redis cache (30min) → API interna. |
| `domains/ai/services/orchestration/message-builder.service.ts` | `buildMessages()`: monta [system, system:context, system:tools, ...history, user?]. `readConversationHistory()` lê array de strings. `expandConversationHistory()` converte "Agent: x" → role:assistant. `shouldSkipInputText` evita duplicar current_input. |
| `shared/utils/payload-reader.util.ts` | `parseJsonObject()`: aceita objeto ou string JSON. `parseJsonArray()`: aceita array ou string JSON. |
| `shared/utils/snapshot.util.ts` | `isRecent(hydratedAt, threshold=90000ms)`: true se snapshot < 90s. |

### app/ (Angular)

| Arquivo | Papel |
|---------|-------|
| `pages/webchat/services/webchat.service.ts` | State de mensagens (signal). `addMessage()` com dedup por id. `compareWebChatMessagesAsc()`: ordena por createdAt ASC → id ASC (numérico quando possível). WebSocket events: `webchat:ai_response`, `webchat:agent_message`, `webchat:sent`. |

## Invariantes e Regras

### Conversation History
- `AiContextBuilderService` busca últimas **15 mensagens** (`DEFAULT_HISTORY_LIMIT`) com `orderByDesc('created_at', 'id')` → `reverse()` → ordem cronológica ASC.
- Formato das linhas: `"Agent: <content>"` ou `"User: <content>"`.
- `is_from_contact = true` OR `direction = 'incoming'` → "User". Caso contrário → "Agent".
- Mensagens com `content` vazio e sem transcrição de mídia são **filtradas** (`filter(fn => $value !== null && $value !== '')`).

### Serialização Redis Stream
- PHP `normalizeStreamFields()`: arrays → `json_encode()` → string no stream.
- Gateway `parseJsonObject()`: string JSON → `JSON.parse()` → objeto JS. Arrays internos preservados.
- `conversation_history` chega ao gateway como `string[]` após parse. `readConversationHistory()` filtra `typeof line === 'string'`.

### Cache Layers (ordem de prioridade)
1. **Snapshot hydration** (90s) — contexto embutido no payload do stream pelo job. Bypass total do cache.
2. **Redis gateway** (`autopilot:context:{ticketId}`, 30min) — fallback quando snapshot vencido.
3. **Laravel Cache** (`internal:ai:context:{ticketId}`, 5min) — fallback do endpoint HTTP.

> **Regra crítica:** Conversation history NUNCA deve ser cacheada via camadas 2/3 — é dinâmica. Só a camada 1 (snapshot, gerado per-message no job) garante history fresco.

### shouldSkipInputText
No `buildMessages()`, `inputText` (current_input) só é adicionado como `[user]` se NÃO for igual ao último item do history. Previne duplicata quando a mensagem atual já está no `conversation_history` (dentro das últimas 15).

### Idempotência
`acquireMessageDispatchLock(messageId)` — Redis SET NX EX 60 por `messageId`. Evita double-dispatch da mesma mensagem.

### Sticky Agent
Se `ChatTicket.current_ai_agent_id` está preenchido, o job usa esse agent diretamente (bypass de trigger lookup). Delegação atualiza esse campo.

## Pontos de Falha Conhecidos

| Sintoma | Causa Provável | Onde Investigar |
|---------|---------------|-----------------|
| AI repete greeting / ignora histórico | `conversation_history` vazio no contexto | `DispatchAutopilotRunJob` — AiContextBuilderService sendo chamado? `$snapshot['context']` tem `conversation_history`? |
| Mensagens fora de ordem no webchat | `compareWebChatMessagesAsc` — IDs ou timestamps idênticos | `webchat.service.ts:compareWebChatMessagesAsc` |
| AI não dispara | Lock Redis ainda ativo | `autopilot:lock:tenant:{t}:msg:{m}` — TTL 60s |
| Contexto stale (sem history recente) | Cache Redis 30min no gateway | `autopilot:context:{ticketId}` — invalidar manualmente ou aguardar TTL |
| Tool call loop infinito | `maxToolIterations` atingido | `AiRunOrchestratorService` — config `AI_MAX_TOOL_ITERATIONS` |

## Checklist Antes de Alterar Este Módulo

- [ ] `AiContextBuilderService.build()` está sendo chamado no job (não no snapshot resolver)?
- [ ] Mudanças no shape do context mantêm `conversation_history` como `string[]`?
- [ ] Novo campo no stream payload está em `normalizeStreamFields()` (PHP) E lido via `getStringField`/`parseJsonObject` (TS)?
- [ ] Cache invalidado se mudança afeta context ou prompt?
- [ ] Teste com ticket real: history chega ao AI com User/Agent corretos?

## Como Testar via Tinker

```php
// Verificar history que chegaria à AI para uma mensagem específica
$ticket = \Domain\Chat\Models\ChatTicket::find('TICKET_UUID');
$message = \Domain\Chat\Models\ChatMessage::find('MESSAGE_UUID');
$builder = app(\Domain\Ai\Services\AiContextBuilderService::class);
$ctx = $builder->build($ticket, $message);

// Inspecionar
dump(count($ctx['conversation_history'])); // deve ser > 0
dump($ctx['conversation_history']);        // deve alternar Agent/User em ordem cronológica
dump($ctx['current_input']);               // deve ser o conteúdo da $message
```
