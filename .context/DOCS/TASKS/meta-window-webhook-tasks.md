# Tasks — Meta WhatsApp: Janela 24h/72h CTWA, roteamento por número e correção do envio

**Feature:** `.context/DOCS/FEATURES/meta-window-webhook.md`
**PRD:** — *(derivado de análise comparativa com `suportefy-supabase` + skill `meta-whatsapp-expert`)*
**Status:** [x] Em progresso | [ ] Concluída

---

## Gates por camada (`.context/WORKFLOW/validation-flow.md`)

```bash
# api (Laravel 12)
cd api && composer gate:all

# gateway (NestJS 11)
pnpm --filter gateway build && pnpm --filter gateway test

# app (Angular 20)
pnpm --filter app build && pnpm --filter app test
```

## Imports autorizados por camada (`.context/ARCHITECTURE/dependencies.yaml`)

- **gateway** → api via HTTP, Redis, Graph API — **proibido** PostgreSQL direto
- **api** → PostgreSQL, Redis — **proibido** Graph API / WhatsApp direto
- **app** → gateway (WS+REST), api (REST) — **proibido** PostgreSQL/Redis

---

## Resumo de todas as tasks

| Task | Fase | Camada | Descrição curta | Status |
|---|---|---|---|---|
| TASK-2.1.1 | 2 — Design | app (doc) | Especificar UI da janela Meta | ⏳ Pendente |
| TASK-3.1.1 | 3 — Backend | gateway | Ampliar contrato do payload Meta | ⏳ Pendente |
| TASK-3.1.2 | 3 — Backend | gateway | `normalizeAll` com lote + janela/CTWA | ⏳ Pendente |
| TASK-3.1.3 | 3 — Backend | gateway | Adapter sem dupla resolução + idempotência estável | ⏳ Pendente |
| TASK-3.1.4 | 3 — Backend | gateway | Controller: roteamento correto + ACK rápido | ⏳ Pendente |
| TASK-3.1.5 | 3 — Backend | gateway | Envio Meta real (texto livre + `failed` não mascarado) | ⏳ Pendente |
| TASK-3.1.6 | 3 — Backend | gateway | Graph API v18.0 → v25.0 | ⏳ Pendente |
| TASK-3.2.1 | 3 — Backend | api | Migration: campos de janela e CTWA | ⏳ Pendente |
| TASK-3.2.2 | 3 — Backend | api | Expor campos no model `ChatTicket` | ⏳ Pendente |
| TASK-3.2.3 | 3 — Backend | api | `MetaWindowService` com semântica da janela | ⏳ Pendente |
| TASK-3.2.4 | 3 — Backend | api | Ligar a janela ao `ChatWebhookIngestor` | ⏳ Pendente |
| TASK-3.2.5 | 3 — Backend | api | `VerifyContactWindowAction` com fallback | ⏳ Pendente |
| TASK-3.2.6 | 3 — Backend | api | Corrigir token de instância Meta no dispatcher | ⏳ Pendente |
| TASK-4.1.1 | 4 — Frontend | app | Modelo e serviço da janela | ⏳ Pendente |
| TASK-4.1.2 | 4 — Frontend | app | Badge de janela no composer | ⏳ Pendente |
| TASK-4.1.3 | 4 — Frontend | app | Re-verificar janela ao trocar de instância | ⏳ Pendente |
| TASK-5.1.1 | 5 — Integration | gateway | Fixtures e teste E2E do webhook Meta | ⏳ Pendente |

**Total:** 17 tasks · Fase 2: 1 · Fase 3: 12 (6 gateway + 6 api) · Fase 4: 3 · Fase 5: 1

---

## Fase 2 — Design

### Grupo 2.1 — Especificação visual

- [ ] **TASK-2.1.1** ⏳ Especificar UI da janela Meta
  **T — Tarefa:** Documentar o design do badge de janela Meta no composer (estados 24h/72h CTWA/expirada/não-Meta, countdown, cores light/dark) para desbloquear a Fase 4.
  **A — Arquivo:** `.context/DESIGN/meta-window-badge.md` (criar)
  **Referência:** `.context/DESIGN/trial-signup-checkout-chat-blocked.md` — mesmo formato de spec (Visão Geral, Fluxo, Wireframes, Especificação de Componentes, Acessibilidade)
  **Imports autorizados:** N/A (documento markdown) — proibido: nada
  **C — Comportamento:**
  ANTES: composer só alterna `free`/`mixed`/`template-only` (`chat.store.ts:75-90`), sem indicar tipo de janela nem tempo restante.
  DEPOIS: arquivo de design descreve os 4 estados do badge (`24h aberta`, `72h CTWA aberta`, `expirada/template-only`, `não-Meta` oculto), formato do tempo restante, cores por token do design system em light/dark, comportamento ao reabrir via realtime, e `aria-label` acessível.
  **E — Evidência:**
  - [ ] `test -f .context/DESIGN/meta-window-badge.md` → arquivo existe com todas as seções do `_TEMPLATE.md`
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

---

## Fase 3 — Backend

### Grupo 3.1 — Gateway (NestJS)

- [ ] **TASK-3.1.1** ⏳ Ampliar o contrato do payload Meta
  **T — Tarefa:** Declarar no contrato TypeScript os campos hoje descartados pelo normalizer: `messages[].referral`, `messages[].context`, `statuses[].errors[]`, `statuses[].conversation.expiration_timestamp`, `sticker`/`reaction`/`button`/`interactive`.
  **A — Arquivo:** `gateway/src/domains/chat/contracts/meta-provider.interface.ts` (modificar)
  **Referência:** `references/webhook-payloads.md` da skill `meta-whatsapp-expert` — estrutura oficial dos campos Meta
  **Imports autorizados:** apenas tipos TypeScript (interfaces/types) — proibido: lógica, chamadas HTTP, imports de outros domínios
  **C — Comportamento:**
  ANTES: `MetaWebhookPayload` (linhas 83-151) não declara `referral`, `context`, `errors[]`, nem `expiration_timestamp` (só `expiry`).
  DEPOIS: interface declara todos os campos acima como opcionais, com os tipos corretos (`expiration_timestamp: string`, `errors: Array<{code:number; title:string}>` etc.), conforme `references/webhook-payloads.md`.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway build` → 0 erros
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

- [ ] **TASK-3.1.2** ⏳ `MetaProvider.normalize` retorna lista de eventos e captura janela/CTWA
  **T — Tarefa:** Criar `normalizeAll(payload): NormalizedMetaEvent[]` que itera `entry → changes → messages|statuses` completos (hoje só o primeiro de cada), anexando a cada evento `window?: {expiresAt: Date; type: '24h'|'72h'}`, `referral?`, `quotedMessageId` (de `context.id`) e `errors?`. Manter `normalize()` delegando ao primeiro elemento de `normalizeAll()` por compatibilidade com specs existentes.
  **A — Arquivo:** `gateway/src/domains/chat/providers/meta/meta.provider.ts` (modificar)
  **Referência:** `gateway/src/domains/chat/providers/zapi/zapi.normalizer.ts` — padrão de normalizer que devolve lista de eventos
  **Imports autorizados:** `MetaWebhookPayload` (contrato da TASK-3.1.1), utilitários de data já usados no arquivo — proibido: chamadas HTTP, acesso a banco
  **C — Comportamento:**
  ANTES: `normalize()` lê `entry[0].changes[0]` e `messages[0]`/`statuses[0]` (`:67-70,171,195`); descarta `referral`, `context`, `errors`, `expiration_timestamp`.
  DEPOIS: `normalizeAll()` processa todos os `entry`/`changes`/`messages|statuses` de um payload em lote; cada `NormalizedMetaEvent` carrega os campos de janela/CTWA quando presentes no payload original.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- meta.provider.spec` → verde, incluindo caso de 2 entries × 2 messages
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.1.1

- [ ] **TASK-3.1.3** ⏳ `MetaAdapter.normalizeWebhook` sem dupla resolução e com idempotência estável
  **T — Tarefa:** Substituir a resolução de instância via `webhookToken` comparado a `phone_number_id` por resolução exclusiva por `phone_number_id` (ou `entry.id` para `message_template_status_update`), preencher `instanceWebhookToken` a partir da instância resolvida e trocar a `idempotencyKey` baseada em `Date.now()` por `meta:{instanceId}:{wamid|statusId}:{status?}`.
  **A — Arquivo:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts` (modificar)
  **Referência:** `gateway/src/domains/chat/providers/meta/meta.provider.ts` (TASK-3.1.2, `normalizeAll`) — fonte dos eventos a converter em `NormalizedWebhookEvent[]`
  **Imports autorizados:** `NormalizedMetaEvent` (TASK-3.1.2), `MetaLookupService.resolvePhoneNumberId`/`.resolveWabaId` (`gateway/src/domains/chat/http/meta-lookup.service.ts`) — proibido: `Date.now()` na chave de idempotência, PostgreSQL direto
  **C — Comportamento:**
  ANTES: valida `instance.webhookToken !== webhookToken` contra um `phone_number_id` (`:355`) — lança `Webhook token mismatch` sempre que os dois não coincidem; usa `Date.now()` na `idempotencyKey` (`:382`), o que gera chave nova a cada reentrega da Meta.
  DEPOIS: `normalizeWebhookBatch(rawPayload): NormalizedWebhookEvent[]`; instância resolvida **só** por `phone_number_id`; nenhuma segunda validação contraditória de token; `idempotencyKey` estável — mesma entrega produz sempre a mesma chave.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- meta.adapter.spec` → verde; duas chamadas com o mesmo payload produzem a mesma `idempotencyKey`
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.1.2

- [ ] **TASK-3.1.4** ⏳ Controller Meta: roteamento correto e ACK rápido
  **T — Tarefa:** Fazer o controller repassar o `resolvedOverride` real devolvido pelo adapter (em vez de recalcular resolução com `phone_number_id` tratado como `webhookToken`), calcular o HMAC sobre o **raw body** (não `JSON.stringify(req.body)`), e responder sempre `200 OK` quando `phone_number_id` for desconhecido (nunca 4xx, para não disparar reentrega em loop da Meta).
  **A — Arquivo:** `gateway/src/domains/chat/controllers/meta-webhook.controller.ts` (modificar)
  **Referência:** `gateway/src/domains/chat/controllers/chat-webhook.controller.ts` — padrão de ACK rápido + pre-check antes de delegar ao service
  **Imports autorizados:** `MetaAdapter.normalizeWebhookBatch` (TASK-3.1.3), `ChatWebhookService` — proibido: recalcular resolução de instância no controller, PostgreSQL direto
  **C — Comportamento:**
  ANTES: `handle('meta', phoneNumberId, event, null)` (`:121`) faz o service resolver por `webhook_token` usando um `phone_number_id`, produzindo `Webhook token mismatch`.
  DEPOIS: adapter resolve e devolve os eventos prontos; controller apenas repassa; HMAC validado sobre o corpo bruto da requisição; `phone_number_id` desconhecido → log estruturado + `200 OK`.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- meta-webhook` → verde; payload com `phone_number_id` desconhecido responde 200
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.1.3

- [ ] **TASK-3.1.5** ⏳ Envio Meta real: texto livre + `failed` não mascarado
  **T — Tarefa:** Implementar `MetaClient.sendText(phoneNumberId, accessToken, {to, body})` fazendo `POST {GRAPH}/{phone_number_id}/messages` com `messaging_product:'whatsapp'`; fazer `MetaAdapter.sendText` delegar ao client e devolver `messages[0].id`; mapear o erro `131047` da Graph API para mensagem "fora da janela — use template"; propagar status `failed` da Meta como `failed` (nunca mascarado como `sent`) com `errors[].code`/`title` em metadata.
  **A — Arquivo:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts`, `gateway/src/domains/chat/providers/meta/meta.client.ts` (modificar)
  **Referência:** `references/sending-and-templates.md` da skill `meta-whatsapp-expert` — mapeamento de erros e payload de envio
  **Imports autorizados:** `axios` (já usado no client), config de Graph API (`configuration.ts`) — proibido: PostgreSQL direto
  **C — Comportamento:**
  ANTES: `sendText` sempre retorna `success:false` (`meta.adapter.ts:43-56`); `MetaClient.sendText` não existe.
  DEPOIS: envio de texto livre dentro da janela funciona ponta a ponta; erro `131047` tratado com mensagem clara; status `failed` nunca mascarado.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- meta.client.spec` → verde, incluindo caso do erro `131047`
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.1.4

- [ ] **TASK-3.1.6** ⏳ Graph API v18.0 → v25.0
  **T — Tarefa:** Atualizar o default da versão da Graph API de `v18.0` para `v25.0` em todos os pontos onde está hardcoded.
  **A — Arquivo:** `gateway/src/core/config/configuration.ts:238`, `gateway/src/domains/chat/providers/meta/meta.client.ts:74,78`, `gateway/.env.example:68-69` (modificar)
  **Referência:** `references/graph-api-version.md` da skill `meta-whatsapp-expert` — versão alvo e política de deprecação
  **Imports autorizados:** N/A (constantes de configuração) — proibido: nada
  **C — Comportamento:**
  ANTES: default `https://graph.facebook.com/v18.0` em 3 lugares — v18 no fim da janela de deprecação da Meta.
  DEPOIS: `v25.0` em todos os 3 lugares.
  **E — Evidência:**
  - [ ] `grep -rn "graph.facebook.com/v" gateway/src` → só `v25.0`
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

### Grupo 3.2 — API (Laravel)

- [ ] **TASK-3.2.1** ⏳ Migration: campos de janela e CTWA
  **T — Tarefa:** Criar migration adicionando a `chat_tickets`: `meta_window_expires_at` (timestamptz, null), `meta_window_type` (string 4, null, check `24h|72h`), `meta_referral_source_id`, `meta_referral_source_type`, `meta_referral_headline`, `meta_referral_ctwa_clid` (string, null) e índice em `meta_window_expires_at`.
  **A — Arquivo:** `api/database/migrations/<timestamp>_add_meta_window_to_chat_tickets.php` (criar via `php artisan make:migration`)
  **Referência:** `api/database/migrations/2026_05_25_000002_add_trial_and_payment_method_to_platform_tenants.php` — padrão `Schema::table` + guards `hasColumn`
  **Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema` — proibido: model/service, Graph API
  **C — Comportamento:**
  ANTES: `chat_tickets` não tem campos de janela Meta.
  DEPOIS: colunas descritas presentes com guards `hasColumn`; `down()` reversível.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate --pretend` → SQL esperado
  - [ ] `cd api && composer gate:all` → verde
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

- [ ] **TASK-3.2.2** ⏳ Expor os campos no model
  **T — Tarefa:** Adicionar os 6 campos da migration ao `$fillable` do model e `meta_window_expires_at` ao `$casts` como `datetime`.
  **A — Arquivo:** `api/src/Domain/Chat/Models/ChatTicket.php` (modificar) — `$fillable` (linhas 58-99) + `$casts`
  **Referência:** `api/src/Domain/Chat/Models/ChatTicket.php` — mesmo arquivo, seguir padrão dos campos já existentes (`instance_id`, `last_customer_message_at`)
  **Imports autorizados:** Eloquent (já importado no arquivo) — proibido: HTTP, Graph API, gateway
  **C — Comportamento:**
  ANTES: model não expõe os campos de janela Meta em fillable/casts.
  DEPOIS: `meta_window_expires_at`, `meta_window_type`, `meta_referral_source_id`, `meta_referral_source_type`, `meta_referral_headline`, `meta_referral_ctwa_clid` em `$fillable`; `meta_window_expires_at` cast para `datetime`.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter=ChatTicket` → verde
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.2.1

- [ ] **TASK-3.2.3** ⏳ `MetaWindowService` com a semântica da janela
  **T — Tarefa:** Criar service com `renewFromInbound(ChatTicket $t, CarbonInterface $msgAt, ?array $referral): void` e `applyFromStatus(ChatTicket $t, int $expirationTs, string $originType): void`, aplicando: `GREATEST(atual, novo)` (nunca encurta); `referral` presente → 72h; mantém `'72h'` enquanto a expiração de 72h for maior que a nova de 24h; `save()` **só** quando `expires_at` ou `type` mudam; base = timestamp da própria mensagem, com fallback para `now()` quando inválido.
  **A — Arquivo:** `api/src/Domain/Chat/Services/MetaWindowService.php` (criar)
  **Referência:** `api/src/Domain/Chat/Services/ChatTicketQueryService.php` — padrão de service de domínio Chat
  **Imports autorizados:** `Domain\Chat\Models\ChatTicket`, `Carbon\CarbonInterface`, `Carbon\Carbon` — proibido: HTTP, Graph API, gateway, controllers
  **C — Comportamento:**
  ANTES: não existe serviço de janela — cálculo é feito ad-hoc e sem persistência.
  DEPOIS: service centraliza a semântica oficial da janela Meta (renovação, GREATEST, guard de escrita).
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter=MetaWindowService` → verde com os casos: janela fechada reabre · 72h não vira 24h · valor igual não persiste (`save()` não chamado) · timestamp inválido cai em `now()`
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.2.2

- [ ] **TASK-3.2.4** ⏳ Ligar a janela ao ingestor
  **T — Tarefa:** Injetar `MetaWindowService` no ingestor; no inbound de conteúdo (`type !== 'system'`) chamar `renewFromInbound` com `payload['message']['timestamp']` e `payload['message']['referral']`; no caminho de status chamar `applyFromStatus` quando `payload['status']['window']` estiver presente; persistir `failed` como `failed` com `errors` em `metadata`. Toda escrita usa `$ticket->id` já resolvido — nunca telefone/nome. Falha no bloco de janela loga e segue, nunca derruba a ingestão.
  **A — Arquivo:** `api/src/Domain/Chat/Actions/ChatWebhookIngestor.php` (modificar)
  **Referência:** `api/src/Domain/Chat/Services/MetaWindowService.php` (TASK-3.2.3) — service a ser chamado
  **Imports autorizados:** `Domain\Chat\Services\MetaWindowService`, classes já importadas no arquivo — proibido: Graph API direto, HTTP outbound
  **C — Comportamento:**
  ANTES: bloco `if ($direction === 'incoming')` (`:386-403`) não toca janela; `updateExistingMessageStatus` ignora `conversation.expiration_timestamp` e mapeia status sem tratar `failed`.
  DEPOIS: toda mensagem inbound de conteúdo renova a janela; status com `expiration_timestamp` aplica a janela vinda da Meta; `failed` nunca mascarado como `sent`; erro no bloco de janela é logado e não interrompe a persistência da mensagem.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter=ChatWebhookIngestor` → verde
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.2.3

- [ ] **TASK-3.2.5** ⏳ `VerifyContactWindowAction` com campo persistido + fallback
  **T — Tarefa:** Resolver o ticket ativo do contato; **Branch 1** usa `meta_window_expires_at` quando no futuro; **Branch 2** cai no cálculo por mensagens (comportamento atual) quando o campo estiver ausente ou no passado — defesa em profundidade. Estender o DTO com `expiresAt` e `windowType`.
  **A — Arquivo:** `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php`, `api/src/Domain/Chat/DTOs/ContactWindowStatusDTO.php` (modificar)
  **Referência:** `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php` — mesmo arquivo, manter query com `where('tenant_id', $tenantId)`
  **Imports autorizados:** `Domain\Chat\Models\ChatTicket`, `Domain\Chat\DTOs\ContactWindowStatusDTO`, Carbon — proibido: Graph API, HTTP outbound, gateway
  **C — Comportamento:**
  ANTES: só calcula 24h a partir da última `ChatMessage` com `is_from_contact=true`; DTO devolve `canSendFreeText` + `lastMessageAt`.
  DEPOIS: resolve o campo persistido primeiro; cai no cálculo por mensagens quando ausente/no passado; DTO ganha `expiresAt` e `windowType`; isolamento por tenant mantido.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter=VerifyContactWindow` → verde com o caso "campo no passado mas inbound de 1h atrás → `canSendFreeText: true`"
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.2.3

- [ ] **TASK-3.2.6** ⏳ Corrigir o token de instância Meta no dispatcher
  **T — Tarefa:** Para `provider === 'meta'`, montar `settings_json['phone_number_id'].':'.settings_json['access_token']` (campos já aceitos em `ChatInstanceRequest.php:90-91`), espelhando o ramo `zapi` existente; retornar `null` quando faltar qualquer um dos dois campos.
  **A — Arquivo:** `api/src/Domain/Chat/Services/ChatMessageGatewayDispatcher.php` (modificar, `resolveInstanceToken` linhas ~398-424)
  **Referência:** ramo `zapi` no mesmo método `resolveInstanceToken` — padrão de composição de token por provider
  **Imports autorizados:** classes já importadas no arquivo — proibido: Graph API direto, gateway
  **C — Comportamento:**
  ANTES: para Meta devolve `$instance->webhook_token`; o adapter espera `phoneNumberId:accessToken` → `sendTemplate` falha com "Invalid instance token format".
  DEPOIS: token formatado como `phoneNumberId:accessToken`; `null` quando incompleto.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter=ChatMessageGatewayDispatcher` → verde
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

---

## Fase 4 — Frontend

### Grupo 4.1 — Modelo, badge e verificação

- [ ] **TASK-4.1.1** ⏳ Modelo e serviço da janela
  **T — Tarefa:** Estender `WindowStatus` com `expiresAt: Date | null` e `windowType: '24h' | '72h' | null`, mapeando os novos campos da resposta do endpoint (TASK-3.2.5) no service. Manter o fallback de erro do service forçando modo template.
  **A — Arquivo:** `app/src/app/core/models/window-status.model.ts`, `app/src/app/pages/chat/components/new-conversation-modal/services/window-verification.service.ts` (modificar)
  **Referência:** `app/src/app/core/models/platform-user.model.ts` — padrão de interface de modelo TS do projeto
  **Imports autorizados:** apenas tipos TS e o que já está importado no service (`HttpClient`, `rxjs`) — proibido: `any`, PostgreSQL/Redis direto
  **C — Comportamento:**
  ANTES: `WindowStatus` tem só `canSendFreeText` e `lastMessageAt`.
  DEPOIS: `+ expiresAt: Date | null` e `windowType: '24h' | '72h' | null`; erro de rede no service continua produzindo `canSendFreeText: false` (modo template forçado).
  **E — Evidência:**
  - [ ] `pnpm --filter app build` → 0 erros
  **Status:** ⏳ Pendente
  **Dependências:** TASK-2.1.1, TASK-3.2.5

- [ ] **TASK-4.1.2** ⏳ Badge de janela no composer
  **T — Tarefa:** Criar computed `windowBadge` (tipo `24h`/`72h CTWA`, tempo restante) em `ChatStore` e renderizar o badge acima do composer em `chat.html`, visível apenas quando o provider da instância selecionada é `meta`, seguindo a spec de `.context/DESIGN/meta-window-badge.md`.
  **A — Arquivo:** `app/src/app/pages/chat/chat.store.ts`, `app/src/app/pages/chat/chat.html` (modificar)
  **Referência:** `.context/DESIGN/meta-window-badge.md` (TASK-2.1.1) — cores, estados e formato do countdown; `app/src/app/pages/chat/chat.store.ts:75-90` (`composerMode`) — mesmo padrão de computed baseado em `windowStatus()` + `instanceProviders()`
  **Imports autorizados:** `WindowStatus` (TASK-4.1.1), signals/computed do Angular — proibido: `any` (anti-pattern do AGENTS.md), HttpClient direto no store
  **C — Comportamento:**
  ANTES: `composerMode` (`chat.store.ts:75-88`) só decide `free|mixed|template-only`; nada no template indica tipo de janela nem tempo restante.
  DEPOIS: computed `windowBadge` expõe tipo e tempo restante formatado; badge renderizado acima do composer quando `provider === 'meta'`; oculto para outros providers.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- chat.store` → verde
  **Status:** ⏳ Pendente
  **Dependências:** TASK-4.1.1

- [ ] **TASK-4.1.3** ⏳ Re-verificar janela ao trocar de instância
  **T — Tarefa:** Adicionar `effect` reagindo a `selectedInstanceId` + `selectedContactId` no modal de nova conversa, invalidando o cache de 30s do `WindowVerificationService` antes de reconsultar o status da janela.
  **A — Arquivo:** `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts` (modificar)
  **Referência:** `app/src/app/pages/chat/components/new-conversation-modal/services/window-verification.service.ts` — método `invalidateCache` já existente, reutilizar (não recriar)
  **Imports autorizados:** `WindowVerificationService` (já injetado), `effect` do Angular — proibido: `any`, PostgreSQL/Redis direto
  **C — Comportamento:**
  ANTES: `checkWindowStatus` só roda em `selectContact` (`:237-244`) — trocar a instância Meta depois de escolher o contato não re-verifica a janela.
  DEPOIS: `effect` observa `selectedInstanceId` + `selectedContactId`; a cada mudança, invalida o cache de 30s e reconsulta.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- new-conversation-modal` → verde
  **Status:** ⏳ Pendente
  **Dependências:** nenhuma

---

## Fase 5 — Integration

### Grupo 5.1 — Validação ponta a ponta

- [ ] **TASK-5.1.1** ⏳ Fixtures e teste ponta a ponta do webhook Meta
  **T — Tarefa:** Criar fixtures de payload real da Meta para: lote multi-entry, inbound com `referral` (CTWA), status com `expiration_timestamp` + `origin.type='referral_conversion'`, status `failed` com `errors[]`, e reentrega duplicada. Adicionar asserts de contagem de eventos e de estabilidade da chave de idempotência.
  **A — Arquivo:** `gateway/src/domains/chat/providers/meta/__fixtures__/` (criar), `gateway/src/domains/chat/providers/meta/meta.adapter.spec.ts` (modificar)
  **Referência:** `references/webhook-payloads.md` da skill `meta-whatsapp-expert` — formato oficial dos payloads a reproduzir nas fixtures
  **Imports autorizados:** fixtures JSON locais, utilitários de teste já usados no spec — proibido: chamadas HTTP reais à Graph API
  **C — Comportamento:**
  ANTES: sem fixtures de payload real da Meta; specs cobrem apenas caminhos felizes de evento único.
  DEPOIS: fixtures cobrem os 5 cenários acima; testes comprovam contagem correta de eventos por lote e chave de idempotência estável entre reentregas.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- meta` → verde
  - [ ] `pnpm --filter gateway build` → 0 erros
  **Status:** ⏳ Pendente
  **Dependências:** TASK-3.1.2, TASK-3.1.3

---

## Ordem de execução sugerida

1. TASK-2.1.1 (Design) → desbloqueia TASK-4.1.1
2. Grupo 3.1 em sequência: 3.1.1 → 3.1.2 → 3.1.3 → 3.1.4 → 3.1.5 (3.1.6 é independente, pode rodar em paralelo)
3. Grupo 3.2 em sequência: 3.2.1 → 3.2.2 → 3.2.3 → (3.2.4 e 3.2.5 em paralelo, ambas dependem de 3.2.3) — 3.2.6 é independente
4. TASK-5.1.1 após 3.1.2 e 3.1.3 (fixtures precisam do normalizer e do adapter finais)
5. Fase 4 após TASK-2.1.1 e TASK-3.2.5 (4.1.1 precisa do endpoint estendido); 4.1.2 após 4.1.1; 4.1.3 independente das demais de Fase 4
6. Fase 5 (gates finais + `/prevec-phase-close`) por último, com review de 7 subagents na última fase
