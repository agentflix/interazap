# Meta Webhook — Hardening: decisões técnicas (2026-08-06)

**Feature:** FEAT-006 (`meta-window-webhook`) · **Contexto:** Chat (api + gateway + app)

## Decisões arquiteturais

### Chave de idempotência canônica vem do adapter
- `NormalizedWebhookEvent.idempotencyKey` (Meta: `meta:{instanceId}:{wamid}`) é a fonte
  canônica até o Redis Stream; fallback determinístico (`WebhookIdempotencyService`)
  só quando ausente. Removido o acoplamento `idempo:{provider}:{eventType}:{token}:{msg}`.

### Tabela auxiliar para unicidade em tabela particionada
- `chat_messages` é particionada por RANGE(created_at) — PK `(id, created_at)` impede
  UNIQUE no escopo tenant+instância+external_id. Solução: `chat_message_identities`
  (não particionada) com UNIQUE nesse escopo + reserva atômica via `insertOrIgnore`.
- Unicidade de `phone_number_id`/`waba_id` Meta só entre instâncias ATIVAS (índice
  único parcial com `WHERE is_active = true`) — inativar libera reconfiguração.

### Janela com decisão no banco (UPDATE atômico)
- `GREATEST(COALESCE(atual, ?), ?)` + `CASE` de tipo + guard `IS DISTINCT FROM` no
  WHERE — 0 linhas quando idêntico. Elimina lost update do read/compare/save.

### Fail-closed como princípio transversal
- Config Meta ausente → 403 (nunca HMAC com chave vazia).
- Janela loading/erro/contexto desconhecido → `template-only` (nunca `free`).
- Provider desconhecido no app → `template-only` até o mapa de instâncias carregar.
- Ticket/contato ausente no guard → bloqueio (antes liberava).

### Guard de janela unificado (agente/BOT/IA)
- `SendChatMessageAction.guardMetaWindow` cobre `SOURCE_AGENT|BOT|AI`; template
  aprovado (`type != 'text'`) é a única exceção. IA recebe `ToolResultDTO::failure`
  com `reason: window_guard`; BOT bloqueia silenciosamente (job não quebra).

## Dívidas / riscos conhecidos

- Identidades órfãs em `chat_message_identities` se a criação da mensagem falhar
  após a reserva (reserva fora de transação) — risco baixo (retry do stream).
- ACK é após enqueue durável, não após persistência na API — a API responde ao
  stream; reentrega da Meta é deduplicada na API pela identidade única.

## Falhas pré-existentes não relacionadas (registradas no phase-close)

- `app`: `bearer.interceptor.spec.ts` (1 teste) e `main-layout.spec.ts`/TrialBanner
  (4 testes) falham também sem as mudanças da feature — fora do escopo; decidir
  correção em ciclo próprio.
- `git stash@{0}` (`all-temp2`) é stash pré-existente do usuário — não consumido.
