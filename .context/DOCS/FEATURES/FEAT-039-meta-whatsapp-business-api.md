# PLAN-039 — Meta WhatsApp Business API Integration

## 1. Objetivo

Integrar a **Meta WhatsApp Business API** como provider de gateway (assim como UAZAPI e Z-API), com suporte a:
- Envio de mensagens via template aprovado (obrigatório fora da janela 24h)
- Envio de texto livre (dentro da janela 24h)
- Listagem de templates aprovados
- Verificação de janela 24h via Backend

## 2. Arquitetura (CORRIGIDA)

```
┌─────────────────────────────────────────────────────────────┐
│                     FRONTEND (Angular)                       │
│  NewConversationModal                                        │
│  1. Seleciona CANAL (provider)                               │
│  2. Pesquisa e seleciona CONTATO                             │
│  3. Backend verifica janela 24h para o contato              │
│     ├─ Meta + FORA 24h → Template Selector                  │
│     ├─ Meta + DENTRO 24h → Texto Livre                      │
│     └─ Outros → Texto Livre                                 │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      BACKEND (Laravel)                       │
│  VerifyContactWindowAction → Verifica última mensagem       │
│  do cliente (is_from_contact=true) nas últimas 24h         │
│  GET /api/chat/contacts/{contactId}/window-status          │
│                                                              │
│  InstanceLookupAction → Resolve phone_number_id → instance  │
│  GET /api/chat/instances/by-phone-number/{phoneNumberId}   │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              │   HTTP via GATEWAY_SECRET      │
              │   (Gateway → Backend REST API) │
              ▼                               ▼
┌─────────────────────────────────────────────────────────────┐
│                      GATEWAY (NestJS)                        │
│  ┌─────────────────────────────────────────────────────┐   │
│  │            WhatsAppMetaProvider                       │   │
│  │  - sendText()      → Texto livre (janela 24h)      │   │
│  │  - sendTemplate()   → Template (obrigatório fora)    │   │
│  │  - listTemplates()  → Meta API /message_templates   │   │
│  │  - normalizeWebhook()→ Normaliza eventos da Meta     │   │
│  │  - Métodos extras   → sendMedia, getStatus, etc.    │   │
│  └─────────────────────────────────────────────────────┘   │
│  MetaWebhookController (NOVO)                              │
│  - GET /webhooks/meta  → handshake verification            │
│  - POST /webhooks/meta → processa + HMAC signature         │
│                                                              │
│  IMPORTANT: Gateway NÃO acessa banco do Backend diretamente. │
│  Toda resolução de phone_number_id → instance é via HTTP.    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    META WHATSAPP BUSINESS API
                    - Graph API v18.0
                    - Phone Number ID + Access Token
```

---

## 3. Arquivos a Criar/Editar

### 3.1 GATEWAY (NestJS)

#### NOVOS:

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `gateway/src/domains/chat/providers/meta/meta.provider.ts` | Normalizador de webhooks da Meta |
| 2 | `gateway/src/domains/chat/providers/meta/meta.client.ts` | Cliente HTTP para Meta Graph API |
| 3 | `gateway/src/domains/chat/providers/meta/meta.adapter.ts` | Adaptador implementando `MetaWhatsAppProvider` |
| 4 | `gateway/src/domains/chat/providers/meta/meta.dto.ts` | DTOs específicos da Meta |
| 5 | `gateway/src/domains/chat/providers/meta/meta.module.ts` | Module do Meta provider |
| 6 | `gateway/src/domains/chat/contracts/meta-provider.interface.ts` | Interface dedicada para Meta |
| 7 | `gateway/src/domains/chat/http/meta-lookup.service.ts` | Serviço HTTP para chamar Backend (resolve phone_number_id) |

#### EDITAR:

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `gateway/src/domains/chat/contracts/provider.interface.ts` | Adicionar `'meta'` em `WhatsAppProvider.name` e `NormalizedWebhookEvent.provider`, alterar `normalizeWebhook` para `async` (return `Promise`) |
| 2 | `gateway/src/domains/chat/models/provider.model.ts` | Adicionar `'meta'` ao type `ProviderName` |
| 3 | `gateway/src/domains/chat/providers/provider.factory.ts` | Registrar `MetaAdapter` no Map |
| 4 | `gateway/src/domains/chat/chat.module.ts` | Importar `MetaModule` e registrar `MetaWebhookController` |
| 5 | `gateway/src/domains/chat/channels.controller.ts` | Adicionar endpoint `GET /channels/{id}/templates` |
| 6 | `gateway/src/domains/chat/providers/uazapi/uazapi.adapter.ts` | Atualizar `normalizeWebhook` para método assíncrono |
| 7 | `gateway/src/domains/chat/providers/zapi/zapi.adapter.ts` | Atualizar `normalizeWebhook` para método assíncrono |

#### NOVOS (Controllers):

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `gateway/src/domains/chat/controllers/meta-webhook.controller.ts` | Controller dedicado para Meta (GET handshake, POST webhook com HMAC) |

---

### 3.2 BACKEND (Laravel)

#### NOVOS:

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `api/src/Domain/Chat/Actions/VerifyContactWindowAction.php` | Verifica se contato está dentro da janela 24h |
| 2 | `api/src/Domain/Chat/Actions/LookupInstanceByPhoneNumberAction.php` | Resolve phone_number_id → ChatInstance |
| 3 | `api/src/Domain/Chat/Http/Controllers/ChatWindowController.php` | Controller para endpoints de verificação e lookup |
| 4 | `api/src/Domain/Chat/Http/Middleware/GatewaySecretGuard.php` | Middleware de autorização para o Gateway |
| 5 | `api/src/Domain/Chat/DTOs/ContactWindowStatusDTO.php` | DTO readonly de resposta com status da janela |
| 6 | `api/src/Domain/Chat/DTOs/InstanceLookupDTO.php` | DTO readonly para resultado do lookup |
| 7 | `api/tests/Unit/Chat/VerifyContactWindowActionTest.php` | Testes unitários do Action |

#### EDITAR:

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `api/src/Domain/Chat/Enums/ProviderType.php` | Adicionar `case META = 'meta'` |
| 2 | `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php` | Adicionar `'meta'` na regra `in` + rules para `settings.phone_number_id` e `settings.access_token` |
| 3 | `api/src/Domain/Chat/Routes/api.php` | Adicionar rotas `window-status` e `instances/by-phone-number` |

#### MIGRATION:

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `api/database/migrations/YYYY_MM_DD_HHMMSS_add_phone_number_id_index_to_chat_instances.php` | Adiciona índice para busca por phone_number_id |

---

### 3.3 FRONTEND (Angular)

#### NOVOS:

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `app/src/app/pages/chat/components/new-conversation-modal/components/template-selector/template-selector.ts` | Componente de seleção de template |
| 2 | `app/src/app/pages/chat/components/new-conversation-modal/components/template-selector/template-selector.html` | Template do selector |
| 3 | `app/src/app/pages/chat/components/new-conversation-modal/services/window-verification.service.ts` | Serviço para verificar janela 24h |
| 4 | `app/src/app/core/models/window-status.model.ts` | Modelo do status da janela |

#### EDITAR:

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts` | Adicionar lógica de verificação de janela + selector de canal + template |
| 2 | `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.html` | Adicionar campos: selector de provider, template selector, preview |
| 3 | `app/src/app/pages/chat/channel/components/channel-form/channel-form.ts` | Adicionar `'meta'` às `providerOptions` |
| 4 | `app/src/app/pages/chat/channel/components/channel-form/channel-form.html` | Adicionar campos específicos do Meta (Phone Number ID, Access Token) |

---

## 4. Interfaces e Tipos

### 4.1 Gateway — MetaWhatsAppProvider Interface

```typescript
// gateway/src/domains/chat/contracts/meta-provider.interface.ts

import { WhatsAppProvider, SendTextRequest, SendMediaRequest, SendMessageResult, NormalizedWebhookEvent } from './provider.interface';

/**
 * Interface dedicada para Meta WhatsApp Business API.
 * Estende WhatsAppProvider com métodos específicos que só a Meta suporta.
 */
export interface MetaWhatsAppProvider extends WhatsAppProvider {
  readonly name: 'meta';

  /** Lista templates aprovados da conta Business (usa cache Redis TTL 15min). */
  listTemplates(instanceToken: string): Promise<MetaTemplate[]>;

  /** Envia mensagem via template aprovado (obrigatório fora da janela 24h). */
  sendTemplate(instanceToken: string, request: SendTemplateRequest): Promise<SendMessageResult>;

  /**
   * Normaliza payload do webhook da Meta.
   * Chama Backend via HTTP para resolver phone_number_id → ChatInstance.
   * MÉTODO ASSÍNCRONO.
   */
  normalizeWebhook(webhookToken: string, rawPayload: unknown): Promise<NormalizedWebhookEvent>;
}

export interface SendTemplateRequest {
  to: string;
  templateName: string;
  templateParams?: string[];
  language?: string; // default: 'pt_BR'
}

export interface MetaTemplate {
  name: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED';
  category: string;
  language: string;
  components: TemplateComponent[];
}

export interface TemplateComponent {
  type: 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';
  params?: string[];
}

/**
 * Payload completo do webhook da Meta WhatsApp Business API.
 * Ref: https://developers.facebook.com/docs/whatsapp/webhooks/webhooks-payload
 */
export interface MetaWebhookPayload {
  object: 'whatsapp_business_account';
  entry: Array<{
    id: string;
    time: number;
    changes: Array<{
      value: {
        messaging_product: 'whatsapp';
        metadata: {
          display_phone_number: string;
          phone_number_id: string;
        };
        contacts?: Array<{
          wa_id: string;
          profile: { name: string };
        }>;
        messages?: Array<{
          from: string;
          id: string;
          timestamp: string;
          type: string;
          text?: { body: string };
          image?: { id: string; mime_type: string; sha256: string; url?: string };
          audio?: { id: string; mime_type: string; voice: boolean };
          video?: { id: string; mime_type: string; sha256: string };
          document?: { id: string; mime_type: string; sha256: string; filename: string };
          location?: { latitude: number; longitude: number; name?: string; address?: string };
          contacts?: Array<{
            wa_id: string;
            profile: { name: string };
            phones: Array<{ phone: string; type?: string }>;
          }>;
        }>;
        statuses?: Array<{
          id: string;
          recipient_id: string;
          status: string;
          timestamp: string;
          conversation?: {
            id: string;
            origin: { type: string };
            expiry?: string;
          };
          pricing?: {
            billable: boolean;
            pricing_model: string;
            category: string;
          };
        }>;
      };
      field: string;
    }>;
  }>;
}
```

### 4.2 Gateway — MetaLookupService (HTTP)

```typescript
// gateway/src/domains/chat/http/meta-lookup.service.ts

import { Injectable, Logger } from '@nestjs/common';

/**
 * Serviço HTTP para comunicação Gateway → Backend.
 * O Gateway NÃO acessa banco do Backend diretamente.
 * Usa GATEWAY_SECRET para autenticação.
 */
@Injectable()
export class MetaLookupService {
  private readonly logger = new Logger(MetaLookupService.name);

  constructor(
    private readonly http: HttpService,
    @Inject('BACKEND_URL') private readonly backendUrl: string,
    @Inject('GATEWAY_SECRET') private readonly secret: string,
  ) {}

  /**
   * Resolve phone_number_id da Meta para dados da ChatInstance.
   * Chamado pelo normalizeWebhook para obter tenantId e instanceId.
   */
  async resolvePhoneNumberId(phoneNumberId: string): Promise<InstanceLookupResult | null> {
    try {
      const response = await this.http.axiosRef.get(
        `${this.backendUrl}/api/chat/instances/by-phone-number/${phoneNumberId}`,
        {
          headers: { Authorization: `Bearer ${this.secret}` },
          timeout: 5000,
        }
      );
      return response.data;
    } catch (error) {
      this.logger.warn(`Failed to resolve phone_number_id ${phoneNumberId}: ${error.message}`);
      return null;
    }
  }
}

export interface InstanceLookupResult {
  instanceId: string;
  tenantId: string;
  webhookToken: string;
}
```

### 4.3 Backend — LookupInstanceByPhoneNumberAction

```php
// api/src/Domain/Chat/Actions/LookupInstanceByPhoneNumberAction.php

final readonly class LookupInstanceByPhoneNumberAction
{
    /**
     * Resolve phone_number_id para dados da ChatInstance.
     * Usado pelo Gateway via HTTP para normalizar webhooks.
     *
     * @param string $phoneNumberId Phone Number ID da Meta
     * @return InstanceLookupDTO|null
     */
    public function execute(string $phoneNumberId): ?InstanceLookupDTO
    {
        $instance = ChatInstance::query()
            ->where('settings_json->phone_number_id', $phoneNumberId)
            ->where('provider', 'meta')
            ->first();

        if (!$instance) {
            return null;
        }

        return InstanceLookupDTO::from([
            'instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'webhook_token' => $instance->webhook_token,
        ]);
    }
}
```

### 4.4 Backend — InstanceLookupDTO

```php
// api/src/Domain/Chat/DTOs/InstanceLookupDTO.php

final readonly class InstanceLookupDTO
{
    public function __construct(
        public string $instanceId,
        public string $tenantId,
        public string $webhookToken,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            instanceId: $data['instance_id'],
            tenantId: $data['tenant_id'],
            webhookToken: $data['webhook_token'],
        );
    }
}
```

### 4.5 Backend — ChatWindowController (CORRIGIDO)

```php
// api/src/Domain/Chat/Http/Controllers/ChatWindowController.php

final class ChatWindowController extends Controller
{
    public function __construct(
        private readonly VerifyContactWindowAction $verifyWindowAction,
        private readonly LookupInstanceByPhoneNumberAction $lookupAction,
    ) {}

    /**
     * Verifica se o contato está dentro da janela de 24h.
     * GET /api/chat/contacts/{id}/window-status
     */
    public function windowStatus(Request $request, string $id): JsonResponse
    {
        $result = $this->verifyWindowAction->execute(
            $request->user()->tenant_id,
            $id
        );

        return $this->success($result);
    }

    /**
     * Resolve phone_number_id para ChatInstance.
     * GET /api/chat/instances/by-phone-number/{phoneNumberId}
     * Called by Gateway (not authenticated as user, uses GATEWAY_SECRET).
     * NOTA: Protegido por GatewaySecretGuard, permitindo query global sem auth user.
     */
    public function lookupByPhoneNumber(Request $request, string $phoneNumberId): JsonResponse
    {
        $result = $this->lookupAction->execute($phoneNumberId);

        if (!$result) {
            return $this->error('Instance not found', 404);
        }

        return $this->success($result);
    }
}
```

### 4.6 Gateway — MetaAdapter com normalizeWebhook ASSÍNCRONO

```typescript
// gateway/src/domains/chat/providers/meta/meta.adapter.ts

@Injectable()
export class MetaAdapter implements MetaWhatsAppProvider {
  readonly name = 'meta' as const;

  constructor(
    private readonly client: MetaClient,
    private readonly redis: Redis,
    private readonly lookupService: MetaLookupService,  // HTTP service
    private readonly logger: Logger,
  ) {}

  async listTemplates(instanceToken: string): Promise<MetaTemplate[]> {
    const cacheKey = `meta:templates:${instanceToken}`;

    // Check cache first (TTL 15 minutes)
    const cached = await this.redis.get(cacheKey);
    if (cached) return JSON.parse(cached);

    // Fetch from Meta API - filtra apenas APPROVED
    const templates = await this.client.getTemplates(instanceToken, {
      status: 'APPROVED',
    });

    // Cache for 15 minutes
    await this.redis.setex(cacheKey, 900, JSON.stringify(templates));

    return templates;
  }

  async sendTemplate(instanceToken: string, request: SendTemplateRequest): Promise<SendMessageResult> {
    // Valida número de parâmetros antes de enviar
    const templates = await this.listTemplates(instanceToken);
    const template = templates.find(t => t.name === request.templateName);

    if (!template) {
      return { success: false, error: `Template '${request.templateName}' not found` };
    }

    const bodyParams = template.components.find(c => c.type === 'BODY')?.params ?? [];
    if ((request.templateParams?.length ?? 0) !== bodyParams.length) {
      return {
        success: false,
        error: `Template expects ${bodyParams.length} parameters, got ${request.templateParams?.length}`,
      };
    }

    return this.client.sendTemplate(instanceToken, request);
  }

  // Stubs para métodos obrigatórios não aplicáveis
  async sendMedia(token: string, request: SendMediaRequest): Promise<SendMessageResult> {
    throw new Error('Not implemented for Meta');
  }
  async getStatus(token: string) { return { connected: true, loggedIn: true }; }
  async disconnect(token: string) {}
  async getQrCode(token: string) { return null; }

  // ✅ CORRIGIDO: Método ASSÍNCRONO e compatível com base interface

  async normalizeWebhook(webhookToken: string, rawPayload: unknown): Promise<NormalizedWebhookEvent> {
    const payload = rawPayload as MetaWebhookPayload;

    // Extrai phone_number_id do payload
    const phoneNumberId = payload.entry[0]?.changes[0]?.value?.metadata?.phone_number_id;

    // Resolve via HTTP para Backend (não acessa banco direto!)
    const instance = await this.lookupService.resolvePhoneNumberId(phoneNumberId);

    if (!instance) {
      throw new Error(`Instance not found for phone_number_id: ${phoneNumberId}`);
    }

    // Valida webhook_token da instância
    if (instance.webhookToken !== webhookToken) {
      throw new Error('Webhook token mismatch');
    }

    return {
      tenantId: instance.tenantId,
      instanceId: instance.instanceId,
      instanceWebhookToken: webhookToken,
      provider: 'meta',
      eventType: payload.entry[0]?.changes[0]?.field ?? 'messages',
      direction: 'inbound',
      message: this.extractMessage(payload),
      rawPayload: payload as unknown as Record<string, unknown>,
      idempotencyKey: `${webhookToken}:${payload.entry[0]?.id ?? 'unknown'}:${Date.now()}`,
      receivedAt: new Date(),
    };
  }

  /**
   * Extrai mensagem normalizada do payload da Meta.
   */
  private extractMessage(payload: MetaWebhookPayload): MessagePayload | undefined {
    const msg = payload.entry[0]?.changes[0]?.value?.messages?.[0];
    if (!msg) return undefined;

    return {
      id: msg.id,
      from: msg.from,
      to: payload.entry[0]?.changes[0]?.value?.metadata?.display_phone_number ?? '',
      type: msg.type as MessagePayload['type'],
      text: msg.text?.body ?? '',
      timestamp: new Date(parseInt(msg.timestamp) * 1000),
      isFromMe: false,
      isGroup: false,
    };
  }
}
```

### 4.7 Gateway — Meta Webhook Controller (GET e POST com HMAC)

```typescript
// gateway/src/domains/chat/controllers/meta-webhook.controller.ts

@Controller({ version: '1', path: 'webhooks/meta' })
export class MetaWebhookController {
  constructor(
    @Inject('META_VERIFY_TOKEN') private readonly verifyToken: string,
    @Inject('META_APP_SECRET') private readonly appSecret: string,
    private readonly chatWebhookService: ChatWebhookService,
  ) {}

  @Get()
  async verifyWebhook(
    @Query('hub.mode') mode: string,
    @Query('hub.verify_token') token: string,
    @Query('hub.challenge') challenge: string,
  ): Promise<string> {
    if (mode === 'subscribe' && token === this.verifyToken) {
      return challenge; // Global App verification
    }
    throw new ForbiddenException('Invalid verification token');
  }

  @Post()
  async handleWebhook(
    @Headers('x-hub-signature-256') signature: string,
    @Req() req: Request,
  ): Promise<{ success: boolean }> {
    // 1. HMAC Verification
    if (!signature) throw new ForbiddenException('Missing signature');
    const expectedSig = 'sha256=' + crypto.createHmac('sha256', this.appSecret).update(req.rawBody).digest('hex');
    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSig))) {
      throw new ForbiddenException('Invalid signature');
    }

    // 2. Extrai phone_number_id
    const payload = req.body as MetaWebhookPayload;

    // 3. Encaminha para processamento assíncrono de webhook (não tem webhookToken na URL)
    await this.chatWebhookService.handle('meta', '', payload, null);
    
    return { success: true };
  }
}
```

---

## 5. API Endpoints

### Backend (Laravel)

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| GET | `/api/chat/contacts/{id}/window-status` | Verifica janela 24h do contato | Sanctum (user) |
| GET | `/api/chat/instances/by-phone-number/{phoneNumberId}` | Resolve phone_number_id → instance | GATEWAY_SECRET |

### Gateway (NestJS)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/channels/{id}/templates` | Lista templates aprovados da Meta |
| POST | `/channels/{id}/send-template` | Envia mensagem via template |
| GET | `/webhooks/meta` | Webhook verification handshake (Global App) |
| POST | `/webhooks/meta` | Webhook events con validation (HMAC) |

---

## 6. Database — Índice para phone_number_id

```php
// api/database/migrations/YYYY_MM_DD_HHMMSS_add_phone_number_id_to_chat_instances.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Índice expression para busca em JSONB path específico
        // Query: WHERE settings_json->>'phone_number_id' = 'xxx' AND provider = 'meta'
        // NÃO usa GIN (GIN é para full-text search em JSON)
        // Partial index WHERE provider = 'meta' para eficiência
        DB::statement("CREATE INDEX idx_phone_number_id_lookup ON chat_instances ((settings_json->>'phone_number_id')) WHERE provider = 'meta'");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_phone_number_id_lookup");
    }
};
```

---

## 7. Environment Variables

### Adicionar em `dependencies.yaml`:

```yaml
  # Meta WhatsApp Business API
  - key: "META_VERIFY_TOKEN"
    required: true
    description: "Token de verificação do webhook Meta (configurado no Meta Dev Portal)"
  - key: "META_APP_SECRET"
    required: true
    description: "App Secret da Meta para validação HMAC da assinatura de webhooks"
  - key: "META_WEBHOOK_CALLBACK_URL"
    required: true
    description: "URL pública do webhook para receber eventos da Meta"
```

### No ChatInstance settings_json:

| Campo | Descrição |
|-------|-----------|
| `phone_number_id` | ID do número de telefone na Meta (ex: "1234567890") |
| `access_token` | Token de acesso da Meta Business API |

---

## 8. Validações

### 8.1 ChatInstanceRequest — Settings para Meta

```php
// api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php

'settings.phone_number_id' => ['nullable', 'string', 'max:255'],
'settings.access_token' => ['nullable', 'string', 'max:500'],
```

### 8.2 ProviderType Enum

```php
// api/src/Domain/Chat/Enums/ProviderType.php

enum ProviderType: string
{
    case UAZAPI = 'uazapi';
    case ZAPI = 'zapi';
    case META = 'meta';
}
```

---

## 9. Tasks Derivadas

| Task | Descrição | Agente |
|------|-----------|--------|
| TASK-039.1 | Gateway: Criar interface `MetaWhatsAppProvider` + provider (client, adapter) | BACKEND |
| TASK-039.2 | Gateway: Criar `MetaLookupService` (HTTP para Backend) | BACKEND |
| TASK-039.2.1 | Gateway: Atualizar `WhatsAppProvider.normalizeWebhook` e adapters para `async` | BACKEND |
| TASK-039.3 | Gateway: Criar endpoint `GET /channels/{id}/templates` | BACKEND |
| TASK-039.4 | Gateway: Criar `MetaWebhookController` (GET e POST com validação HMAC) | BACKEND |
| TASK-039.5 | Backend: Criar `LookupInstanceByPhoneNumberAction` + `InstanceLookupDTO` + `GatewaySecretGuard` | BACKEND |
| TASK-039.6 | Backend: Criar `VerifyContactWindowAction` + `ContactWindowStatusDTO` | BACKEND |
| TASK-039.7 | Backend: Criar `ChatWindowController` + rotas | BACKEND |
| TASK-039.8 | Backend: Criar migration para índice `phone_number_id` | DBA |
| TASK-039.9 | Backend: Criar `VerifyContactWindowActionTest` (testes unitários) | QA |
| TASK-039.10 | Backend: Atualizar `ProviderType` + `ChatInstanceRequest` | BACKEND |
| TASK-039.11 | Frontend: Criar `TemplateSelector` component | FRONTEND |
| TASK-039.12 | Frontend: Criar `WindowVerificationService` | FRONTEND |
| TASK-039.13 | Frontend: Atualizar `NewConversationModal` com lógica 3 modos | FRONTEND |
| TASK-039.14 | Frontend: Atualizar `ChannelForm` para Meta (phone_number_id, access_token) | FRONTEND |
| TASK-039.15 | Config: Adicionar `META_VERIFY_TOKEN` às env vars | OPS |
| TASK-039.16 | QA: Testes E2E fluxo completo | QA |

---

## 10. Fluxo de Interação (Frontend)

```
open()
├── loadInstances() — carrega canais
├── reset state
└── isOpen.set(true)

selectProvider(provider)
└── Se provider = 'meta' && contactSelected → checkWindowStatus()

selectContact(contact)
├── contactSelected.set(contact)
└── Se provider = 'meta' → checkWindowStatus()

checkWindowStatus()
├── GET /api/chat/contacts/{contactId}/window-status
└── Atualiza modo UI:
    ├── canSendFreeText = true  → modoTextoLivre = true, modoTemplate = false
    └── canSendFreeText = false → modoTextoLivre = false, modoTemplate = true

loadTemplates()  [só quando modoTemplate = true]
└── GET /api/v1/channels/{channelId}/templates

submit()
├── Se modoTemplate → POST /api/v1/channels/{id}/send-template
└── Se modoTextoLivre → POST /api/v1/channels/{id}/send-text
```

---

## 11. Cache Strategy

| Dados | Storage | TTL | Invalidation |
|-------|---------|-----|--------------|
| Templates (Meta) | Redis (Gateway) | 15 min | On error, or manual refresh |

### Redis Key Naming Convention

```
meta:{env}:templates:{phoneNumberId}
```

Exemplo: `meta:prod:templates:1234567890`

---

## 12. Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Meta API rate limits | Redis cache TTL 15min em `listTemplates` |
| Template não aprovado | UI filtra apenas `status: 'APPROVED'` |
| Webhook verification handshake | Implementar GET endpoint, valida `webhook_token` da instância |
| Telefone não formatado | Usar PhoneInputComponent existente com validação |
| Parâmetros incorretos no template | Validar `params.length` antes de enviar na API |
| Timezone mismatch | Usar Carbon UTC, comparações em `diffInMinutes()` |
| phone_number_id não encontrado | 404 → log + alerta de configuração |
| Gateway não acessa banco Backend | Toda resolução via HTTP (MetaLookupService) |
| Webhook token mismatch | Verificar com `instance.webhookToken !== verifyToken` |

---

## 13. Dependências Externas

- Meta Business API credentials (Phone Number ID + Access Token)
- Template aprovado na Meta Business Console
- Webhook URL pública ou ngrok para desenvolvimento
- `META_VERIFY_TOKEN` configurado no Meta Dev Portal E no `.env`

---

## 14. Notas de Implementação

### 14.1 Arquitetura Distribuída — Regra Principal

```
❌ ERRADO: Gateway → PostgreSQL (banco do Backend)
✅ CORRETO: Gateway → Backend REST API → PostgreSQL
```

O Gateway é um serviço SEPARADO. Não pode acessar o banco do Backend diretamente.
Toda comunicação é via HTTP usando `GATEWAY_SECRET`.

### 14.2 Webhook Flow Completo

```
1. Meta API → POST /webhooks/meta (com header `X-Hub-Signature-256`)
2. Gateway (`MetaWebhookController`): Verifica HMAC vs `META_APP_SECRET`. 403 se falhar.
3. Gateway: Passa o payload para o Service, queue processa `meta.adapter`.
4. Gateway (`normalizeWebhook`): Faz HTTP GET /api/chat/instances/by-phone-number/{phoneNumberId}
5. Backend (`LookupAction` via `GatewaySecretGuard`): Retorna { instanceId, tenantId, webhookToken }
6. Gateway: Normaliza evento com tenant + instance e segue o fluxo padrão.
```

### 14.3 Border Case: Exatamente 24h

**Regra: Exactly 24h = FORA da janela (texto livre NÃO permitido)**

A janela de 24h é calculada como:
- **DENTRO**: `created_at > now() - 24 hours` (mais de 24h atrás)
- **FORA**: `created_at <= now() - 24 hours` (exatamente 24h ou menos)

Usar `where('created_at', '>=', now()->subHours(24))` para queries eficientes.
Esse operador `>=` significa que exatamente 24h retorna FALSE (fora da janela).

**Testes obrigatórios:**
- 23h59m → DENTRO (canSendFreeText = true)
- 24h00m → FORA (canSendFreeText = false)
- 24h01m → FORA (canSendFreeText = false)
- Sem mensagens → FORA (canSendFreeText = false)

### 14.4 Template Params Validation

A Meta API rejeita se o número de params não corresponder ao header.
Validar ANTES de enviar:
```typescript
const bodyParams = template.components.find(c => c.type === 'BODY')?.params ?? [];
if (params.length !== bodyParams.length) {
  throw new BadRequestException(`Template expects ${bodyParams.length} params`);
}
```
