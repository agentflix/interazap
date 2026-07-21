# Meta WhatsApp — Janela 24h/72h CTWA, roteamento por número e correção do envio

**Status:** [x] Em planejamento | [ ] Em execução | [ ] Concluída
**Data:** 2026-07-21
**PRD:** — *(derivado de análise comparativa com `suportefy-supabase` + skill `meta-whatsapp-expert`)*

## Metadados

- **ID:** FEAT-006
- **Bounded Context:** Chat (`api/src/Domain/Chat` + `gateway/src/domains/chat` + `app/src/app/pages/chat`)
- **Complexidade:** G
- **Status:** 🟡 Em Planning

## Visão Geral

A integração Meta Cloud API do InteraZap tem toda a estrutura montada — provider, adapter, client, controller de webhook, templates HSM, UI de composer com modo `template-only` — mas a **ligação entre as peças está quebrada** e a **janela de atendimento de 72h (CTWA) não existe**.

Esta feature conserta o plumbing do webhook, introduz a janela de atendimento persistida (24h/72h) com a semântica oficial da Meta, e restaura o envio (texto livre dentro da janela + template fora dela), que hoje falha em 100% dos casos.

A referência é a implementação em produção do **suportefy-supabase** (`supabase/functions/meta-whatsapp-webhook/index.ts`), combinada com os princípios da skill `meta-whatsapp-expert` (`~/Documents/prompts/SKILLS/meta-whatsapp-expert/`), destilada de um incidente real de produção (26 conversas travadas em 3 organizações — ticket ATD-038677).

## Problemas confirmados com evidência

| # | Severidade | Problema | Evidência |
|---|---|---|---|
| A | 🔴 Bloqueante | `phone_number_id` é passado como se fosse `webhookToken`. Duas resoluções de instância concorrentes e contraditórias — a segunda lança `Webhook token mismatch` sempre que `webhook_token != phone_number_id` | `gateway/src/domains/chat/controllers/meta-webhook.controller.ts:121` → `services/chat-webhook.service.ts:69` → `providers/meta/meta.adapter.ts:355` |
| B | 🔴 | Só processa `entry[0].changes[0]`, `messages[0]`, `statuses[0]`. A Meta agrupa eventos em lote → mensagens perdidas silenciosamente | `providers/meta/meta.provider.ts:67-70,171,195` |
| C | 🔴 | Janela 72h CTWA inexistente. `msg.referral` e `conversation.expiration_timestamp` são descartados pelo normalizer; não há campo persistido | `contracts/meta-provider.interface.ts:83-151`; `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php` |
| D | 🔴 | `MetaAdapter.sendText` **sempre** retorna `success:false`. Dentro da janela de 24h a Meta aceita texto livre | `providers/meta/meta.adapter.ts:43-56` |
| E | 🔴 | `resolveInstanceToken` devolve `webhook_token` para Meta, mas o adapter espera `phoneNumberId:accessToken` → `sendTemplate` falha com "Invalid instance token format" | `api/src/Domain/Chat/Services/ChatMessageGatewayDispatcher.php:424` vs `meta.adapter.ts:407-416` |
| F | 🟡 | Graph API pinada em **v18.0**; a skill pina **v25.0** e v18 está no fim da janela de deprecação | `gateway/src/core/config/configuration.ts:238`, `providers/meta/meta.client.ts:74,78` |

## Módulos Afetados

- [x] api/ (Laravel 12) — migration, model, service de janela, ingestor, action de verificação, dispatcher
- [x] gateway/ (NestJS 11) — contrato do payload, normalizer, adapter, controller, client, config
- [x] app/ (Angular 20) — model, store, composer, modal de nova conversa
- [ ] Infraestrutura

## Escopo

### Incluído

- [ ] Roteamento correto do webhook Meta por `phone_number_id` (multi-número no mesmo App)
- [ ] Processamento de lote completo (`entry[] → changes[] → messages[]|statuses[]`)
- [ ] Idempotência estável por `wamid` (sem `Date.now()` na chave)
- [ ] Janela de atendimento persistida em `chat_tickets` (24h/72h + campos CTWA)
- [ ] Renovação da janela a **cada inbound do cliente**, com `GREATEST` e guard "só grava se mudou"
- [ ] Captura de `conversation.expiration_timestamp` + `origin.type` nos status de saída
- [ ] Captura de `messages[].referral` (CTWA) abrindo 72h na própria inbound
- [ ] `VerifyContactWindowAction` com campo persistido + fallback por mensagens
- [ ] Status `failed` como estado próprio, com `errors[].code` registrado
- [ ] `MetaAdapter.sendText` real (texto livre dentro da janela)
- [ ] `resolveInstanceToken` correto para Meta (`phoneNumberId:accessToken`)
- [ ] Upgrade Graph API v18.0 → v25.0
- [ ] Badge de janela (24h / 72h CTWA + tempo restante) no composer

### Fora de Escopo

- Secret por instância na URL / múltiplos Apps Meta por tenant (evolução futura — decisão registrada em Notas)
- Download de mídia inbound via `GET {GRAPH}/{media_id}` (fluxo separado)
- Backfill de tickets travados em produção (feito depois, com dry-run + skill `db-safety-guard`)
- Reactions e edições de mensagem via Meta

## Critérios de Aceite

- [ ] Webhook com 2 `entry`, cada um com 2 `messages`, persiste 4 mensagens (hoje persiste 1)
- [ ] Webhook cujo `phone_number_id` pertence à instância X não gera `Webhook token mismatch` e resolve tenant/instância de X
- [ ] Inbound do cliente com janela expirada faz `meta_window_expires_at = msg.timestamp + 24h` e `meta_window_type = '24h'`
- [ ] Inbound com `msg.referral` grava `meta_referral_*` e `meta_window_type = '72h'` com expiração `msg.timestamp + 72h`
- [ ] Janela de 72h ainda válida **não** é rebaixada para 24h por um inbound novo (`GREATEST`)
- [ ] `UPDATE` na janela só ocorre quando `expires_at` **ou** `type` mudam
- [ ] `GET /api/chat/contacts/{id}/window-status` retorna `expiresAt` e `windowType`, e cai no cálculo por mensagens quando o campo persistido está ausente ou no passado
- [ ] Status `failed` da Meta é persistido como `failed` (não mascarado como `sent`) com `errors[].code` em `metadata`
- [ ] Envio de texto livre por instância Meta dentro da janela retorna `success:true` com `messages[0].id`
- [ ] Envio de template por instância Meta não retorna "Invalid instance token format"
- [ ] `grep -rn "graph.facebook.com/v" gateway/src` retorna apenas `v25.0`
- [ ] `phone_number_id` desconhecido responde `200 OK` (nunca 4xx — evita reentrega em loop da Meta)

## Design

Artefato obrigatório antes das tasks de Frontend: `.context/DESIGN/meta-window-badge.md` (gerado pela TASK-2.1.1).

## Tasks

Ver `.context/DOCS/TASKS/meta-window-webhook-tasks.md`

## Dependências

- **Features:** nenhuma
- **Módulos:** `api` (PostgreSQL, Redis) · `gateway` (api via HTTP, Redis, Graph API) · `app` (api/gateway via REST/WS)
- **Externas:** Meta Graph API v25.0 · instância Meta com `settings_json.phone_number_id` e `settings_json.access_token` preenchidos (`api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php:90-91`)

## Fases Estimadas

- [x] **Fase 1 — Planning** ✅
- [x] **Fase 2 — Design** (badge de janela no composer) — 1 task ✅
- [x] **Fase 3 — Backend** (gateway + api + migration) — 12 tasks ✅
- [x] **Fase 4 — Frontend** (model, store, modal) — 3 tasks ✅
- [x] **Fase 5 — Integration** (fixtures + E2E do webhook) — 1 task ✅

### Gates (medidos pelo coordenador, não apenas relatados)

| Camada | Gate | Resultado |
|---|---|---|
| api | Pint | `{"result":"pass"}` exit 0 |
| api | Larastan | `[OK] No errors` (1315 arquivos) exit 0 |
| api | Pest | `3023 passed, 0 failed` exit 0 |
| api | Rector | `[OK] Rector is done!` exit 0 |
| gateway | `test` | `1394 passed` — 3 execuções idênticas |
| gateway | `test:e2e` | `6 suites / 31 passed` — 3 execuções idênticas |
| gateway | `test:e2e -- meta-webhook` | `5 passed` — prova do defeito raiz |
| gateway | `build` | 0 erros |
| app | specs da feature | `58 passed` (4 arquivos) |

### 7º problema — descoberto só na verificação

Além dos 6 do diagnóstico inicial, a revisão encontrou o mais grave: o `ValidationPipe`
global (`whitelist: true, forbidNonWhitelisted: true`, `gateway/src/main.ts:125-131`)
rejeitava com **400** todo payload real da Meta, porque o `@Body()` era tipado com um
DTO parcial que não declarava `value.messages`/`statuses`. 4xx faz a Meta reentregar em
loop. Havia 1419 testes verdes sobre isso — nenhum exercitava a camada HTTP.
Precedente no repo: commit `2f7e95f` corrigiu o mesmo bug na rota do uazapi.

### Débito herdado da branch, corrigido junto (autorizado pelo usuário)

- Larastan: nullsafe em `SendMessageTool.php:67` (commit `4318a35`) — corrigido com guard
  explícito, **não** trocando `?->` por `->` (que causaria fatal, pois `find()` é nullable)
- `AiRunTrackerJobTest`: literais não-UUID em coluna `uuid`
- `MediaTranscriptionTest`: não seguia o contrato `data.data` já padrão no projeto
- `PlatformPlanSeederTest`: desatualizado frente à decisão de produto do commit `55989d9`
- `composer.json`: `process-timeout: 0` — o `gate:all` era inexecutável (timeout 300s no Larastan)
- Gate do gateway era flaky ~40%: e2e-specs subiam `AppModule` real no run unitário e vazavam
  conexões BullMQ entre workers do Jest. Separados via `testPathIgnorePatterns`

### Riscos residuais conhecidos

1. **Nada exercitou a Graph API real.** Toda evidência é unitária ou e2e com mocks.
   Roteiro de 14 passos em `meta-window-webhook-verificacao.md` — executar antes de produção.
2. `UpdateConnectionStatusProcessor.onModuleInit()` ainda abre `Worker` BullMQ real
   independente de mock de `RedisService`. **Contido** no `test:e2e`, não eliminado.
3. `.real.e2e-spec.ts` excluído do gate (depende de serviço externo); rodar sob demanda.

## Notas

### Decisões de arquitetura

1. **Persistência da janela em `chat_tickets`** — equivalente direto de `conversations` no suportefy; já possui `instance_id` e `last_customer_message_at`. Alternativas descartadas: tabela `chat_meta_windows` por par (instância × contato) — semanticamente mais correta, mas adiciona join e tabela nova; `chat_tickets_extended` — a janela é lida a cada abertura de conversa, não é campo de baixa frequência.

2. **App Meta único da plataforma** — mantém `/v1/webhooks/meta` global com `META_VERIFY_TOKEN`/`META_APP_SECRET` de ambiente. Corrige apenas o plumbing. O modelo do suportefy (secret por canal na URL, N Apps Meta) exigiria recadastrar webhooks no painel Meta de cada tenant — fica como evolução futura.

3. **Janela como Service, não como WebhookHandler** — os handlers registrados em `ChatWebhookIngestor.php:95-101` fazem `return` após processar, curto-circuitando a persistência da mensagem. A renovação de janela precisa acontecer **depois** do insert, então vira `MetaWindowService` chamado pelo ingestor.

4. **`normalize()` preservado** — `MetaProvider.normalize()` continua existindo delegando ao primeiro evento de `normalizeAll()`, para não quebrar as specs existentes (`meta.provider.spec.ts`, `meta.adapter.spec.ts`).

### Princípios aplicados (skill `meta-whatsapp-expert`)

| Princípio | Onde é aplicado |
|---|---|
| 1 — Janela renova a cada inbound | TASK-3.2.3, TASK-3.2.4 |
| 2 — Nunca encurtar janela válida (`GREATEST`) | TASK-3.2.3 |
| 3 — Backend autoritativo + fallback de frontend | TASK-3.2.5 |
| 4 — Idempotência obrigatória | TASK-3.1.3, TASK-3.1.4 |
| 5 — Isolamento por tenant | TASK-3.2.4 (escrita sempre por `$ticket->id` resolvido) |
| 6 — Sistema ≠ mensagem do cliente | TASK-3.2.4 (`type !== 'system'`) |
| 7 — Não mascarar `failed` | TASK-3.1.5, TASK-3.2.4 |
| 8 — Produção viva (migration antes do código) | TASK-3.2.1 antes de TASK-3.2.3 |
| 9 — Fora da janela, só template aprovado | TASK-3.1.5 (erro 131047), TASK-4.1.2 |

### Referências

- Implementação de referência: `~/Documents/suportefy-supabase/supabase/functions/meta-whatsapp-webhook/index.ts`
- Skill: `~/Documents/prompts/SKILLS/meta-whatsapp-expert/SKILL.md` + `references/`
- Janela 24h/72h: `references/customer-service-window.md`
- Payloads: `references/webhook-payloads.md`
- Envio/templates/erros: `references/sending-and-templates.md`
- Upgrade Graph API: `references/graph-api-version.md`
