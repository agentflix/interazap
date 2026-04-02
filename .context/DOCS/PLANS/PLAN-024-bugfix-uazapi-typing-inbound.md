# PLAN-024 — Bugfix: Status "Digitando" não aparece ao receber webhook de typing da UazAPI

## Objetivo

Implementar o fluxo INBOUND de typing status (contato → agente) para que o indicador "digitando..." apareça no frontend quando o contato está digitando via WhatsApp/UazAPI.

## Módulo relacionado

Gateway | Chat (Backend) | Chat (Frontend)

## PRD relacionado

PRD-UAZAPI-001 | PRD-CHAT-001

## Contexto do Bug

A infraestrutura frontend para `chat.typing` **já existe** (frontend ouve `chat.typing`, store tem `setTyping()`, indicador UI existe), mas **nada nunca emite esse evento**. O fluxo INBOUND está completamente ausente.

**Fluxo esperado (agora implementado):**
```
UazAPI webhook (contact typing)
  → ChatWebhookService::handle()
    → UazapiProvider::normalize()
      → extract presence/composing fields
    → emitRealtime()
      → emit 'chat.typing' WebSocket event
        → Frontend ChatRealtimeService recebe 'chat.typing'
          → UI mostra "digitando..."
```

**Fluxo atual (quebrado):**
- UazAPI envia webhook de typing/presence → `emitRealtime()` não tem case para `presence` → payload descartado silenciosamente

## Escopo

### Incluído

1. Gateway: atualizar `NormalizedUazapiEvent` (model) para incluir `presence?`, `is_typing?`, `number?` + `'presence'` no union de `event_type`
2. Gateway: adicionar `'presence'` em `EventType` e `number?` no `UazapiWebhookDto`
3. Gateway: extrair campos de presence/composing no `UazapiProvider::normalize()`
4. Gateway: adicionar `'presence'` em `PayloadSemanticMetadata.semanticType`
5. Gateway: adicionar constante `TYPING` em `CHAT_EVENTS`
6. Gateway: adicionar caso `presence semantic` no `PayloadSemanticsResolver`
7. Gateway: adicionar bypass de idempotência para `presence` events (igual a connection)
8. Gateway: adicionar case `presence` em `emitRealtime()` + criar `emitTypingEvent()`
9. Backend: adicionar método `emitTyping()` em `ChatBroadcastService.php` (assinatura corrigida)
10. Backend: adicionar `typing` ao `VALID_SUBEVENT_TYPES` em `ChatActivityBroadcastService`
11. Frontend: verificar que o fluxo `chat.typing` → `contactTyping` funciona end-to-end

### Excluído

- Alteração no comportamento OUTBOUND (agente → contato) — esse fluxo já funciona
- Implementação de typing para outros providers (Z-API, etc.) — foco em UazAPI
- Alteração de contrato de API pública
- Migração de banco de dados
- Frontend (infraestrutura já existente)

## Etapas propostas

1. **Gateway — Atualizar `NormalizedUazapiEvent` + adicionar `presence` em `EventType` e `number` no DTO** (model + dto)
2. **Gateway — Adicionar `typing` em `CHAT_EVENTS`** (`gateway.constants.ts`)
3. **Gateway — Normalizar presence/composing no `UazapiProvider::normalize()`** (`uazapi.provider.ts`)
4. **Gateway — Atualizar `PayloadSemanticMetadata` + adicionar `presence` no `PayloadSemanticsResolver`** (types + resolver)
5. **Gateway — Adicionar bypass idempotência + case `presence` em `emitRealtime()` + criar `emitTypingEvent()`** (`chat-webhook.service.ts`)
6. **Backend — Adicionar `emitTyping()` em `ChatBroadcastService`** (`ChatBroadcastService.php`)
7. **Backend — Adicionar `typing` em `VALID_SUBEVENT_TYPES`** (`ChatActivityBroadcastService.php`)
8. **Frontend — Verificar fluxo completo end-to-end** (sem alterações necessárias)
9. **QA + REVIEWER (2ª rodada)**

## Arquivos a Modificar

### Gateway (NestJS)

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `gateway.constants.ts` | modificar | `gateway/src/shared/constants/gateway.constants.ts` |
| `uazapi.dto.ts` | modificar | `gateway/src/domains/chat/providers/uazapi/uazapi.dto.ts` |
| `uazapi.model.ts` | modificar | `gateway/src/domains/chat/models/uazapi.model.ts` |
| `uazapi.provider.ts` | modificar | `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` |
| `chat-webhook.types.ts` | modificar | `gateway/src/domains/chat/services/chat-webhook.types.ts` |
| `payload-semantics-resolver.service.ts` | modificar | `gateway/src/domains/chat/services/payload-semantics-resolver.service.ts` |
| `chat-webhook.service.ts` | modificar | `gateway/src/domains/chat/services/chat-webhook.service.ts` |

### Backend (Laravel)

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `ChatBroadcastService.php` | modificar | `api/src/Domain/Chat/Services/ChatBroadcastService.php` |
| `ChatActivityBroadcastService.php` | modificar | `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php` |

### Frontend (Angular)

| Arquivo | Ação | Caminho |
|---------|------|---------|
| Nenhum (infraestrutura já existe) | verificar | `app/src/app/core/services/chat-realtime.service.ts` |

## Evidências da Codebase

### Gateway

- [x] `gateway/src/shared/constants/gateway.constants.ts` — `CHAT_EVENTS` sem `TYPING`
- [x] `gateway/src/domains/chat/providers/uazapi/uazapi.dto.ts` — `EventType` só aceita `'messages' | 'messages_update' | 'connection'`, sem campo `number`
- [x] `gateway/src/domains/chat/models/uazapi.model.ts` — `NormalizedUazapiEvent` sem campos `presence`/`is_typing`/`number`
- [x] `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` — `normalize()` não extrai `presence`/`composing`
- [x] `gateway/src/domains/chat/services/chat-webhook.types.ts` — `PayloadSemanticMetadata.semanticType` sem `'presence'`
- [x] `gateway/src/domains/chat/services/payload-semantics-resolver.service.ts` — sem case `presence`
- [x] `gateway/src/domains/chat/services/chat-webhook.service.ts` — `emitRealtime()` sem case para typing/presence; idempotência sem bypass para presence

### Backend

- [x] `api/src/Domain/Chat/Services/ChatBroadcastService.php` — sem método `emitTyping()`
- [x] `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php` — `VALID_SUBEVENT_TYPES` sem `typing`

### Frontend (infraestrutura existente, não modificar)

- [x] `app/src/app/core/services/chat-realtime.service.ts:316-328` — listener `chat.typing` ✅
- [x] `app/src/app/core/services/chat-realtime.store.ts:73-85` — `setTyping()` ✅
- [x] `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.store.ts:266-272` — `contactTyping` signal ✅

## Tarefas Derivadas para Execução Paralela

| Task | Descrição | Agente | Paralelo com |
|------|-----------|--------|------------|
| TASKS-024 (GW-typing) | Gateway: normalizer + emitRealtime + constants + types | @DEV | TASKS-024 (BE-typing) |
| TASKS-024 (BE-typing) | Backend: emitTyping + VALID_SUBEVENT_TYPES | @BACKEND | TASKS-024 (GW-typing) |

## Validação e Gates

- [ ] Gateway: `cd gateway && pnpm lint && pnpm test`
- [ ] Backend: `cd api && composer gate:all`
- [ ] Frontend: verificar que `cd app && pnpm run gate:all` passa (sem alterações)
- [ ] @QA: validar plano
- [ ] @REVIEWER: code review

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|-------------|---------|-----------|
| UazAPI não envia webhook de typing | Baixa | Alto | Verificar com equipe se UazAPI suporta webhook de presence; se não suportar, avaliar polling ou outra estratégia |
| Race condition no auto-clear de typing | Média | Baixo | Frontend já tem timeout de 10s; validar que múltiplos eventos não sobrepõem o timer |

### Dependências

- PRD-UAZAPI-001 — event `chat.typing` já definido na especificação (linha 1532)
- PRD-CHAT-001 — fluxo de realtime já documentado
- Infraestrutura frontend — já existente e funcional

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Média |
| Camadas afetadas | Gateway + Backend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Baixo (apenas adição, sem alteração de comportamento existente) |
