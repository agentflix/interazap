# PLAN-041 — Integração Telegram Bot API

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo                  | Valor                       |
| ---------------------- | --------------------------- |
| **ID**                 | FEAT-041                    |
| **Nome**               | Integração Telegram Bot API |
| **Bounded Context**    | Gateway, Chat               |
| **Complexidade**       | G                           |
| **Prioridade**         | Should                      |
| **Status**             | 🔵 Em Refatoração           |
| **Criada em**          | 2026-04-13                  |
| **Última atualização** | 2026-04-14                  |
| **Análise de Desvios** | `telegram_plan_analysis.md` |
| **Plano de Refatoração** | `refactoring_plan.md`      |

---

## 1. Resumo

Integrar o **Telegram** como novo canal de comunicação no InteraZap, permitindo que empresas atendam clientes via Telegram Bot com as mesmas funcionalidades já disponíveis para WhatsApp (UaZapi, Z-API, Meta) e Webchat.

---

## 2. Objetivo

- Expandir os canais de atendimento suportados para além do WhatsApp
- Alavancar a base de 900M+ de usuários ativos do Telegram
- Manter paridade funcional com os canais existentes (AI bot, auto-reply, tickets, CRM)
- Seguir o padrão Adapter-Factory-Provider já consolidado na plataforma

---

## 3. Escopo

### Dentro do Escopo ✅

- [ ] Criação de instância Telegram (canal) via bot token
- [ ] Recebimento de mensagens inbound via webhook (`setWebhook`) ou Long Polling
- [ ] Envio de mensagens outbound (texto, foto, vídeo, áudio, documento, location)
- [ ] Normalização de webhooks Telegram → `NormalizedWebhookEvent`
- [ ] Integração com AI bot (roteamento automático)
- [ ] Integração com auto-reply/autopilot
- [ ] Criação de tickets/contatos CRM a partir de chats Telegram
- [ ] Formulário de canal no frontend com campos Telegram-específicos
- [ ] Typing indicator (`sendChatAction`)
- [ ] Edição de mensagens (`edited_message`)
- [ ] Suporte a reações (`message_reaction`)

### Fora do Escopo ❌

- Suporte a grupos/supergrupos Telegram (apenas chats privados nesta iteração)
- Telegram Business API (contas comerciais)
- Inline bots / Mini Apps / Games
- Pagamentos via Telegram Stars
- Telegram Passport
- Stickers customizados (receber sim, enviar não)

---

## 4. Análise da Arquitetura Atual

> ⚠️ **Arquitetura original contém 6 desvios identificados.** Ver `telegram_plan_analysis.md`.
> A arquitetura abaixo reflete o **estado refatorado** aprovado.

### 4.1 Arquitetura Refatorada (Estado Alvo)

```
┌──────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Angular 19 — Standalone)             │
│  ChannelFormComponent (standalone: true, Signals)                │
│  - providerOptions: ['uazapi', 'zapi', 'meta', 'web', 'telegram']│
│  - Estado reativo via signal() / computed() / effect()          │
│  - WebSocketService com reconexão + fallback SSE                 │
└──────────────────────────────────────────────────────────────────┘
                              │
                              │ WebSocket / SSE (/telegram namespace)
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                       GATEWAY (NestJS)                            │
│                                                                    │
│  ┌─────────────────────────────────────────────────────────┐     │
│  │                    src/bot/ MODULE                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │     │
│  │  │  Controllers │  │   Services   │  │    Guards    │  │     │
│  │  │ - Webhook    │  │ - TelegramCl.│  │ - HMAC Sign. │  │     │
│  │  │ - Updates    │  │ - CircuitBrk │  │ - JWT Auth   │  │     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  │     │
│  │  ┌──────────────┐  ┌──────────────────────────────────┐ │     │
│  │  │  Strategies  │  │    DTOs (class-validator)         │ │     │
│  │  │ - LongPoll.. │  │  @IsString, @ValidateNested       │ │     │
│  │  │ - Webhook... │  │  @IsEnum, @IsOptional, @IsInt     │ │     │
│  │  │ (via .env)   │  │  whitelist + forbidNonWhitelisted │ │     │
│  │  └──────────────┘  └──────────────────────────────────┘ │     │
│  │                                                           │     │
│  │  ┌────────────────────────────────────────────────────┐  │     │
│  │  │               src/common/secrets/                  │  │     │
│  │  │  SecretsService: AWS SM → Vault → ENV (fallback)   │  │     │
│  │  └────────────────────────────────────────────────────┘  │     │
│  └─────────────────────────────────────────────────────────┘     │
│                                                                    │
│  WebSocketGateway (/telegram, Socket.IO + JWT)                    │
│  TelegramWebhookController  (POST /webhooks/telegram/{token})     │
└──────┬───────────────────────────────────┬────────────────────────┘
       │ Redis Streams (async)              │ Circuit Breaker wraps
       ▼                                   ▼
┌──────────────────┐              ┌─────────────────────┐
│   BACKEND        │              │   TELEGRAM BOT API  │
│   (Laravel 12)   │◄─JWT/REST───►│   api.telegram.org  │
│   Business Logic │              │   (Webhook / Poll)  │
└──────────────────┘              └─────────────────────┘
```

### 4.2 Providers existentes

| Provider            | Tipo              | Identificador   | Conexão        |
| ------------------- | ----------------- | --------------- | -------------- |
| UaZapi              | WhatsApp          | Phone JID       | QR Code / Pair |
| Z-API               | WhatsApp          | Phone JID       | QR Code / Pair |
| Meta                | WhatsApp Business | Phone Number ID | Access Token   |
| Web                 | Webchat           | Session ID      | Embed code     |
| **Telegram** (novo) | **Telegram Bot**  | **Chat ID**     | **Bot Token**  |

### 4.3 Diferenças fundamentais WhatsApp vs Telegram

| Aspecto            | WhatsApp (UaZapi/Meta)          | Telegram Bot                                    |
| ------------------ | ------------------------------- | ----------------------------------------------- |
| **Identificador**  | Número de telefone (JID)        | Chat ID (int64 → string)                        |
| **Conexão**        | QR Code / Phone Number ID       | Bot Token (estático via @BotFather)             |
| **Webhook setup**  | Configurado no provider externo | Chamada `setWebhook` na Telegram API            |
| **Status entrega** | sent → delivered → read         | Apenas `sent` (sem delivered/read)              |
| **Limite arquivo** | 16-100MB (varia)                | 50MB (upload) / 20MB (download)                 |
| **Sessão**         | Instância persistente           | Stateless (token fixo)                          |
| **Contato**        | `phone` / `phone_e164`          | `chat_id` + `username` (telefone opcional)      |
| **Janela 24h**     | Obrigatória (Meta)              | Não existe — bot pode enviar a qualquer momento |
| **Rate limit**     | Varia por provider              | 30 msg/s (private), 20 msg/min (grupo)          |

---

## 5. Dependências

| Feature/Sistema                      | Tipo       | Status       | Blocker |
| ------------------------------------ | ---------- | ------------ | ------- |
| Gateway NestJS (Adapter-Factory)     | Depende    | ✅ Pronta    | Não     |
| Redis Streams infra                  | Depende    | ✅ Pronta    | Não     |
| ChatInstance (settings_json JSONB)   | Depende    | ✅ Pronta    | Não     |
| PLAN-039 Meta (padrão de referência) | Referência | ✅ Concluída | Não     |

---

## 6. Critérios de Aceite

### 6.1 Funcionais (Originais)

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | Admin pode criar instância Telegram com bot token | [ ] | ❌ |
| CA-002 | Ao conectar, webhook é registrado via `setWebhook` na Telegram API | [ ] | ❌ |
| CA-003 | Mensagem texto inbound do Telegram cria ticket + contato CRM | [ ] | ❌ |
| CA-004 | Mensagem texto outbound é enviada via `sendMessage` | [ ] | ❌ |
| CA-005 | Mídias (foto, vídeo, áudio, documento) são recebidas e enviadas | [ ] | ❌ |
| CA-006 | AI bot responde mensagens Telegram quando `is_bot_active = true` | [ ] | ❌ |
| CA-007 | Auto-reply funciona em tickets Telegram | [ ] | ❌ |
| CA-008 | Typing indicator exibido via `sendChatAction` | [ ] | ❌ |
| CA-009 | Edição de mensagem (`edited_message`) atualiza mensagem no sistema | [ ] | ❌ |
| CA-010 | Ao desconectar, webhook é removido via `deleteWebhook` | [ ] | ❌ |
| CA-011 | Frontend exibe campos corretos ao selecionar provider Telegram | [ ] | ❌ |
| CA-012 | Contatos Telegram sem telefone são armazenados com `chat_id` | [ ] | ❌ |
| CA-013 | Status de entrega mostra apenas "enviado" (sem delivered/read) | [ ] | ❌ |

### 6.2 Arquiteturais — Refatoração (AC-041-Rx)

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-014 / AC-041-R1 | `src/bot/` existe como módulo top-level no NestJS sem nenhum artefato em `domains/chat/providers/telegram/` | Lint + compilação isolada | ❌ |
| CA-015 / AC-041-R2 | Tokens acessados **exclusivamente** via `SecretsService` (AWS SM → Vault → ENV). Nenhum token em coluna de banco, log ou resposta HTTP | Scan de secrets + unit tests SecretsService | ❌ |
| CA-016 / AC-041-R3 | Circuit Breaker abre após 5 falhas consecutivas (10s window); fast-fail por 30s; half-open com 3 requests de teste; backoff 1–32s com jitter | Testes unitários com mock da Telegram API | ❌ |
| CA-017 / AC-041-R4 | `TELEGRAM_POLLING_MODE=long_polling` ativa Long Polling (55s timeout, reconexão automática); `=webhook` ativa endpoint HMAC-SHA256. Troca sem restart | Teste funcional por feature flag | ❌ |
| CA-018 / AC-041-R5 | Todos os DTOs usam class-validator. ValidationPipe global com `whitelist: true` e `forbidNonWhitelisted: true`. Payload inválido → HTTP 400 sem processamento | Testes de payload malicioso | ❌ |
| CA-019 / AC-041-R6 | Todos os componentes Angular com `standalone: true`, `@if`/`@for`/`@switch`, sem `NgModule` | Code review + lint Angular | ❌ |
| CA-020 / AC-041-R7 | Estado gerenciado exclusivamente via `signal()` / `computed()` / `effect()`. Zero `BehaviorSubject` para state | Code review + Playwright | ❌ |
| CA-021 / AC-041-R8 | WebSocket `/telegram` com JWT. Eventos emitidos em < 100ms. Reconexão Angular backoff 1–60s. Fallback SSE disponível | Teste de desconexão + latência | ❌ |
| CA-022 / AC-041-R9 | Todos os logs JSON com `traceId`, `spanId`, `timestamp ISO 8601`, `level`, `service`. Zero dados sensíveis nos logs | Revisão de log estruturado | ❌ |
| CA-023 / AC-041-R10 | OpenAPI/Swagger disponível em `/api/docs` cobrindo todos os endpoints webhook + REST + eventos WS | Validação da spec gerada | ❌ |

---

## 7. Arquivos a Criar/Editar

> ⚠️ **Desvio Corrigido:** Artefatos movidos de `domains/chat/providers/telegram/` para `src/bot/` (isolamento DDD).

### 7.1 GATEWAY (NestJS)

#### NOVOS — src/bot/ (Módulo Isolado):

| # | Arquivo | Descrição |
|---|---------|-----------|
| 1 | `gateway/src/bot/bot.module.ts` | Módulo NestJS raiz do Bot Telegram |
| 2 | `gateway/src/bot/dto/telegram-update.dto.ts` | DTOs com class-validator (`@IsString`, `@ValidateNested`, `@IsEnum`) |
| 3 | `gateway/src/bot/services/telegram-client.service.ts` | HTTP client para `api.telegram.org/bot<token>/` |
| 4 | `gateway/src/bot/services/circuit-breaker.service.ts` | Circuit Breaker (5 falhas → open 30s, backoff 1-32s) |
| 5 | `gateway/src/bot/services/strategies/polling-strategy.factory.ts` | Fábrica `TELEGRAM_POLLING_MODE=long_polling\|webhook` |
| 6 | `gateway/src/bot/services/strategies/long-polling.strategy.ts` | Long Polling offset-based (timeout 55s, reconexão auto) |
| 7 | `gateway/src/bot/services/strategies/webhook.strategy.ts` | Webhook mode com HMAC-SHA256 |
| 8 | `gateway/src/bot/controllers/telegram-webhook.controller.ts` | `POST /webhooks/telegram/{token}` com guard HMAC |
| 9 | `gateway/src/bot/guards/webhook-hmac-signature.guard.ts` | Verificação HMAC do `X-Telegram-Bot-Api-Secret-Token` |
| 10 | `gateway/src/bot/gateways/telegram-websocket.gateway.ts` | Socket.IO Gateway (`/telegram`, JWT auth, heartbeat 30s) |
| 11 | `gateway/src/common/secrets/secrets.service.ts` | SecretsService: AWS SM → Vault → ENV fallback |
| 12 | `gateway/src/common/logger/logger.service.ts` | JSON Logger (Pino/Winston) com traceId via AsyncLocalStorage |
| 13 | `gateway/src/common/validation/telegram-id.validator.ts` | Custom validator para `chat_id` (int64 como string) |
| 14 | `gateway/test/bot/telegram-client.spec.ts` | Testes unitários do client |
| 15 | `gateway/test/bot/circuit-breaker.spec.ts` | Testes do Circuit Breaker (open/half-open/close) |
| 16 | `gateway/test/bot/telegram-update.dto.spec.ts` | Testes de validação dos DTOs |
| 17 | `gateway/test/bot/secrets.service.spec.ts` | Testes do SecretsService (3 estratégias) |
| 18 | `gateway/test/e2e/telegram-flow.e2e.spec.ts` | E2E Playwright: webhook → Redis → WS → Angular |

#### EDITAR:

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `gateway/src/domains/chat/models/provider.model.ts` | Adicionar `'telegram'` ao type `ProviderName` |
| 2 | `gateway/src/domains/chat/contracts/provider.interface.ts` | Adicionar `'telegram'` ao union em `NormalizedWebhookEvent.provider` |
| 3 | `gateway/src/domains/chat/models/outbound.model.ts` | Adicionar `'telegram'` ao provider union em `OutboundMessage` |
| 4 | `gateway/src/domains/chat/providers/provider.factory.ts` | Registrar adapter Telegram via evento Redis (sem acoplamento direto) |
| 5 | `gateway/src/app.module.ts` | Importar `BotModule` e `CommonModule` |

#### DELETAR (após migração validada):

| # | Arquivo | Motivo |
|---|---------|---------|
| 1 | `gateway/src/domains/chat/providers/telegram/` (diretório) | Substituído por `src/bot/` (DDD isolation) |

### 7.2 BACKEND (Laravel)

#### NOVOS:

| #   | Arquivo                                                      | Descrição                            |
| --- | ------------------------------------------------------------ | ------------------------------------ |
| 1   | `api/src/Domain/Chat/Actions/ChatTelegramWebhookActions.php` | Handler de webhooks Telegram inbound |
| 2   | `api/tests/Feature/Chat/TelegramWebhookTest.php`             | Testes de ingestão de webhook        |
| 3   | `api/tests/Feature/Chat/TelegramOutboundTest.php`            | Testes de envio outbound             |

#### EDITAR:

| #   | Arquivo                                                        | Mudança                                                           |
| --- | -------------------------------------------------------------- | ----------------------------------------------------------------- |
| 1   | `api/src/Domain/Chat/Enums/ProviderType.php`                   | Adicionar `case TELEGRAM = 'telegram'` com label `'Telegram'`     |
| 2   | `api/config/gateway.php`                                       | Adicionar streams `telegram_request` e `telegram_response_prefix` |
| 3   | `api/src/Domain/Chat/Services/ChatGatewayService.php`          | Routing outbound para stream Telegram                             |
| 4   | `api/src/Domain/Chat/Services/ChatChannelConnector.php`        | Setup de webhook Telegram via `setWebhook` ao conectar            |
| 5   | `api/src/Domain/Chat/Services/ChatWebhookPayloadExtractor.php` | Parsing de payload Telegram                                       |
| 6   | `api/src/Domain/Chat/Routes/chat.php`                          | Rota `POST /webhooks/telegram/instances/{token}`                  |
| 7   | `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php`    | Adicionar `'telegram'` na regra `in` + validação de `bot_token`   |

#### MIGRATION:

| #   | Arquivo                                                                   | Descrição                                                            |
| --- | ------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| 1   | `api/database/migrations/YYYY_MM_DD_HHMMSS_add_telegram_channel_type.php` | Adicionar `'telegram'` às constraints de `channel` em `chat_tickets` |

### 7.3 FRONTEND (Angular)

#### EDITAR:

| #   | Arquivo                                                                    | Mudança                                                                                         |
| --- | -------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| 1   | `app/src/app/pages/chat/channel/components/channel-form/channel-form.ts`   | Adicionar `{ label: 'Telegram', value: 'telegram' }` ao `providerOptions` + campos condicionais |
| 2   | `app/src/app/pages/chat/channel/components/channel-form/channel-form.html` | Template condicional: bot_token quando provider = telegram; esconder campos WhatsApp            |
| 3   | `app/src/app/pages/chat/channel/channel.html`                              | Ícone/badge para canais Telegram na listagem                                                    |

---

## 8. Interfaces e Tipos

### 8.1 Gateway — TelegramClient

```typescript
// gateway/src/bot/services/telegram-client.service.ts

export class TelegramClient {
    constructor(private readonly httpService: HttpService) {}

    /** Envia texto. Retorna message_id. */
    async sendMessage(botToken: string, chatId: string, text: string): Promise<TgResult<TgMessage>>;

    /** Envia foto (URL ou file_id). */
    async sendPhoto(botToken: string, chatId: string, photo: string, caption?: string): Promise<TgResult<TgMessage>>;

    /** Envia vídeo. */
    async sendVideo(botToken: string, chatId: string, video: string, caption?: string): Promise<TgResult<TgMessage>>;

    /** Envia áudio/voice. */
    async sendVoice(botToken: string, chatId: string, voice: string, caption?: string): Promise<TgResult<TgMessage>>;

    /** Envia documento genérico. */
    async sendDocument(
        botToken: string,
        chatId: string,
        document: string,
        caption?: string,
    ): Promise<TgResult<TgMessage>>;

    /** Envia localização. */
    async sendLocation(
        botToken: string,
        chatId: string,
        latitude: number,
        longitude: number,
    ): Promise<TgResult<TgMessage>>;

    /** Indica que o bot está "digitando". */
    async sendChatAction(botToken: string, chatId: string, action: 'typing'): Promise<TgResult<boolean>>;

    /** Registra webhook. */
    async setWebhook(botToken: string, url: string, secretToken: string): Promise<TgResult<boolean>>;

    /** Remove webhook. */
    async deleteWebhook(botToken: string): Promise<TgResult<boolean>>;

    /** Verifica status do webhook. */
    async getWebhookInfo(botToken: string): Promise<TgResult<TgWebhookInfo>>;

    /** Verifica se o token é válido. */
    async getMe(botToken: string): Promise<TgResult<TgUser>>;

    /** Baixa arquivo do Telegram. */
    async getFile(botToken: string, fileId: string): Promise<TgResult<TgFile>>;
}
```

### 8.2 Gateway — Tipos Telegram (principais)

```typescript
// gateway/src/bot/dto/telegram-update.dto.ts

export interface TgResult<T> {
    ok: boolean;
    result?: T;
    error_code?: number;
    description?: string;
    parameters?: { retry_after?: number; migrate_to_chat_id?: number };
}

export interface TgUpdate {
    update_id: number;
    message?: TgMessage;
    edited_message?: TgMessage;
    message_reaction?: TgMessageReaction;
}

export interface TgMessage {
    message_id: number;
    from?: TgUser;
    chat: TgChat;
    date: number; // Unix timestamp
    text?: string;
    photo?: TgPhotoSize[];
    video?: TgVideo;
    voice?: TgVoice;
    audio?: TgAudio;
    document?: TgDocument;
    sticker?: TgSticker;
    location?: TgLocation;
    contact?: TgContact;
    caption?: string;
    reply_to_message?: TgMessage;
    edit_date?: number;
}

export interface TgUser {
    id: number;
    is_bot: boolean;
    first_name: string;
    last_name?: string;
    username?: string;
    language_code?: string;
}

export interface TgChat {
    id: number; // int64 — armazenar como string
    type: 'private' | 'group' | 'supergroup' | 'channel';
    title?: string;
    username?: string;
    first_name?: string;
    last_name?: string;
}

export interface TgPhotoSize {
    file_id: string;
    file_unique_id: string;
    width: number;
    height: number;
    file_size?: number;
}

export interface TgVideo {
    file_id: string;
    file_unique_id: string;
    width: number;
    height: number;
    duration: number;
    mime_type?: string;
    file_size?: number;
}

export interface TgVoice {
    file_id: string;
    file_unique_id: string;
    duration: number;
    mime_type?: string;
    file_size?: number;
}

export interface TgAudio {
    file_id: string;
    file_unique_id: string;
    duration: number;
    performer?: string;
    title?: string;
    mime_type?: string;
    file_size?: number;
}

export interface TgDocument {
    file_id: string;
    file_unique_id: string;
    file_name?: string;
    mime_type?: string;
    file_size?: number;
}

export interface TgSticker {
    file_id: string;
    file_unique_id: string;
    type: string;
    width: number;
    height: number;
    emoji?: string;
}

export interface TgLocation {
    latitude: number;
    longitude: number;
}

export interface TgContact {
    phone_number: string;
    first_name: string;
    last_name?: string;
    user_id?: number;
}

export interface TgFile {
    file_id: string;
    file_unique_id: string;
    file_size?: number;
    file_path?: string; // usar https://api.telegram.org/file/bot<token>/<file_path>
}

export interface TgWebhookInfo {
    url: string;
    has_custom_certificate: boolean;
    pending_update_count: number;
    last_error_date?: number;
    last_error_message?: string;
}

export interface TgMessageReaction {
    chat: TgChat;
    message_id: number;
    user?: TgUser;
    date: number;
    old_reaction: TgReactionType[];
    new_reaction: TgReactionType[];
}

export interface TgReactionType {
    type: 'emoji' | 'custom_emoji';
    emoji?: string;
    custom_emoji_id?: string;
}
```

### 8.3 Gateway — Normalizer

```typescript
// gateway/src/bot/services/telegram-normalizer.service.ts

export class TelegramNormalizer {
    /**
     * Converte TgUpdate → NormalizedWebhookEvent
     *
     * Mapeamento:
     * - update.message (from.is_bot=false) → direction: 'inbound', eventType: 'message'
     * - update.message (from.is_bot=true)  → direction: 'outbound', eventType: 'message'
     * - update.edited_message              → eventType: 'message' (com flag edited)
     * - update.message_reaction            → eventType: 'message_status' (reaction)
     *
     * Mapeamento de tipo de mídia:
     * - message.text         → type: 'text'
     * - message.photo        → type: 'image'   (maior resolução: último item do array)
     * - message.video        → type: 'video'
     * - message.voice        → type: 'audio'
     * - message.audio        → type: 'audio'
     * - message.document     → type: 'document'
     * - message.sticker      → type: 'sticker'
     * - message.location     → type: 'location'
     * - message.contact      → type: 'text' (formatado como contato)
     *
     * Identificador do contato (remote_jid):
     * - Usa chat.id convertido para string
     * - Phone: message.contact?.phone_number ou null
     *
     * Idempotency key: `tg-{update_id}`
     */
    normalize(webhookToken: string, update: TgUpdate): NormalizedWebhookEvent;
}
```

### 8.4 Backend — settings_json para Telegram

```json
{
    "bot_token": "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11",
    "bot_username": "my_company_bot",
    "bot_id": 123456,
    "webhook_secret": "random-generated-secret-256-chars"
}
```

### 8.5 Backend — ChatChannelConnector (novo fluxo Telegram)

```
connect(instance):
  1. Validar bot_token via getMe()
  2. Gerar webhook_secret (random 256 chars alfanuméricos)
  3. Chamar setWebhook(bot_token, url="{gateway_url}/webhooks/telegram/{webhook_token}", secret_token=webhook_secret)
  4. Armazenar bot_id, bot_username, webhook_secret em settings_json
  5. Atualizar status → 'connected'

disconnect(instance):
  1. Chamar deleteWebhook(bot_token)
  2. Atualizar status → 'disconnected'
```

---

## 9. Plano de Implementação (Tasks)

### Fase 1: Fundação Backend — NestJS (DDD Isolation + Polling + DTOs)

| Task ID | Descrição | Owner | Dependência | Prioridade | Status |
|---------|-----------|-------|-------------|------------|--------|
| TASK-T1 | Criar estrutura `src/bot/` com subpastas (controllers, services, dto, guards, gateways) e `bot.module.ts` | @BACKEND | — | 🔴 High | ⏳ |
| TASK-T2 | Criar DTOs completos `telegram-update.dto.ts` com class-validator (`@IsString`, `@ValidateNested`, `@IsEnum`, `@IsOptional`, `@IsInt`). Configurar `ValidationPipe` global com `whitelist + forbidNonWhitelisted` | @BACKEND | TASK-T1 | 🔴 High | ⏳ |
| TASK-T3 | Migrar `telegram.client.ts` de `domains/chat/providers/` para `src/bot/services/telegram-client.service.ts` | @BACKEND | TASK-T1 | 🔴 High | ⏳ |
| TASK-T4 | Criar `telegram.normalizer.ts` em `src/bot/services/` (sem dependências do domínio Chat) | @BACKEND | TASK-T3 | 🟡 Medium | ⏳ |
| TASK-T5 | Implementar `PollingStrategyFactory` com `LongPollingStrategy` (timeout 55s) e `WebhookStrategy`. Feature flag `TELEGRAM_POLLING_MODE` hot-swap sem restart | @BACKEND | TASK-T3 | 🔴 High | ⏳ |
| TASK-T6 | Criar `WebhookController` com guard `WebhookHmacSignatureGuard` (HMAC-SHA256 via `X-Telegram-Bot-Api-Secret-Token`) | @BACKEND | TASK-T2, TASK-T5 | 🔴 High | ⏳ |
| TASK-T7 | Atualizar `ProviderFactory`, `ProviderName`, `OutboundMessage` para incluir `'telegram'`. Comunicação via Redis (sem acoplamento direto) | @BACKEND | TASK-T4 | 🟡 Medium | ⏳ |
| TASK-T8 | Testes unitários: DTOs (payloads inválidos), client, normalizer, estratégias de polling | @QA | TASK-T6 | 🟡 Medium | ⏳ |

### Fase 2: Segurança, Logging & Resiliência

| Task ID | Descrição | Owner | Dependência | Prioridade | Status |
|---------|-----------|-------|-------------|------------|--------|
| TASK-T9 | Implementar `SecretsService` em `src/common/secrets/`: AWS Secrets Manager → HashiCorp Vault → ENV. Lança `ServiceUnavailableException` sem fonte válida | @BACKEND | TASK-T1 | 🔴 High | ⏳ |
| TASK-T10 | Implementar `CircuitBreakerService`: 5 falhas/10s → open 30s; half-open 3 requests; exponential backoff 1–32s com jitter; respeitar `retry_after` da API Telegram | @BACKEND | TASK-T3 | 🔴 High | ⏳ |
| TASK-T11 | Configurar JSON Logger (Pino/Winston) com `traceId` via `AsyncLocalStorage`. Mascarar tokens e dados sensíveis em todos os níveis de log | @BACKEND | — | 🟡 Medium | ⏳ |
| TASK-T12 | Gerar OpenAPI/Swagger em `/api/docs` cobrindo endpoints webhook, REST e contratos WS | @DOC | TASK-T6 | 🟡 Medium | ⏳ |
| TASK-T13 | Testes unitários do `SecretsService` (3 estratégias de fallback) e `CircuitBreakerService` | @QA | TASK-T9, TASK-T10 | 🔴 High | ⏳ |

### Fase 3: Frontend Angular 19 — Standalone + Signals + WebSocket

| Task ID | Descrição | Owner | Dependência | Prioridade | Status |
|---------|-----------|-------|-------------|------------|--------|
| TASK-T14 | Implementar `WebSocketGateway` NestJS (`/telegram` namespace, Socket.IO, JWT auth, heartbeat 30s, rate-limit 100/min) | @BACKEND | TASK-T6 | 🟡 Medium | ⏳ |
| TASK-T15 | Migrar componentes Angular para `standalone: true`, `@if`/`@for`/`@switch`, sem NgModules | @FRONTEND | — | 🟡 Medium | ⏳ |
| TASK-T16 | Refatorar estado Angular de `BehaviorSubject` para `signal()` / `computed()` / `effect()` | @FRONTEND | TASK-T15 | 🟡 Medium | ⏳ |
| TASK-T17 | Criar `WebSocketService` Angular: Socket.IO client, reconexão auto backoff 1–60s, fallback SSE | @FRONTEND | TASK-T14 | 🟡 Medium | ⏳ |
| TASK-T18 | Adicionar option `Telegram` ao `providerOptions` + campos condicionais `bot_token` + ícone/badge | @FRONTEND | TASK-T15 | 🟡 Medium | ⏳ |
| TASK-T19 | Testes do formulário Angular com provider Telegram (Jasmine/Karma) | @QA | TASK-T18 | 🟡 Medium | ⏳ |

### Fase 4: Backend Laravel 12 + Integração e QA

| Task ID | Descrição | Owner | Dependência | Prioridade | Status |
|---------|-----------|-------|-------------|------------|--------|
| TASK-T20 | Adicionar `TELEGRAM` ao `ProviderType` enum + label + configurar Redis streams Telegram em `gateway.php` | @BACKEND | — | 🔴 High | ⏳ |
| TASK-T21 | Criar `ChatTelegramWebhookActions` para ingestão + rota `POST /webhooks/telegram/instances/{token}` | @BACKEND | TASK-T20 | 🔴 High | ⏳ |
| TASK-T22 | Atualizar `ChatChannelConnector` — connect (`setWebhook`) / disconnect (`deleteWebhook`) com getMe() + webhook_secret | @BACKEND | TASK-T20 | 🔴 High | ⏳ |
| TASK-T23 | Migration: adicionar `'telegram'` ao channel de `chat_tickets` (backward compatible) | @DBA | — | 🟡 Medium | ⏳ |
| TASK-T24 | Testes Feature Laravel (webhook ingestion + outbound dispatch + connect/disconnect) | @QA | TASK-T21, TASK-T22 | 🟡 Medium | ⏳ |
| TASK-T25 | E2E Playwright: criar instância → conectar → receber webhook → responder → Angular WS update | @QA | Fases 1-3 | 🟢 Low | ⏳ |
| TASK-T26 | Validar AI bot routing + auto-reply + autopilot com mensagens Telegram | @QA | TASK-T25 | 🟢 Low | ⏳ |
| TASK-T27 | Revisão de segurança (SAST scan, token exposure, WebSocket bypass) | @REVIEWER | TASK-T13 | 🔴 High | ⏳ |
| TASK-T28 | Documentação: CHANGELOG + MEMORY + atualizar `project-state.yaml` | @DOC | TASK-T26 | 🟢 Low | ⏳ |

---

## 10. Riscos e Mitigações

| # | Risco | Impacto | Probabilidade | Mitigação |
|---|-------|---------|---------------|-----------|
| R1 | Interface `WhatsAppProvider` acoplada ao nome "WhatsApp" | Confusão / dívida técnica | Alta | Manter nome por ora (D1 resolvida); renomear para `ChannelProvider` em task separada após PLAN-041 |
| R2 | Chat ID do Telegram é `int64` (até 52 bits) | Overflow em linguagens com int32 | Baixa | Armazenar como `string` em `remote_jid` (já é string no schema). Custom validator `@IsTelegramChatId` |
| R3 | Sem delivery/read status no Telegram | UX diferente do WhatsApp | Média | Frontend exibe apenas "enviado" para canais Telegram (CA-013) |
| R4 | Rate limiting Telegram (30 msg/s private, 20 msg/min grupo) | Falha em envios em massa | Média | **Circuit Breaker** (TASK-T10) + respeitar `retry_after` de `ResponseParameters` |
| R5 | Webhook requer HTTPS em produção | Bloqueio em ambiente dev | Média | **Long Polling mode** (TASK-T5) elimina necessidade de ngrok/tunnel em dev |
| R6 | Contato Telegram sem telefone | `phone`/`phone_e164` null em tickets | Alta | Aceitar null; usar `chat_id` como identificador primário no CRM (D4 resolvida) |
| R7 | Bot token exposto em logs ou banco | Segurança crítica | Média | **SecretsService** (TASK-T9) + mascaramento de token em todos os logs (TASK-T11) |
| R8 | Event loop do NestJS bloqueado por Long Polling síncrono | Performance | Média | Long Polling com `async/await` + cancellation token no graceful shutdown (TASK-T5) |
| R9 | Componente Angular mistura NgModule e Standalone | Falha de compilação | Baixa | Migração completa para Standalone (TASK-T15); lint rule para bloquear NgModule misturado |

---

## 11. Decisões Pendentes

| #   | Decisão                                          | Opções                                                       | Recomendação                                                          | Status |
| --- | ------------------------------------------------ | ------------------------------------------------------------ | --------------------------------------------------------------------- | ------ |
| D1  | Renomear `WhatsAppProvider` → `ChannelProvider`? | (A) Renomear agora (B) Manter e documentar                   | (B) Manter — refactor cross-cutting com risco; fazer em task separada | ⏳     |
| D2  | Redis stream separado ou compartilhado?          | (A) Stream `telegram.*` separado (B) Reusar `whatsapp.*`     | (A) Separado — isolamento de falhas                                   | ⏳     |
| D3  | Suporte a grupos na v1?                          | (A) Sim (B) Não, apenas chats privados                       | (B) Não — simplifica v1, grupos como feature futura                   | ⏳     |
| D4  | Contato Telegram sem telefone no CRM?            | (A) Criar contato com `telegram_chat_id` (B) Exigir telefone | (A) Criar com chat_id — Telegram não garante acesso ao telefone       | ⏳     |

---

## 12. Compatibilidade de Tipos de Mensagem

| Tipo InteraZap (`chat_messages.type`) | Telegram Inbound                             | Telegram Outbound API     |
| ------------------------------------- | -------------------------------------------- | ------------------------- |
| `text`                                | `message.text`                               | `sendMessage`             |
| `image`                               | `message.photo` (array, usar último = maior) | `sendPhoto`               |
| `video`                               | `message.video`                              | `sendVideo`               |
| `audio`                               | `message.voice` / `message.audio`            | `sendVoice` / `sendAudio` |
| `document`                            | `message.document`                           | `sendDocument`            |
| `sticker`                             | `message.sticker`                            | `sendSticker`             |
| `location`                            | `message.location`                           | `sendLocation`            |

---

## Notas

- A Telegram Bot API está na versão 9.6 (abril 2026)
- Webhook suporta portas: 443, 80, 88, 8443
- Secret token no header `X-Telegram-Bot-Api-Secret-Token` garante autenticidade
- Arquivos podem ser referenciados por `file_id` (eficiente) ou URL HTTP (até 20MB)
- Para download: `https://api.telegram.org/file/bot<token>/<file_path>` (até 20MB)
- Long Polling: usar `offset = last_update_id + 1`; timeout até 55s (Telegram recomenda < 60s)
- Circuit Breaker deve respeitar o campo `parameters.retry_after` de respostas 429 da Telegram API
- `TgChat.id` é `int64` — sempre converter para `string` antes de persistir no PostgreSQL
- Componentes Angular 19 com `standalone: true` devem importar `CommonPipes`, `RouterModule`, etc. individualmente
- `AsyncLocalStorage` no NestJS garante propagação de `traceId` por toda a cadeia async/await sem passar manualmente

---

## Referências

- [Análise de Desvios Arquiteturais](../MEMORY/2026-04-14-telegram-desvios-arquiteturais.md)
- [Plano de Refatoração Completo](../../../.gemini/antigravity/brain/f1a112c2-df40-4d09-9811-0ea5f8afc36e/artifacts/refactoring_plan.md)
- [PLAN-039 Meta Integration](./PLAN-039-meta-whatsapp-integration.md) — padrão de referência para Adapter
- [Telegram Bot API Docs](https://core.telegram.org/bots/api) — v9.6
- [NestJS Resilience Patterns](https://docs.nestjs.com/fundamentals/async-providers)
