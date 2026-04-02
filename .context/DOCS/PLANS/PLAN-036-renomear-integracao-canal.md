# PLAN-036 — Renomear "Integração" → "Canal"

## Identificação

- **Módulo:** Chat
- **Fase atual:** PLANNING
- **Camadas impactadas:** Backend (Laravel) | Frontend (Angular) | Gateway (NestJS)
- **Tipo de mudança:** Rename semântico + refatoração de código

---

## 1. Definição da Mudança

| Contexto | Antes | Depois |
|----------|-------|--------|
| Rota API | `/api/integrations` | `/api/chat/channels` |
| Rota Frontend | `/chat/integration` | `/chat/channel` |
| Menu Label | "Integrações" | "Canais" |
| Permissão | `integrations.whatsapp.view` | `chat.channel.view` |
| Permissão | `integrations.whatsapp.manage` | `chat.channel.manage` |
| Service Class | `ChatIntegrationConnector` | `ChatChannelConnector` |
| Campo plano | `whatsapp_integrations_limit` | `chat_channels_limit` |
| Campo JSON | `settings_json.integration_id` | `settings_json.channel_provider_id` |
| Campo JSON | `settings_json.integration_fallback_message` | `settings_json.channel_fallback_message` |
| Config | `services.integrations` | `services.channels` |
| Env var | `INTEGRATIONS_WEBHOOK_BASE_URL` | `CHANNELS_WEBHOOK_BASE_URL` |
| Broadcast | `integration.connection` | `chat.channel.connection` |
| Redis channel | `integration.connection` | `chat.channel.connection` |
| Log channel | `integration.uazapi.*` | `channel.uazapi.*` |

---

## 2. Escopo DENTRO

### Backend (Laravel) — 28+ arquivos

**Classes a renomear:**
- `api/src/Domain/Chat/Services/ChatIntegrationConnector.php` → `ChatChannelConnector.php`
- `api/tests/Unit/Chat/ChatIntegrationConnectorTest.php` → `ChatChannelConnectorTest.php`

**Arquivos com referências a atualizar:**
- `api/src/Domain/Chat/Routes/chat.php` — route prefix + phpDoc
- `api/src/Domain/Chat/Actions/ChatInstanceActions.php` — import, type, messages, method names, config
- `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php` — validation rules keys
- `api/src/Domain/Chat/Http/Resources/ChatInstanceResource.php` — JSON keys, method name
- `api/src/Domain/Chat/Actions/ChatUazapiWebhookActions.php` — broadcast event
- `api/src/Domain/Chat/Policies/ChatInstancePolicy.php` — permission strings
- `api/src/Domain/Auth/Actions/AuthLoginActions.php` — menu label, route, permission
- `api/config/services.php` — config key + env var

**Campo `chat_channels_limit` (32+ ocorrências):**
- `api/src/Domain/Billing/` (5 arquivos)
- `api/src/Domain/Platform/` (8 arquivos)
- `api/src/Domain/Shared/` (CriticalDataCacheService)
- `api/database/seeders/` (PlatformPlanSeeder, DemoDataSeeder)
- `api/database/factories/PlatformPlanFactory.php`
- `api/database/seeders/AuthPermissionSeeder.php`
- `api/database/seeders/RolePermissionSeeder.php`

**Testes (16 arquivos):**
- `api/tests/Feature/ChatInstanceControllerTest.php`
- `api/tests/Feature/ChatMultiTenancyTest.php`
- `api/tests/Unit/Chat/ChatInstanceActionsTest.php`
- `api/tests/Unit/Chat/ChatInstancePolicyTest.php`
- `api/tests/Feature/Platform/PlatformPlanEnforcementTest.php`
- `api/tests/Feature/Platform/PlatformPlanControllerTest.php`
- `api/tests/Feature/Platform/PlatformPlanSeederTest.php`
- `api/tests/Feature/PlatformTenantDetailsTest.php`
- `api/tests/Feature/BillingSubscriptionTest.php`
- `api/tests/Unit/Platform/PlatformPlanEnforcementServiceTest.php`
- `api/tests/Unit/Platform/PlatformPlanModelTest.php`
- `api/tests/Unit/Platform/Actions/CreatePlatformPlanActionTest.php`
- `api/tests/Unit/Platform/PlatformReportsModeTest.php`
- `api/tests/Unit/Domain/Shared/CriticalDataCacheServiceTest.php`
- `api/tests/Feature/AutopilotRunDispatcherListenerTest.php`
- `api/tests/Unit/Chat/ChatMessageActionsTest.php`

### Frontend (Angular) — 15+ arquivos

**Renomear diretório:**
`app/src/app/pages/chat/integration/` → `app/src/app/pages/chat/channel/`

**Service:**
`app/src/app/core/services/integration.service.ts` → `channel.service.ts`

**Demais arquivos:**
- `app/src/app/core/services/instance.service.ts`
- `app/src/app/core/services/chat-realtime.service.ts`
- `app/src/app/core/services/chat-realtime.events.ts`
- `app/src/app/app.routes.ts`
- `app/src/app/layout/components/sidenav/menu-config.ts`
- `app/src/app/pages/chat/transmission-list/chat-transmission-list-form.ts`
- `app/src/app/pages/chat/transmission-list/chat-transmission-list-form.spec.ts`
- `app/src/app/pages/auth/roles/components/role-form/role-form.ts`
- `app/src/app/shared/models/subscription.model.ts`
- `app/src/app/pages/platform/models/platform-plan.model.ts`
- `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.ts`
- `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.html`
- `app/src/app/pages/settings/my-plan/my-plan.spec.ts`

### Gateway (NestJS) — 4 arquivos

- `gateway/src/shared/constants/gateway.constants.ts`
- `gateway/src/domains/chat/services/webhook-realtime-emitter.service.ts`
- `gateway/src/domains/chat/services/payload-semantics-resolver.service.ts`
- `gateway/src/domains/chat/services/chat-webhook.service.spec.ts`

---

## 3. Escopo FORA

- **Tabela `chat_instances`** — não renomear
- **docs/uazapi-openapi-spec.yaml** — especificação externa
- **landing/index.html** — landing marketing
- **`.context/DOCS/`** — documentação
- **`.github/skills/` e `.claude/skills/`** — skills

---

## 4. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Quebrar APIs existentes | Alta | Crítico | Mudar rota para `/api/chat/channels` |
| Broadcast/Redis fora de sincronia | Média | Alto | Mudar backend + gateway juntos |
| Testes quebrando | Alta | Médio | Atualizar todos os testes |

---

## 5. Estimativa

- **Complexidade:** High (50+ arquivos)
- **Ordem:** Backend → Gateway → Frontend
