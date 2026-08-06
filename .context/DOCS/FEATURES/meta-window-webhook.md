# Meta WhatsApp — Hardening de webhook, janela e envio

**Status:** [ ] Em planejamento | [ ] Em execução | [x] Concluída
**Data:** 2026-08-04
**PRD:** — *(correção evolutiva da FEAT-006, orientada pela skill `meta-whatsapp-expert`)*

## Metadados

- **ID:** FEAT-006
- **Bounded Context:** Chat (`api/src/Domain/Chat` + `gateway/src/domains/chat` + `app/src/app/pages/chat`)
- **Complexidade:** G
- **Status:** 🟡 Reaberta em Planning após auditoria
- **Tasks:** `.context/DOCS/TASKS/meta-window-webhook-tasks.md`

## Resumo

A primeira entrega implementou Graph API v25.0, parsing em lote, janela 24h/72h, referral CTWA, status `failed`, envio de texto/template e badge no composer. A auditoria de 2026-08-04 encontrou riscos residuais bloqueantes no pipeline: eventos Meta publicados como Z-API, credencial composta usada e registrada indevidamente, ACK síncrono, idempotência não atômica, janela vulnerável a concorrência e autorização de texto livre com escopo incorreto.

Este ciclo endurece o fluxo ponta a ponta sem substituir a arquitetura vigente: Gateway continua sem PostgreSQL, API permanece autoritativa para tenant/janela e App falha fechado enquanto o estado Meta não for conhecido.

## Baseline já entregue

- [x] Graph API pinada em v25.0
- [x] HMAC sobre corpo bruto com comparação timing-safe
- [x] Parsing completo de `entry[] → changes[] → messages[]|statuses[]`
- [x] Resolução por `phone_number_id`/WABA via API interna
- [x] Persistência de janela 24h/72h e referral CTWA
- [x] Fallback de janela por última inbound
- [x] `failed` e `errors[]` preservados no ingestor ativo
- [x] Texto e template enviados pela Cloud API
- [x] Badge Meta e modo `template-only`

## Problemas confirmados

| # | Severidade | Problema | Evidência |
|---|---|---|---|
| A | 🔴 | Evento normalizado Meta vira `provider: 'zapi'` e perde a chave idempotente do adapter | `gateway/src/domains/chat/services/chat-webhook.service.ts:170`; `chat-webhook-event-normalizer.service.ts:347-375` |
| B | 🔴 | `phoneNumberId:accessToken` inteiro é usado na listagem de templates, aparece em cache/log e respostas sem WAMID são aceitas | `gateway/src/domains/chat/providers/meta/meta.adapter.ts:124-150,179-215`; `meta.client.ts:95-125,187-192` |
| C | 🔴 | `META_APP_SECRET` e verify token admitem vazio | `gateway/src/core/config/configuration.ts:234-235`; `meta-webhook.controller.ts:87-113` |
| D | 🔴 | ACK aguarda lookup HTTP, Redis, realtime e stream; lote é processado serialmente | `gateway/src/domains/chat/controllers/meta-webhook.controller.ts:135-154`; `services/chat-webhook.service.ts:159-183` |
| E | 🔴 | Idempotência na API é check-then-insert, sem unicidade por instância/canal; status busca apenas tenant + external ID | `api/src/Domain/Chat/Actions/ChatWebhookIngestor.php:317-326,573-578` |
| F | 🔴 | `GREATEST` da janela é calculado em memória; concorrência pode encurtar CTWA | `api/src/Domain/Chat/Services/MetaWindowService.php:121-172` |
| G | 🔴 | Fallback usa tenant + contato, podendo misturar ticket/instância/canal; inclui potencial mensagem system | `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php:43-61` |
| H | 🔴 | BOT/IA podem enviar texto livre fora da janela e ausência de ticket/contact falha aberto | `api/src/Domain/Chat/Actions/SendChatMessageAction.php:294-324`; `ChatAutoReplyResponder.php:91-173` |
| I | 🟡 | Lookup global escolhe primeira instância sem unicidade/ambiguidade fail-closed | `LookupInstanceByPhoneNumberAction.php:27-32`; `LookupInstanceByWabaIdAction.php:34-41` |
| J | 🔴 | Composer não reage ao instante de expiração e provider desconhecido falha aberto | `app/src/app/pages/chat/chat.store.ts:94-108,143-159` |
| K | 🔴 | Respostas assíncronas antigas podem aplicar janela de outro ticket/instância | `app/src/app/pages/chat/chat.ts:311-328`; `new-conversation-modal.ts:193-216,282-297` |
| L | 🟡 | Gate documentado do App está inválido para o builder Angular atual | `app/package.json` (`ng test --watch=false`) |

## Escopo

### Incluído

- [ ] Preservar provider Meta e idempotency key até o Redis Stream
- [ ] Separar identificadores de credenciais; segredo nunca em logs/chaves
- [ ] Validar configuração Meta obrigatória em startup
- [ ] ACK rápido após enfileiramento durável no Gateway
- [ ] Idempotência atômica e escopada por tenant + instância/canal + external ID
- [ ] Atualização atômica da janela com `GREATEST`/guard no PostgreSQL
- [ ] Verificação de janela por ticket/instância Meta e exclusão de system
- [ ] Aplicar janela a todo texto livre outbound, inclusive BOT/IA, com fail-closed
- [ ] Lookup Meta rejeitar instância inativa ou identificador ambíguo
- [ ] Composer reagir à expiração e falhar fechado durante loading/erro
- [ ] Cancelar/ignorar respostas antigas ao trocar ticket ou instância
- [ ] Restaurar gate `test:run` do App
- [ ] Cobertura regressiva cross-layer para provider, tenant, concorrência e ACK

### Fora de Escopo

- Envio/download de mídia Meta e voice note via `media_id`
- Backfill ou alteração destrutiva de dados de produção
- Múltiplos Apps Meta/secrets por tenant
- Reactions e edições de mensagem
- Upgrade além da Graph API v25.0

## Critérios de Aceite

- [ ] Stream originado da Meta mantém `provider=meta` e chave `meta:{instanceId}:...`
- [ ] Access token não aparece em log, nome de chave Redis ou mensagem de erro
- [ ] Aplicação não inicia com secret/verify token Meta vazios quando integração está habilitada
- [ ] POST válido retorna ACK após persistência durável, sem aguardar lookup/processamento do lote
- [ ] Duas entregas concorrentes do mesmo WAMID geram uma única mensagem por instância
- [ ] Mesmo WAMID em instâncias distintas não colide
- [ ] Duas atualizações concorrentes nunca reduzem uma janela 72h válida
- [ ] Janela consultada pertence ao ticket e à instância selecionados; system não renova fallback
- [ ] Agente, BOT e IA fora da janela Meta só enviam template aprovado
- [ ] Lookup ambíguo/inativo não roteia webhook para tenant arbitrário
- [ ] Composer muda para `template-only` ao expirar sem depender de nova requisição
- [ ] Provider em loading/erro e resposta obsoleta nunca liberam texto livre
- [ ] `pnpm --filter app test:run` executa uma vez e retorna código correto
- [ ] Gates finais de API, Gateway e App ficam verdes

## Dependências

- **Módulos:** Chat API, Chat Gateway, Chat App, Redis/BullMQ
- **Externas:** Meta Cloud API v25.0
- **Arquitetura:** Gateway → API apenas HTTP `/internal`; API → Gateway por Redis Streams; Gateway nunca acessa PostgreSQL
- **Design:** `.context/DESIGN/meta-window-badge.md`

## Fases estimadas

- [x] **Fase 1 — Planning** ✅
- [ ] **Fase 2 — Tooling** — gate App executável (implementada — aguarda phase-close)
- [ ] **Fase 3 — Backend** — Gateway + API (implementada — aguarda phase-close)
- [ ] **Fase 4 — Frontend** — estado temporal e concorrência (implementada — aguarda phase-close)
- [ ] **Fase 5 — Integration** — regressão cross-layer e gates finais (executada — aguarda phase-close)

## Evidência da auditoria

- Gateway Meta: 43 testes direcionados passaram, mas não cobriam provider final/token composto/ACK.
- API Meta: 22 testes passaram; 1 teste falhou por fixture dependente da data atual.
- App: `test:run` falhou antes de iniciar specs porque o builder rejeita `--watch=false`.
- Convenções da skill regeneradas para o InteraZap em 2026-08-04.

## Estado da execução (ciclo de hardening — 2026-08-05)

### Implementado

- [x] Mapper neutro preserva `provider: meta` e `idempotencyKey` até o Redis Stream (TASK-3.1.1)
- [x] Token composto separado na listagem/envio; segredo fora de cache/log; resposta sem WAMID falha (TASK-3.1.2)
- [x] `META_APP_SECRET`/`META_VERIFY_TOKEN` obrigatórios com fail-closed no controller (TASK-3.1.3)
- [x] ACK após enqueue BullMQ durável; lookup/normalização/publicação no processor com retry/DLQ (TASK-3.1.4)
- [x] Identidade única por tenant+instance+external_id e unicidade de phone_number_id/waba_id ativos (TASK-3.2.1)
- [x] Ingestão e status atômicos via `chat_message_identities` (insertOrIgnore) e escopo por instância (TASK-3.2.2)
- [x] Janela com UPDATE SQL atômico (`GREATEST` + `CASE` + guard `IS DISTINCT FROM`) (TASK-3.2.3)
- [x] Verificação de janela por ticket/instância, excluindo `type=system`, contexto ausente falha fechado (TASK-3.2.4)
- [x] Guard de janela aplicado a agente, BOT e IA; template aprovado é a única exceção (TASK-3.2.5)
- [x] Composer reativo ao deadline com relógio compartilhado; loading/erro fail-closed (TASK-4.1.1)
- [x] Race de ticket A→B descartada no effect de janela (TASK-4.1.2)
- [x] Race de instância no modal de nova conversa descartada por validação de contexto (TASK-4.1.3)
- [x] Gate Vitest do App restaurado (`--no-watch`) (TASK-2.1.1)

### Gates deste ciclo

| Camada | Resultado |
|---|---|
| gateway | `test` 1427 ✓ · `test:e2e` 31 ✓ · `build` ✓ |
| app | `build` ✓ · specs da feature 52 ✓ (chat.store 22, chat 7, modal 23) |
| api | `composer gate:all` 🎉 (Pint + Larastan + Pest 3039 ✓ + Rector) |
| fronteiras | driver banco/migrations/LLM/any → OK |

### Falhas pré-existentes não relacionadas (fora do escopo)

- `app` — `bearer.interceptor.spec.ts` (1 teste: interceptor injeta Bearer em web quando há token em memória; falha isolada, presente sem as mudanças desta feature) e `main-layout.spec.ts` (4 testes: `TrialBannerComponent` sem mock de subscription service). Não tocadas — registrar no phase-close final.
