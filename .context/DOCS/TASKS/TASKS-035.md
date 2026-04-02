# TASKS-035 — Correção de file_name no recebimento de arquivos

**Entregas:** 3 | **Tasks:** 6

| Entrega | Descrição | Tasks | Status |
|---------|-----------|-------|--------|
| 1 | Frontend: Mapeamento WebSocket + Tipagem | TASK-035.1.1, TASK-035.1.2 | todo |
| 2 | Gateway: Z-API fileName para todos os tipos de mídia | TASK-035.2.1 | todo |
| 3 | Backend: SendMessageTool com parâmetros de arquivo | TASK-035.3.1, TASK-035.3.2, TASK-035.3.3 | todo |

---

## Entrega 1 — Frontend: Mapeamento WebSocket + Tipagem ✅ testável

**Entrega:** Mensagens recebidas via WebSocket exibem corretamente nome do arquivo, thumbnail e link de download | **Agente:** @FRONTEND

**Gate:** `cd app && pnpm run gate:all` — build + lint + testes passando.

### TASK-035.1.1 — Mapear propriedades de arquivo em handleRealtimeNewMessage

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Corrigir o `handleRealtimeNewMessage()` no `user-chat-thread.store.ts` para incluir as propriedades de arquivo (`file_name`, `file_url`, `mime_type`, `file_size`) ao criar o objeto `CalledMessage` a partir do evento WebSocket. Atualmente só 7 campos são mapeados, ignorando completamente os metadados de arquivo.

**Constraints**

- Seguir o padrão já existente em `chat.store.ts` (linhas 379-383) para status updates — usar type guards (`typeof === 'string'`)
- Manter null safety — campos não presentes no evento devem resultar em `null`
- `ChangeDetectionStrategy.OnPush` — signals devem ser atualizados corretamente
- Nenhum `any` ou `unknown`

**Context**

- Módulos afetados: Chat (Frontend)
- Dependências: Nenhuma
- Interface `CalledMessage` já suporta todos os campos necessários

**Context References**

- `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.store.ts` _(required in context)_
- `app/src/app/core/services/called-message.service.ts` _(required in context — interface CalledMessage)_
- `app/src/app/pages/chat/store/chat.store.ts` _(referência de padrão — linhas 379-383)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code (user-chat-thread.store.ts, lines 336-344)
const message: CalledMessage = {
  id: event.message.id,
  content: event.message.content ?? null,
  type: event.message.type,
  direction: event.message.direction === 'outgoing' ? 'outgoing' : 'incoming',
  status: event.message.status ?? null,
  created_at: event.message.created_at,
  ticket_id: currentId,
};
```

```typescript
// Expected code — adicionar propriedades de arquivo
const message: CalledMessage = {
  id: event.message.id,
  content: event.message.content ?? null,
  type: event.message.type,
  direction: event.message.direction === 'outgoing' ? 'outgoing' : 'incoming',
  status: event.message.status ?? null,
  created_at: event.message.created_at,
  ticket_id: currentId,
  // File properties
  file_url: typeof event.message.file_url === 'string' ? event.message.file_url : null,
  file_name: typeof event.message.file_name === 'string' ? event.message.file_name : null,
  mime_type: typeof event.message.mime_type === 'string' ? event.message.mime_type : null,
  file_size: typeof event.message.file_size === 'number' ? event.message.file_size : null,
  // Extended properties
  external_id: typeof event.message.external_id === 'string' ? event.message.external_id : null,
  media_transcription: typeof event.message.media_transcription === 'string' ? event.message.media_transcription : null,
  media_transcription_status: typeof event.message.media_transcription_status === 'string' ? (event.message.media_transcription_status as string) : undefined,
  reactions: Array.isArray(event.message.reactions) ? event.message.reactions : undefined,
  is_edited: typeof event.message.is_edited === 'boolean' ? event.message.is_edited : false,
  quoted_message: event.message.quoted_message ?? undefined,
};
```
</details>

**Etapas**

- [ ] 1. Editar `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.store.ts` — função `handleRealtimeNewMessage()` — adicionar mapeamento de file_name, file_url, mime_type, file_size e demais campos
- [ ] 2. Verificar que o componente `user-chat-message-bubble` já renderiza file_name (confirmar que não precisa de alteração)
- [ ] 3. Verificar `cd app && pnpm run gate:all`

**Critérios de conclusão**

- [ ] Mensagem recebida via WebSocket com arquivo exibe file_name no bubble
      -> `test_websocket_new_message_maps_file_properties`
- [ ] Mensagem recebida sem arquivo não quebra (null safety)
      -> `test_websocket_new_message_without_file_properties`
- [ ] Build e lint passando
      -> `gate:all`

---

### TASK-035.1.2 — Tipar explicitamente ChatNewMessageEvent com campos de arquivo

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Adicionar tipagem explícita à interface `ChatNewMessageEvent` para incluir os campos de arquivo (`file_name`, `file_url`, `mime_type`, `file_size`), eliminando a dependência do index signature `[key: string]: unknown`. Isso garante autocomplete, validação em compile time e previne regressões.

**Constraints**

- Nenhum `any` ou `unknown` em campos tipados
- Manter compatibilidade com campos existentes
- jsDoc obrigatório na interface

**Context**

- Módulos afetados: Chat (Frontend)
- Dependências: TASK-035.1.1 (consumidor dos tipos)

**Context References**

- `app/src/app/core/services/chat-realtime.events.ts` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code (chat-realtime.events.ts, lines 35-46)
export interface ChatNewMessageEvent {
  ticket_id: string;
  message: {
    id: string;
    content?: string;
    type?: string;
    direction?: string;
    status?: string;
    created_at?: string;
    [key: string]: unknown;
  };
}
```

```typescript
// Expected code — adicionar campos explícitos de arquivo
/** Evento emitido via WebSocket quando uma nova mensagem é recebida no chat. */
export interface ChatNewMessageEvent {
  ticket_id: string;
  message: {
    id: string;
    content?: string;
    type?: string;
    direction?: string;
    status?: string;
    created_at?: string;
    /** URL do arquivo de mídia. */
    file_url?: string | null;
    /** Nome original do arquivo. */
    file_name?: string | null;
    /** Tipo MIME do arquivo (ex: image/jpeg, application/pdf). */
    mime_type?: string | null;
    /** Tamanho do arquivo em bytes. */
    file_size?: number | null;
    /** ID externo do provider (Z-API/UAZAPI). */
    external_id?: string | null;
    /** Transcrição de mídia (áudio/imagem). */
    media_transcription?: string | null;
    /** Status da transcrição de mídia. */
    media_transcription_status?: string | null;
    /** Reações na mensagem. */
    reactions?: unknown[];
    /** Se a mensagem foi editada. */
    is_edited?: boolean;
    /** Mensagem citada/respondida. */
    quoted_message?: Record<string, unknown>;
    [key: string]: unknown;
  };
}
```
</details>

**Etapas**

- [ ] 1. Editar `app/src/app/core/services/chat-realtime.events.ts` — tipar `ChatNewMessageEvent.message` com campos de arquivo
- [ ] 2. Verificar `cd app && pnpm run gate:all`

**Critérios de conclusão**

- [ ] Interface tipada com todos os campos de arquivo
      -> Compile-time validation
- [ ] Nenhum uso de `any` adicionado
      -> `gate:all` (lint)

---

## Entrega 2 — Gateway: Z-API fileName para todos os tipos de mídia ✅ testável

**Entrega:** Webhook de Z-API extrai fileName para image, video e audio (não apenas document) | **Agente:** @DEV

**Gate:** `cd gateway && pnpm lint && pnpm test` — lint + testes passando.

### TASK-035.2.1 — Extrair fileName de image/video/audio no Z-API normalizer

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Corrigir o método `extractContent()` em `zapi.normalizer.ts` para extrair `fileName` de todos os tipos de mídia (image, video, audio, sticker), não apenas de `document`. Atualmente, o `fileName` só é incluído no retorno quando o payload é `document`.

**Constraints**

- Manter compatibilidade com payloads existentes (campo pode não existir em webhooks antigos)
- Seguir o padrão já usado pelo UAZAPI normalizer como referência
- Não quebrar testes existentes do gateway

**Context**

- Módulos afetados: Gateway (Chat providers)
- Dependências: Nenhuma
- Referência: `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` (linhas 88-93) — extrai fileName corretamente

**Context References**

- `gateway/src/domains/chat/providers/zapi/zapi.normalizer.ts` _(required in context)_
- `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` _(referência de padrão)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code (zapi.normalizer.ts, extractContent method)
// IMAGES — NO fileName extraction
if (payload.image) {
  return {
    mediaUrl: payload.image.imageUrl,
    caption: payload.image.caption,
    mimeType: payload.image.mimeType,
    // ❌ fileName ausente
  };
}

// VIDEOS — NO fileName extraction
if (payload.video) {
  return {
    mediaUrl: payload.video.videoUrl,
    // ❌ fileName ausente
  };
}

// AUDIO — NO fileName extraction
if (payload.audio) {
  return {
    mediaUrl: payload.audio.audioUrl,
    // ❌ fileName ausente
  };
}
```

```typescript
// Expected code — adicionar fileName em todos os tipos de mídia
if (payload.image) {
  return {
    mediaUrl: payload.image.imageUrl,
    caption: payload.image.caption,
    mimeType: payload.image.mimeType,
    fileName: payload.image.fileName,  // ✅ Adicionado
  };
}

if (payload.video) {
  return {
    mediaUrl: payload.video.videoUrl,
    caption: payload.video.caption,
    mimeType: payload.video.mimeType,
    fileName: payload.video.fileName,  // ✅ Adicionado
  };
}

if (payload.audio) {
  return {
    mediaUrl: payload.audio.audioUrl,
    mimeType: payload.audio.mimeType,
    fileName: payload.audio.fileName,  // ✅ Adicionado
  };
}
```
</details>

**Etapas**

- [ ] 1. Editar `gateway/src/domains/chat/providers/zapi/zapi.normalizer.ts` — método `extractContent()` — adicionar `fileName` nos retornos de image, video, audio e sticker (se aplicável)
- [ ] 2. Verificar que campos `caption` e `mimeType` que possam estar faltando em video/audio também sejam incluídos para consistência
- [ ] 3. Escrever/atualizar teste unitário para o normalizer cobrindo cada tipo de mídia
- [ ] 4. Verificar `cd gateway && pnpm lint && pnpm test`

**Critérios de conclusão**

- [ ] Webhook Z-API com image extrai fileName
      -> `test_zapi_normalizer_extracts_filename_from_image`
- [ ] Webhook Z-API com video extrai fileName
      -> `test_zapi_normalizer_extracts_filename_from_video`
- [ ] Webhook Z-API com audio extrai fileName
      -> `test_zapi_normalizer_extracts_filename_from_audio`
- [ ] Webhook Z-API com document continua extraindo fileName (não regressão)
      -> `test_zapi_normalizer_extracts_filename_from_document`
- [ ] Webhook sem fileName não quebra (undefined aceitável)
      -> `test_zapi_normalizer_handles_missing_filename`

---

## Entrega 3 — Backend: SendMessageTool com parâmetros de arquivo ✅ testável

**Entrega:** AI pode enviar mensagens com metadados de arquivo preservados | **Agente:** @BACKEND

**Gate:** `cd api && composer gate:all` — PHPStan + testes passando.

### TASK-035.3.1 — Adicionar parâmetros de arquivo no SendMessageTool

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Atualizar o `SendMessageTool.php` para:
1. Declarar parâmetros `file_url`, `file_name`, `mime_type`, `file_size` em `getParameters()`
2. Extrair esses parâmetros em `handle()` e passá-los ao `ChatMessageDTO::fromArray()`

Atualmente, quando a IA chama a tool `send_message` com tipo `image`/`document`/`audio`, os metadados do arquivo são silenciosamente descartados.

**Constraints**

- `declare(strict_types=1)` obrigatório
- phpDoc em métodos públicos
- `final class` mantido
- Parâmetros devem ser opcionais (não quebrar chamadas existentes)
- Manter `$this->authorize()` pattern se existir

**Context**

- Módulos afetados: Ai (Backend)
- Dependências: Nenhuma
- `ChatMessageDTO::fromArray()` já suporta todos os campos

**Context References**

- `api/src/Domain/Ai/Tools/SendMessageTool.php` _(required in context)_
- `api/src/Domain/Chat/DTOs/ChatMessageDTO.php` _(referência — fromArray suporta file_name)_

**Code Context**

<details>
<summary>Current → Expected (getParameters)</summary>

```php
// Current code — apenas 3 parâmetros
public function getParameters(): array
{
    return [
        'ticket_id' => ['type' => 'string', 'required' => true, ...],
        'content'   => ['type' => 'string', 'required' => true, ...],
        'type'      => ['type' => 'string', 'required' => false, ...],
    ];
}
```

```php
// Expected code — adicionar parâmetros de arquivo
public function getParameters(): array
{
    return [
        'ticket_id' => ['type' => 'string', 'required' => true, ...],
        'content'   => ['type' => 'string', 'required' => true, ...],
        'type'      => ['type' => 'string', 'required' => false, ...],
        'file_url'  => [
            'type' => 'string',
            'required' => false,
            'description' => 'URL or path to the media file attachment',
        ],
        'file_name' => [
            'type' => 'string',
            'required' => false,
            'description' => 'Original filename of the attachment',
        ],
        'mime_type' => [
            'type' => 'string',
            'required' => false,
            'description' => 'MIME type of the file (e.g., image/jpeg, application/pdf)',
        ],
        'file_size' => [
            'type' => 'integer',
            'required' => false,
            'description' => 'File size in bytes',
        ],
    ];
}
```
</details>

<details>
<summary>Current → Expected (handle)</summary>

```php
// Current code — sem campos de arquivo
$dto = ChatMessageDTO::fromArray([
    'ticket_id' => $ticket->id,
    'content' => trim($content),
    'type' => $type,
    'direction' => 'outgoing',
    'is_from_contact' => false,
    'source' => 'ai',
]);
```

```php
// Expected code — com campos de arquivo
$dto = ChatMessageDTO::fromArray([
    'ticket_id' => $ticket->id,
    'content' => trim($content),
    'type' => $type,
    'direction' => 'outgoing',
    'is_from_contact' => false,
    'source' => 'ai',
    'file_url' => $input->parameters['file_url'] ?? null,
    'file_name' => $input->parameters['file_name'] ?? null,
    'mime_type' => $input->parameters['mime_type'] ?? null,
    'file_size' => isset($input->parameters['file_size']) ? (int) $input->parameters['file_size'] : null,
]);
```
</details>

**Etapas**

- [ ] 1. Editar `api/src/Domain/Ai/Tools/SendMessageTool.php` — `getParameters()` — adicionar file_url, file_name, mime_type, file_size
- [ ] 2. Editar `api/src/Domain/Ai/Tools/SendMessageTool.php` — `handle()` — extrair parâmetros e passar ao fromArray
- [ ] 3. Verificar `cd api && composer gate:all`

**Critérios de conclusão**

- [ ] SendMessageTool aceita parâmetros de arquivo
      -> `test_send_message_tool_parameters_include_file_fields`
- [ ] SendMessageTool preserva file_name ao criar mensagem
      -> `test_send_message_tool_creates_message_with_file_metadata`
- [ ] Chamada sem file params não quebra (backwards compatible)
      -> `test_send_message_tool_works_without_file_params`

---

### TASK-035.3.2 — Normalizar file params no tool-executor do Gateway

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Atualizar o método `normalizeSendMessageArgs()` no `tool-executor.service.ts` do Gateway para incluir normalização de campos de arquivo quando o AI chama a tool `send_message` com parâmetros de mídia. Atualmente o método só normaliza o campo `content`.

**Constraints**

- Manter backward compatibility
- Logger por service
- Não adicionar lógica de negócio complexa — apenas normalização de nomes de campo

**Context**

- Módulos afetados: Gateway (AI)
- Dependências: TASK-035.3.1 (API precisa aceitar os params)

**Context References**

- `gateway/src/domains/ai/services/tool-executor.service.ts` _(required in context)_

**Etapas**

- [ ] 1. Editar `gateway/src/domains/ai/services/tool-executor.service.ts` — `normalizeSendMessageArgs()` — adicionar normalização de file_url, file_name, mime_type, file_size (aceitar tanto camelCase quanto snake_case)
- [ ] 2. Verificar `cd gateway && pnpm lint && pnpm test`

**Critérios de conclusão**

- [ ] Tool executor normaliza file_name/fileName corretamente
      -> `test_normalize_send_message_args_includes_file_params`
- [ ] Chamada sem file params não quebra
      -> `test_normalize_send_message_args_without_file_params`

---

### TASK-035.3.3 — Testes de integração do fluxo AI → Chat Message com arquivo

**Status:** todo

**Plano origem:** PLAN-023-bugfix-file-name-recebimento

**Goal**

Escrever testes feature/integração que validem o fluxo completo: AI tool call com parâmetros de arquivo → ChatMessageDTO → ChatMessage salvo com file_name na tabela extended.

**Constraints**

- Usar Pest para testes backend
- Tenant isolation obrigatório nos testes
- Não usar `$guarded = []`

**Context**

- Módulos afetados: Ai + Chat (Backend)
- Dependências: TASK-035.3.1

**Context References**

- `api/src/Domain/Ai/Tools/SendMessageTool.php` _(required in context)_
- `tests/Feature/` _(padrão de testes existentes)_

**Etapas**

- [ ] 1. Criar teste `tests/Feature/AiSendMessageToolFileTest.php` com cenários:
  - AI envia imagem com file_name → verifica chat_messages_extended.file_name
  - AI envia documento com file_url + file_name → verifica persistência
  - AI envia texto sem arquivo → verifica que extended não tem file_name
- [ ] 2. Verificar `cd api && composer gate:all`

**Critérios de conclusão**

- [ ] Teste de imagem com file_name passa
      -> `test_ai_sends_image_with_file_name_persists_to_extended`
- [ ] Teste de documento com metadados completos passa
      -> `test_ai_sends_document_with_full_metadata`
- [ ] Teste de texto sem arquivo passa
      -> `test_ai_sends_text_without_file_does_not_create_extended`

---

## Notas

- `TASK-035.{entrega}.{seq}` — primeiro número = plano 035
- **Entregas 1, 2 e 3 podem ser executadas EM PARALELO** — são camadas independentes (Frontend, Gateway, Backend)
- **Entrega 1** é a causa raiz principal do bug reportado — priorizar
- **Entrega 2** melhora a qualidade dos dados no recebimento via Z-API
- **Entrega 3** habilita o fluxo AI → arquivo, que é um cenário complementar
- O UAZAPI normalizer já funciona corretamente e serve como referência
- O schema do banco (chat_messages_extended) está correto — não precisa de migração
