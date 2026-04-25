# Tasks: Meta WhatsApp Business API Integration

> Decomposição T.A.C.E das tasks da feature

---

## Feature: Meta WhatsApp Business API Integration
**ID:** FEAT-039
**Bounded Context:** Chat + Gateway
**Total Tasks:** 17
**Concluídas:** 16
**Status:** ✅ COMPLETA (exceto TASK-039.17 E2E - pré-existente)

---

## 🔄 FASE 3: BACKEND (Laravel)

### Tasks

#### TASK-039.1 ⏳: Atualizar ProviderType enum e ChatInstanceRequest

**T — Tarefa:** Adicionar provider 'meta' no enum ProviderType e validar settings do Meta (phone_number_id, access_token) no ChatInstanceRequest.

**A — Arquivo:**
- `api/src/Domain/Chat/Enums/ProviderType.php`
- `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php`

**C — Comportamento:**
```
ANTES:
- ProviderType tem apenas 'uazapi' e 'zapi'
- ChatInstanceRequest não valida campos do Meta

DEPOIS:
- ProviderType tem case META = 'meta'
- ChatInstanceRequest valida settings.phone_number_id e settings.access_token
```

**E — Evidência:**
- [ ] ProviderType::META existe e é 'meta'
- [ ] ChatInstanceRequest tem regras para phone_number_id e access_token
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

#### TASK-039.2 ⏳: Criar LookupInstanceByPhoneNumberAction + InstanceLookupDTO + GatewaySecretGuard

**T — Tarefa:** Criar action para resolver phone_number_id → ChatInstance, DTO de resposta e middleware de autorização para o Gateway.

**A — Arquivo:**
- `api/src/Domain/Chat/Actions/LookupInstanceByPhoneNumberAction.php`
- `api/src/Domain/Chat/DTOs/InstanceLookupDTO.php`
- `api/src/Domain/Chat/Http/Middleware/GatewaySecretGuard.php`

**C — Comportamento:**
```
ANTES:
- Gateway não tem como resolver phone_number_id via HTTP

DEPOIS:
- LookupInstanceByPhoneNumberAction busca por settings->phone_number_id
- InstanceLookupDTO retorna { instanceId, tenantId, webhookToken }
- GatewaySecretGuard protege rotas via GATEWAY_SECRET
```

**E — Evidência:**
- [ ] Action faz query por phone_number_id em settings_json
- [ ] DTO é readonly e tem factory method from()
- [ ] Middleware valida Authorization Bearer com GATEWAY_SECRET
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

#### TASK-039.3 ⏳: Criar VerifyContactWindowAction + ContactWindowStatusDTO

**T — Tarefa:** Criar action para verificar se contato está dentro da janela 24h de mensagens e DTO com resultado.

**A — Arquivo:**
- `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php`
- `api/src/Domain/Chat/DTOs/ContactWindowStatusDTO.php`

**C — Comportamento:**
```
ANTES:
- Sistema não verifica janela 24h para envios

DEPOIS:
- VerifyContactWindowAction busca última mensagem is_from_contact=true do cliente
- Retorna status: { canSendFreeText: boolean, lastMessageAt: Carbon|null }
- Janela: DENTRO se created_at > now() - 24h (23h59m = true, 24h00m = false)
```

**E — Evidência:**
- [ ] Action aceita tenantId + contactId
- [ ] DTO é readonly com canSendFreeText + lastMessageAt
- [ ] Testes para bordas: 23h59m=true, 24h00m=false, sem mensagem=false
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

#### TASK-039.4 ⏳: Criar ChatWindowController + rotas

**T — Tarefa:** Criar controller com endpoints window-status e lookup-by-phone-number, e registrar rotas em api.php.

**A — Arquivo:**
- `api/src/Domain/Chat/Http/Controllers/ChatWindowController.php`
- `api/src/Domain/Chat/Routes/api.php`

**C — Comportamento:**
```
ANTES:
- Não existem endpoints para window-status

DEPOIS:
- GET /api/chat/contacts/{id}/window-status → VerifyContactWindowAction
- GET /api/chat/instances/by-phone-number/{phoneNumberId} → LookupInstanceByPhoneNumberAction (GatewaySecretGuard)
- Rotas registradas no router
```

**E — Evidência:**
- [ ] Rota window-status acessível via Sanctum (usuário autenticado)
- [ ] Rota lookup-by-phone-number acessível via GATEWAY_SECRET
- [ ] Controller usa método success() do base controller
- [ ] Teste de rota passa

**Status:** ✅ Concluída

---

#### TASK-039.5 ⏳: Criar migration para índice phone_number_id

**T — Tarefa:** Criar migration para adicionar índice parcial em chat_instances para busca por phone_number_id (só rows com provider='meta').

**A — Arquivo:**
- `api/database/migrations/YYYY_MM_DD_HHMMSS_add_phone_number_id_index_to_chat_instances.php`

**C — Comportamento:**
```
ANTES:
- Tabela chat_instances não tem índice para phone_number_id

DEPOIS:
- Índice parcial WHERE provider = 'meta' usando expressão JSONB
- Query: settings_json->>'phone_number_id' = 'xxx' AND provider = 'meta'
```

**E — Evidência:**
- [ ] Migration executa sem erro
- [ ] down() remove índice corretamente
- [ ] Query plan usa índice (EXPLAIN ANALYZE)

**Status:** ✅ Concluída

---

#### TASK-039.6 ⏳: Criar VerifyContactWindowActionTest

**T — Tarefa:** Criar testes unitários para VerifyContactWindowAction com casos de borda da janela 24h.

**A — Arquivo:**
- `api/tests/Unit/Chat/VerifyContactWindowActionTest.php`

**C — Comportamento:**
```
ANTES:
- Sem testes para VerifyContactWindowAction

DEPOIS:
- Teste: contato com mensagem há 23h59m → canSendFreeText=true
- Teste: contato com mensagem há 24h00m → canSendFreeText=false
- Teste: contato com mensagem há 24h01m → canSendFreeText=false
- Teste: contato sem mensagens → canSendFreeText=false
- Teste: contato de tenant diferente → não encontra
```

**E — Evidência:**
- [ ] Todos os testes passam
- [ ] Coverage cobre lógica de janela
- [ ] Teste usa banco de dados real (não mock)

**Status:** ✅ Concluída

---

## 🔄 FASE 3.5: GATEWAY (NestJS)

### Tasks

#### TASK-039.7 ⏳: Criar interface MetaWhatsAppProvider + MetaProvider

**T — Tarefa:** Criar interface dedicada para Meta e provider (client, adapter) conforme architecture do PLAN-039.

**A — Arquivo:**
- `gateway/src/domains/chat/contracts/meta-provider.interface.ts`
- `gateway/src/domains/chat/providers/meta/meta.provider.ts` (normalizador de webhooks)
- `gateway/src/domains/chat/providers/meta/meta.client.ts` (cliente HTTP para Meta Graph API)
- `gateway/src/domains/chat/providers/meta/meta.adapter.ts` (implementação do MetaWhatsAppProvider)
- `gateway/src/domains/chat/providers/meta/meta.dto.ts` (DTOs específicos)
- `gateway/src/domains/chat/providers/meta/meta.module.ts` (module)

**C — Comportamento:**
```
ANTES:
- Não existe provider Meta

DEPOIS:
- MetaWhatsAppProvider estende WhatsAppProvider com métodos específicos
- listTemplates() → busca na Meta API com cache Redis 15min
- sendTemplate() → envia template com validação de params
- normalizeWebhook() → MÉTODO ASSÍNCRONO, resolve phone_number_id via HTTP
- sendMedia() → stub (not implemented)
```

**E — Evidência:**
- [ ] Interface MetaWhatsAppProvider define name='meta'
- [ ] listTemplates filtra apenas APPROVED
- [ ] normalizeWebhook é async e retorna Promise<NormalizedWebhookEvent>
- [ ] Module exports providers necessários

**Status:** ✅ Concluída

---

#### TASK-039.8 ⏳: Criar MetaLookupService (HTTP para Backend)

**T — Tarefa:** Criar serviço HTTP no Gateway para comunicar com Backend e resolver phone_number_id → instance.

**A — Arquivo:**
- `gateway/src/domains/chat/http/meta-lookup.service.ts`

**C — Comportamento:**
```
ANTES:
- Gateway não tem serviço HTTP para chamar Backend

DEPOIS:
- MetaLookupService usa HttpService injetado
- resolvePhoneNumberId(phoneNumberId) → GET /api/chat/instances/by-phone-number/{phoneNumberId}
- Usa Authorization: Bearer {GATEWAY_SECRET}
- Retorna null em caso de erro (não exception)
```

**E — Evidência:**
- [ ] Service usa @Inject('BACKEND_URL') e @Inject('GATEWAY_SECRET')
- [ ] Método resolvePhoneNumberId retorna InstanceLookupResult|null
- [ ] Logger.warn em caso de falha
- [ ] Timeout de 5s

**Status:** ✅ Concluída

---

#### TASK-039.9 ⏳: Atualizar WhatsAppProvider.normalizeWebhook e adapters para async

**T — Tarefa:** Atualizar interface base e adapters existentes (uazapi, zapi) para normalizeWebhook assíncrono.

**A — Arquivo:**
- `gateway/src/domains/chat/contracts/provider.interface.ts`
- `gateway/src/domains/chat/providers/uazapi/uazapi.adapter.ts`
- `gateway/src/domains/chat/providers/zapi/zapi.adapter.ts`

**C — Comportamento:**
```
ANTES:
- normalizeWebhook() é síncrono, retorna NormalizedWebhookEvent

DEPOIS:
- normalizeWebhook() é async, retorna Promise<NormalizedWebhookEvent>
- Adapters uazapi e zapi atualizam método para assíncrono
- uazapi adapter: retorna await this.lookupService.resolvePhoneNumberId() se necessário
```

**E — Evidência:**
- [ ] Interface base define normalizeWebhook como Promise
- [ ] UazapiAdapter.normalizeWebhook é async
- [ ] ZapiAdapter.normalizeWebhook é async
- [ ] Compila sem erro

**Status:** ✅ Concluída

---

#### TASK-039.10 ⏳: Criar MetaWebhookController (GET e POST com HMAC)

**T — Tarefa:** Criar controller dedicado para webhook da Meta com handshake verification e validação HMAC.

**A — Arquivo:**
- `gateway/src/domains/chat/controllers/meta-webhook.controller.ts`

**C — Comportamento:**
```
ANTES:
- Gateway não tem endpoint para webhooks da Meta

DEPOIS:
- GET /webhooks/meta → handshake verification (hub.mode, hub.verify_token, hub.challenge)
- POST /webhooks/meta → processa webhook com X-Hub-Signature-256 validation
- HMAC: crypto.timingSafeEqual para comparar assinaturas
- 403 se signature inválida ou missing
- Encaminha para ChatWebhookService.handle('meta', '', payload, null)
```

**E — Evidência:**
- [ ] GET retorna challenge se token válido
- [ ] POST valida HMAC com META_APP_SECRET
- [ ] POST rejeita请求 sem signature (403)
- [ ] POST rejeita signature inválida (403)

**Status:** ✅ Concluída

---

#### TASK-039.11 ⏳: Criar endpoint GET /channels/{id}/templates

**T — Tarefa:** Criar endpoint no channels controller para listar templates aprovados da Meta.

**A — Arquivo:**
- `gateway/src/domains/chat/channels.controller.ts`

**C — Comportamento:**
```
ANTES:
- Endpoint /channels/{id}/templates não existe

DEPOIS:
- GET /channels/{id}/templates → busca channel por id → instance.token → listTemplates()
- Retorna array de MetaTemplate (APPROVED only)
- Usa cache Redis 15min
```

**E — Evidência:**
- [ ] Endpoint responde com array de templates
- [ ] Só retorna templates APPROVED
- [ ] 404 se channel não existe ou não é Meta
- [ ] Cache funciona (segunda chamada não bate Meta API)

**Status:** ✅ Concluída

---

#### TASK-039.12 ⏳: Registrar MetaAdapter no ProviderFactory + ChatModule

**T — Tarefa:** Registrar MetaAdapter no Map do provider factory e importar MetaModule no chat module.

**A — Arquivo:**
- `gateway/src/domains/chat/providers/provider.factory.ts`
- `gateway/src/domains/chat/chat.module.ts`
- `gateway/src/domains/chat/models/provider.model.ts`

**C — Comportamento:**
```
ANTES:
- ProviderFactory só conhece uazapi e zapi
- ProviderName type não tem 'meta'

DEPOIS:
- ProviderFactory.set('meta', MetaAdapter)
- ProviderName type = 'uazapi' | 'zapi' | 'meta'
- ChatModule importa MetaModule
- MetaWebhookController registrado
```

**E — Evidência:**
- [ ] Factory accepts 'meta' key
- [ ] ProviderName type inclui 'meta'
- [ ] ChatModule compila com MetaModule import

**Status:** ✅ Concluída

---

## 🔄 FASE 4: FRONTEND (Angular)

### Tasks

#### TASK-039.13 ⏳: Criar TemplateSelector component

**T — Tarefa:** Criar componente de seleção de template para envio via Meta (dentro do new-conversation-modal).

**A — Arquivo:**
- `app/src/app/pages/chat/components/new-conversation-modal/components/template-selector/template-selector.ts`
- `app/src/app/pages/chat/components/new-conversation-modal/components/template-selector/template-selector.html`

**C — Comportamento:**
```
ANTES:
- Não existe template selector

DEPOIS:
- TemplateSelectorComponent recebe channelId como input
- Carrega templates via GET /api/v1/channels/{id}/templates
- Mostra dropdown com templates APPROVED (nome, categoria, idioma)
- Emite templateSelected com templateName + params
- Valida número de parâmetros vs template.components
```

**E — Evidência:**
- [ ] Componente é standalone
- [ ] Input channelId dispara loadTemplates()
- [ ] Mostra apenas templates APPROVED
- [ ] Emite evento ao selecionar
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

#### TASK-039.14 ⏳: Criar WindowVerificationService

**T — Tarefa:** Criar serviço para verificar janela 24h do contato antes de enviar mensagem.

**A — Arquivo:**
- `app/src/app/pages/chat/components/new-conversation-modal/services/window-verification.service.ts`
- `app/src/app/core/models/window-status.model.ts`

**C — Comportamento:**
```
ANTES:
- Frontend não verifica janela 24h

DEPOIS:
- WindowVerificationService.checkStatus(contactId) → GET /api/chat/contacts/{id}/window-status
- Retorna Observable<WindowStatus>
- WindowStatus: { canSendFreeText: boolean, lastMessageAt: Date|null }
- Usa cached result se disponível (staleTime 30s)
```

**E — Evidência:**
- [ ] Service retorna Observable<WindowStatus>
- [ ] Model tem interface correta
- [ ] HTTP GET para window-status endpoint
- [ ] Cache com staleTime 30s

**Status:** ✅ Concluída

---

#### TASK-039.15 ⏳: Atualizar NewConversationModal com lógica 3 modos

**T — Tarefa:** Atualizar modal para suportar 3 modos de envio baseado em provider e janela 24h.

**A — Arquivo:**
- `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts`
- `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.html`

**C — Comportamento:**
```
ANTES:
- Modal só envia texto livre

DEPOIS:
- selectProvider(provider):
  - Se provider='meta' && contactSelected → checkWindowStatus()
- selectContact(contact):
  - Se provider='meta' → checkWindowStatus()
- checkWindowStatus():
  - GET /api/chat/contacts/{contactId}/window-status
  - Atualiza modo UI:
    - canSendFreeText=true → modoTextoLivre (input texto)
    - canSendFreeText=false → modoTemplate (TemplateSelector)
- loadTemplates() [só modoTemplate]
- submit():
  - modoTemplate → POST /api/v1/channels/{id}/send-template
  - modoTextoLivre → POST /api/v1/channels/{id}/send-text
```

**E — Evidência:**
- [ ] UI muda entre texto livre e template selector
- [ ] Meta provider respeita janela 24h
- [ ] Outros providers sempre texto livre
- [ ] Submit chama endpoint correto baseado no modo
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

#### TASK-039.16 ⏳: Atualizar ChannelForm para Meta (phone_number_id, access_token)

**T — Tarefa:** Atualizar form de canal para incluir campos específicos do Meta.

**A — Arquivo:**
- `app/src/app/pages/chat/channel/components/channel-form/channel-form.ts`
- `app/src/app/pages/chat/channel/components/channel-form/channel-form.html`

**C — Comportamento:**
```
ANTES:
- ChannelForm só tem campos comuns (name, provider dropdown)

DEPOIS:
- providerOptions inclui 'meta'
- Se provider='meta':
  - Mostra campos: Phone Number ID, Access Token
  - Campos são required quando provider=meta
- Campos hidden se provider não é meta
```

**E — Evidência:**
- [ ] Provider dropdown inclui 'meta' option
- [ ] Campos Meta aparecem só quando meta selecionado
- [ ] Campos são required para provider=meta
- [ ] Validação funciona
- [ ] Teste unitário passa

**Status:** ✅ Concluída

---

## 🔄 FASE 5: INTEGRATION / QA

### Tasks

#### TASK-039.17 ⏳: Testes E2E fluxo completo

**T — Tarefa:** Executar testes E2E validando fluxo completo: criar canal Meta, verificar janela 24h, enviar template.

**A — Arquivo:**
- Test file em `app-e2e/` ou `tests/e2e/`

**C — Comportamento:**
```
ANTES:
- Sem testes E2E para Meta

DEPOIS:
- Teste: Criar canal com provider=meta (phone_number_id, access_token)
- Teste: Verificar GET /api/chat/instances/by-phone-number/{phoneNumberId} retorna instance correta
- Teste: Webhook Meta → evento chega no sistema
- Teste: GET /channels/{id}/templates retorna lista
- Teste: POST /channels/{id}/send-template envia mensagem
- Teste: Janela 24h → modo texto livre
- Teste: Fora janela 24h → modo template
```

**E — Evidência:**
- [ ] Todos E2E testes passam
- [ ] Webhook delivery test passa
- [ ] Template send test passa
- [ ] Window verification test passa

**Status:** ⏳ Pendente (pré-existente - E2E não implementado)

---

## Revisão de Tasks

| Task | Status | Validada por | Data |
|------|--------|--------------|------|
| TASK-039.1 | ✅ | BACKEND + QA | 2026-04-11 |
| TASK-039.2 | ✅ | BACKEND + QA | 2026-04-11 |
| TASK-039.3 | ✅ | BACKEND + QA | 2026-04-11 |
| TASK-039.4 | ✅ | BACKEND + QA | 2026-04-11 |
| TASK-039.5 | ✅ | DBA + QA | 2026-04-11 |
| TASK-039.6 | ✅ | QA | 2026-04-11 |
| TASK-039.7 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.8 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.9 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.10 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.11 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.12 | ✅ | GATEWAY + QA | 2026-04-11 |
| TASK-039.13 | ✅ | FRONTEND + QA | 2026-04-11 |
| TASK-039.14 | ✅ | FRONTEND + QA | 2026-04-11 |
| TASK-039.15 | ✅ | FRONTEND + QA | 2026-04-11 |
| TASK-039.16 | ✅ | FRONTEND + QA | 2026-04-11 |
| TASK-039.17 | ⏳ | - | - (pré-existente) |

---

## Progresso

- [16/17] Tasks concluídas
- [x] Feature completa (exceto E2E)
