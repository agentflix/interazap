# Tasks — Meta WhatsApp: hardening de webhook, janela e envio

**Feature:** `.context/DOCS/FEATURES/meta-window-webhook.md`
**Status:** [ ] Em progresso | [ ] Concluída
**Total:** 14 tasks

## Gates por camada

```bash
cd api && composer gate:fast
pnpm --filter gateway test && pnpm --filter gateway test:e2e && pnpm --filter gateway build
pnpm --filter app test:run && pnpm --filter app build
# Última fase:
cd api && composer gate:all
```

## Imports e fronteiras

- **gateway:** pode usar API interna, Redis, BullMQ e Meta Graph API; proibido PostgreSQL.
- **api:** pode usar PostgreSQL e Redis; proibido chamar Meta diretamente.
- **app:** pode usar API REST e Gateway WS; proibido Redis, PostgreSQL, Meta direta e `any`.

## Resumo

| Task | Fase | Camada | Descrição | Status |
|---|---|---|---|---|
| TASK-2.1.1 | 2 | app/tooling | Corrigir gate Vitest Angular | ✅ |
| TASK-3.1.1 | 3 | gateway | Preservar provider e idempotência Meta | ✅ |
| TASK-3.1.2 | 3 | gateway | Separar credencial e validar resposta Graph | ✅ |
| TASK-3.1.3 | 3 | gateway | Configuração Meta fail-closed | ✅ |
| TASK-3.1.4 | 3 | gateway | ACK durável e processamento assíncrono | ✅ |
| TASK-3.2.1 | 3 | api/db | Constraint de idempotência e unicidade Meta | ✅ |
| TASK-3.2.2 | 3 | api | Ingestão/status atômicos e escopados | ✅ |
| TASK-3.2.3 | 3 | api | Janela atômica com GREATEST | ✅ |
| TASK-3.2.4 | 3 | api | Verificação por ticket/instância | ✅ |
| TASK-3.2.5 | 3 | api | Guard para agente, BOT e IA | ✅ |
| TASK-4.1.1 | 4 | app | Composer temporal e fail-closed | ✅ |
| TASK-4.1.2 | 4 | app | Cancelar race entre tickets | ✅ |
| TASK-4.1.3 | 4 | app | Cancelar race entre instâncias | ✅ |
| TASK-5.1.1 | 5 | integration | Regressão cross-layer e gates | ✅ |

---

## Fase 2 — Tooling

- [x] **TASK-2.1.1** ✅
  **T — Tarefa:** Corrigir os scripts Vitest do App para o builder `@angular/build:unit-test` executar uma única vez e propagar falhas.
  **A — Arquivo:** `app/package.json` (modificar), `.context/WORKFLOW/validation-flow.md` (modificar somente se o comando canônico mudar)
  **Referência:** `app/angular.json` — runner `vitest` já configurado no target `test`.
  **Imports autorizados:** N/A — proibido trocar runner ou adicionar dependência sem necessidade.
  **C — Comportamento:**
  ANTES: `test:run` chama `ng test --watch=false` e o Angular rejeita `--watch`.
  DEPOIS: `pnpm --filter app test:run` executa os specs uma vez, termina e retorna exit code não zero quando houver falha.
  **E — Evidência:**
  - [ ] `pnpm --filter app test:run` → suíte executada uma vez, sem “Unknown argument: watch”.
  **Status:** ✅ Concluída

---

## Fase 3 — Backend

### Grupo 3.1 — Gateway

- [x] **TASK-3.1.1** ✅
  **T — Tarefa:** Generalizar o mapper de evento normalizado para preservar `provider`, `idempotencyKey` e semântica Meta até o Redis Stream.
  **A — Arquivo:** `gateway/src/domains/chat/services/chat-webhook-event-normalizer.service.ts`, `gateway/src/domains/chat/services/chat-webhook.service.ts`, respectivos `*.spec.ts` (modificar)
  **Referência:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts:452-504` — contrato normalizado e chave determinística.
  **Imports autorizados:** contratos locais de Chat e utilitário de idempotência — proibido PostgreSQL e branches hardcoded por provider.
  **C — Comportamento:**
  ANTES: `handleNormalizedEvents` chama `mapZapiNormalizedToStream`, que fixa `provider: zapi` e descarta a chave Meta.
  DEPOIS: mapper neutro mantém `provider: meta`; o serviço usa a chave fornecida pelo adapter, com fallback determinístico somente quando ausente.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test --runInBand chat-webhook.service.spec.ts chat-webhook-event-normalizer.service.spec.ts meta.adapter.spec.ts` → provider e chave Meta preservados.
  **Status:** ✅ Concluída

- [x] **TASK-3.1.2** ✅
  **T — Tarefa:** Separar `phoneNumberId`/access token antes de listar ou enviar templates, retirar segredo de cache/logs e rejeitar sucesso Graph sem WAMID.
  **A — Arquivo:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts`, `meta.client.ts`, `meta.adapter.spec.ts`, `meta.client.spec.ts` (modificar)
  **Referência:** `meta.adapter.ts:204-215,521-535` — parser existente do token composto.
  **Imports autorizados:** `MetaClient`, DTOs Meta e hash criptográfico se cache não puder usar instanceId — proibido logar token ou usá-lo literalmente em chave Redis.
  **C — Comportamento:**
  ANTES: listagem recebe o token composto inteiro; cache/log expõem credencial; resposta 200 sem `messages[0].id` é sucesso.
  DEPOIS: Graph recebe só access token; cache/log usam identificador não secreto; envio sem WAMID retorna erro de contrato.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test --runInBand meta.adapter.spec.ts meta.client.spec.ts` → token não aparece em chamadas/cache/log e resposta sem ID falha.
  **Status:** ✅ Concluída

- [x] **TASK-3.1.3** ✅
  **T — Tarefa:** Validar `META_APP_SECRET` e `META_VERIFY_TOKEN` como configuração obrigatória quando o webhook Meta estiver habilitado.
  **A — Arquivo:** `gateway/src/core/config/configuration.ts`, `gateway/src/domains/chat/controllers/meta-webhook.controller.ts`, specs correspondentes (modificar)
  **Referência:** `gateway/src/domains/ai/providers/openai/openai.config.spec.ts` — padrão de validação fail-closed no startup.
  **Imports autorizados:** `ConfigService` e validação de configuração já adotada — proibido default secreto vazio.
  **C — Comportamento:**
  ANTES: HMAC e handshake operam com string vazia configurável.
  DEPOIS: configuração ausente impede habilitação/startup do fluxo Meta; controller nunca valida assinatura com chave vazia.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test --runInBand meta-webhook.controller.spec.ts` → casos secret/token ausentes rejeitados.
  **Status:** ✅ Concluída

- [x] **TASK-3.1.4** ✅
  **T — Tarefa:** Persistir o payload Meta validado numa fila BullMQ resiliente antes do ACK e mover lookup/normalização/publicação para processor assíncrono.
  **A — Arquivo:** `gateway/src/domains/chat/controllers/meta-webhook.controller.ts`, `gateway/src/domains/chat/processors/meta-webhook.processor.ts` (criar), `gateway/src/domains/chat/services/meta-webhook-queue.service.ts` (criar), `gateway/src/domains/chat/chat.module.ts`, testes unitários/E2E correspondentes (modificar)
  **Referência:** `gateway/src/domains/billing/services/billing-webhook.service.ts:201-259` e `gateway/src/shared/services/queue/bullmq-queue-factory.service.ts` — fila durável, retry e DLQ.
  **Imports autorizados:** BullMQ apenas no Gateway, queue factory/resilience, MetaAdapter e ChatWebhookService — proibido fire-and-forget em memória e PostgreSQL.
  **C — Comportamento:**
  ANTES: resposta aguarda lookup HTTP e processamento serial completo.
  DEPOIS: assinatura válida + enqueue durável bem-sucedido retornam 200; processor trata itens independentemente com retry/DLQ; falha de enqueue não produz falso ACK.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test --runInBand meta-webhook` → controller não chama lookup/processamento inline.
  - [ ] `pnpm --filter gateway test:e2e -- meta-webhook` → ACK ocorre após enqueue e job processa lote.
  **Status:** ✅ Concluída
  **Dependências:** TASK-3.1.1, TASK-3.1.3

### Grupo 3.2 — API

- [x] **TASK-3.2.1** ✅
  **T — Tarefa:** Criar migration para impor identidade única de mensagem Meta por tenant + instância + external ID e unicidade segura de `phone_number_id`/WABA ativos.
  **A — Arquivo:** `api/database/migrations/<timestamp>_harden_meta_webhook_identity.php` (criar via Artisan)
  **Referência:** `api/database/migrations/2026_03_20_000006_create_shared_webhook_events.php:40-55` — constraints idempotentes; `2026_05_24_000010_partition_chat_messages_table.php` — particionamento vigente.
  **Imports autorizados:** Migration, Schema, Blueprint e DB — proibido editar migration existente ou executar UPDATE de produção.
  **C — Comportamento:**
  ANTES: `external_id` possui índice não único; lookups Meta confiam em JSON sem ambiguidade protegida.
  DEPOIS: banco impede duplicata no escopo correto e rejeita configuração Meta ambígua, respeitando limitações da tabela particionada; migration reversível e sem backfill destrutivo.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate --pretend` → DDL esperado sem DML destrutivo.
  - [ ] `cd api && composer gate:fast` → verde.
  **Status:** ✅ Concluída

- [x] **TASK-3.2.2** ✅
  **T — Tarefa:** Tornar inserção e atualização de status idempotentes atomicamente no escopo tenant + instância + external ID.
  **A — Arquivo:** `api/src/Domain/Chat/Actions/ChatWebhookIngestor.php`, `api/tests/Feature/Chat/ChatWebhookIngestorMetaWindowTest.php` (modificar)
  **Referência:** `api/src/Domain/Shared/Models/SharedWebhookEvent.php` — persistência protegida por idempotency key única.
  **Imports autorizados:** modelos/serviços do Domain Chat, Query Builder e exceção de unique violation — proibido Graph API e consulta sem tenant/instance.
  **C — Comportamento:**
  ANTES: `exists()` seguido de insert permite corrida; status localiza somente por tenant + external ID.
  DEPOIS: operação atômica tolera retry concorrente, escopa status por instância e mantém mesmo WAMID independente em duas instâncias.
  **E — Evidência:**
  - [ ] `cd api && vendor/bin/pest tests/Feature/Chat/ChatWebhookIngestorMetaWindowTest.php` → duplicata concorrente e colisão cross-instance cobertas.
  **Status:** ✅ Concluída
  **Dependências:** TASK-3.2.1

- [x] **TASK-3.2.3** ✅
  **T — Tarefa:** Aplicar expiração/tipo da janela com UPDATE SQL atômico, `GREATEST` e guard de mudança.
  **A — Arquivo:** `api/src/Domain/Chat/Services/MetaWindowService.php`, `api/tests/Unit/Chat/Services/MetaWindowServiceTest.php` (modificar)
  **Referência:** `api/src/Domain/Chat/Services/MetaWindowService.php:121-172` — regras atuais a preservar.
  **Imports autorizados:** ChatTicket, Carbon e Query Builder/DB — proibido Graph API e update sem `tenant_id + id`.
  **C — Comportamento:**
  ANTES: read/compare/save permite lost update e o teste de reabertura depende da data real.
  DEPOIS: banco escolhe atomicamente a maior expiração, preserva 72h correspondente e não escreve valor idêntico; teste congela o relógio.
  **E — Evidência:**
  - [ ] `cd api && vendor/bin/pest tests/Unit/Chat/Services/MetaWindowServiceTest.php` → inclui interleaving 24h/72h e passa em qualquer data.
  **Status:** ✅ Concluída

- [x] **TASK-3.2.4** ✅
  **T — Tarefa:** Alterar a verificação de janela para receber ticket/instância Meta, consultar somente mensagens desse contexto e excluir `type=system`.
  **A — Arquivo:** `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php`, `api/src/Domain/Chat/Http/Controllers/ChatWindowController.php`, `api/src/Domain/Chat/DTOs/ContactWindowStatusDTO.php`, testes correspondentes (modificar)
  **Referência:** `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php` — branches persistida/fallback existentes.
  **Imports autorizados:** models/DTOs do Domain Chat e Carbon — proibido Graph API, telefone/nome como chave e query sem tenant.
  **C — Comportamento:**
  ANTES: ticket e mensagem são escolhidos separadamente por tenant + contato; system pode contar.
  DEPOIS: janela pertence ao ticket/instance solicitado; outro canal não autoriza Meta; system nunca abre fallback; contexto ausente falha fechado.
  **E — Evidência:**
  - [ ] `cd api && vendor/bin/pest tests/Unit/Chat/VerifyContactWindowActionTest.php` → multi-canal, system e contexto ausente cobertos.
  **Status:** ✅ Concluída

- [x] **TASK-3.2.5** ✅
  **T — Tarefa:** Aplicar o guard de janela a todo texto livre outbound Meta, incluindo agente, BOT e IA, mantendo template aprovado como única exceção.
  **A — Arquivo:** `api/src/Domain/Chat/Actions/SendChatMessageAction.php`, `api/src/Domain/Chat/Services/ChatAutoReplyResponder.php`, testes unitários/feature correspondentes (modificar)
  **Referência:** `SendChatMessageAction.php:294-324` — guard humano existente.
  **Imports autorizados:** VerifyContactWindowAction, enums/models Chat já usados — proibido chamada Meta direta e bypass por source.
  **C — Comportamento:**
  ANTES: somente `SOURCE_AGENT` é bloqueada; ticket/contact ausente libera envio.
  DEPOIS: agente, BOT e IA usam a mesma decisão por ticket/instância; fora da janela ou contexto desconhecido, texto não é despachado e template aprovado continua permitido.
  **E — Evidência:**
  - [ ] `cd api && vendor/bin/pest --filter='Meta.*Window|SendChatMessage|ChatAutoReplyResponder'` → agente/BOT/IA e fail-closed cobertos.
  **Status:** ✅ Concluída
  **Dependências:** TASK-3.2.4

---

## Fase 4 — Frontend

- [x] **TASK-4.1.1** ✅
  **T — Tarefa:** Tornar o composer reativo ao instante de expiração e fail-closed enquanto provider/janela Meta estiverem carregando ou falharem.
  **A — Arquivo:** `app/src/app/pages/chat/chat.store.ts`, `app/src/app/pages/chat/chat.store.spec.ts`, componente do composer se necessário (modificar)
  **Referência:** `app/src/app/pages/chat/components/meta-window-badge/meta-window-badge.ts:64-113` e `.context/DESIGN/meta-window-badge.md` (§ “Formato do tempo restante” e § “Comportamento ao reabrir a janela”).
  **Imports autorizados:** signals/computed/effect Angular e WindowStatus central — proibido `any` e HttpClient no store.
  **C — Comportamento:**
  ANTES: badge expira, mas `composerMode` permanece `mixed`; provider desconhecido retorna `free`.
  DEPOIS: relógio compartilhado fecha composer no deadline; estado Meta loading/erro fica `template-only` ou indisponível, nunca livre.
  **E — Evidência:**
  - [ ] `pnpm --filter app test:run --include='**/chat.store.spec.ts'` → transição temporal e loading/erro cobertos.
  **Status:** ✅ Concluída
  **Dependências:** TASK-2.1.1

- [x] **TASK-4.1.2** ✅
  **T — Tarefa:** Cancelar ou ignorar a resposta de window-status do ticket anterior ao trocar seleção.
  **A — Arquivo:** `app/src/app/pages/chat/chat.ts`, `app/src/app/pages/chat/chat.spec.ts` (modificar)
  **Referência:** `app/src/app/pages/chat/chat.ts:311-328` — effect atual; usar padrão RxJS `switchMap`/cleanup já existente no componente.
  **Imports autorizados:** RxJS e APIs Angular já usadas — proibido subscriptions órfãs e `any`.
  **C — Comportamento:**
  ANTES: resposta tardia A pode sobrescrever a janela do ticket B.
  DEPOIS: mudança A→B cancela/descarta A; somente ticket/contact/instance atuais atualizam o store.
  **E — Evidência:**
  - [ ] `pnpm --filter app test:run --include='**/chat.spec.ts'` → resposta A tardia não altera B.
  **Status:** ✅ Concluída
  **Dependências:** TASK-4.1.1

- [x] **TASK-4.1.3** ✅
  **T — Tarefa:** Cancelar ou ignorar resposta obsoleta ao trocar instância no modal de nova conversa.
  **A — Arquivo:** `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts`, `new-conversation-modal.spec.ts` (modificar)
  **Referência:** `new-conversation-modal.ts:193-216,282-297` — reconsulta atual.
  **Imports autorizados:** RxJS/signals Angular e WindowVerificationService — proibido requests concorrentes gravando estado sem validar IDs.
  **C — Comportamento:**
  ANTES: resposta da instância antiga pode sobrescrever `sendMode`.
  DEPOIS: apenas a combinação contact/instance corrente atualiza janela e modo.
  **E — Evidência:**
  - [ ] `pnpm --filter app test:run --include='**/new-conversation-modal.spec.ts'` → ordem invertida de respostas coberta.
  **Status:** ✅ Concluída
  **Dependências:** TASK-2.1.1

---

## Fase 5 — Integration

- [x] **TASK-5.1.1** ✅
  **T — Tarefa:** Consolidar regressões Meta ponta a ponta, atualizar tracking e executar gates finais.
  **A — Arquivo:** `gateway/test/meta-webhook.e2e-spec.ts`, specs Meta afetados, testes API/App afetados, `.context/DOCS/FEATURES/meta-window-webhook.md`, este arquivo de tasks (modificar)
  **Referência:** `.context/DOCS/FEATURES/meta-window-webhook-verificacao.md` — roteiro manual existente.
  **Imports autorizados:** fixtures/mocks locais — proibido Graph API real no gate automatizado e alteração de dados de produção.
  **C — Comportamento:**
  ANTES: E2E não afirma provider final/ACK durável; tracking contradiz implementação; gates direcionados têm lacunas.
  DEPOIS: regressão cobre HMAC→enqueue→processor→stream `provider=meta`, WAMID por instância, janela e fail-closed; feature/tasks refletem o estado real.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test && pnpm --filter gateway test:e2e && pnpm --filter gateway build` → verde.
  - [ ] `pnpm --filter app test:run && pnpm --filter app build` → verde.
  - [ ] `cd api && composer gate:all` → verde.
  - [ ] verificações de fronteira de `.context/WORKFLOW/validation-flow.md` → sem violação.
  **Status:** ✅ Concluída
  **Dependências:** todas as tasks anteriores

## Ordem de execução

1. TASK-2.1.1.
2. Gateway: 3.1.1 → 3.1.2 e 3.1.3 → 3.1.4.
3. API: 3.2.1 → 3.2.2; 3.2.3 e 3.2.4 podem seguir separadamente; 3.2.5 após 3.2.4.
4. App: 4.1.1 → 4.1.2 e 4.1.3.
5. TASK-5.1.1 e `/prevec-phase-close meta-window-webhook 5`.
