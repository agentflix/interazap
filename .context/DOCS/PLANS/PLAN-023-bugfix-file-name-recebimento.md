# PLAN-023-bugfix-file-name-recebimento — Correção de file_name no recebimento de arquivos

## Objetivo

Corrigir o bug onde o `file_name` de arquivos recebidos (incoming) não é preservado/exibido corretamente no chat, embora ao enviar arquivos (outgoing) o nome seja salvo normalmente. A investigação revelou **3 pontos de falha** no fluxo de recebimento que operam em camadas distintas.

## Módulo relacionado

Chat | Gateway | Ai

## PRD relacionado (se existir): N/A (bugfix)

## Escopo

### Incluído

- Correção do mapeamento de propriedades de arquivo no WebSocket handler do Frontend (causa raiz principal)
- Correção da extração de `fileName` no normalizador Z-API do Gateway para todos os tipos de mídia
- Adição de parâmetros de arquivo no `SendMessageTool` do Backend (AI)
- Testes unitários para cada correção
- Tipagem explícita do evento WebSocket no Frontend

### Excluído

- Refatoração da god-class `chat.ts` (PLAN-020 separado)
- Alterações na tabela `chat_messages_extended` (schema está correto)
- Modificações no UAZAPI normalizer (já funciona corretamente)
- Upload de arquivos pelo usuário (fluxo de envio já funciona)

## Análise de Causa Raiz

### Fluxo de ENVIO (funciona ✅)

```
Frontend (file.name) → API (ChatMessageDTO.fileName) → chat_messages_extended.file_name → Resource.file_name
```

### Fluxo de RECEBIMENTO (3 pontos de falha ❌)

```
WhatsApp → Z-API webhook
  → [BUG 2] Gateway Z-API normalizer: NÃO extrai fileName para image/video/audio
  → Redis Stream → API ChatWebhookIngestor (extrai fileName, fallback genérico)
  → chat_messages_extended.file_name (salvo como "file.jpg" genérico ou null)
  → WebSocket event → Frontend
  → [BUG 1] user-chat-thread.store.ts: NÃO mapeia file_name/file_url/mime_type do evento
  → Mensagem renderizada sem nome de arquivo

AI Tool Response:
  → [BUG 3] SendMessageTool.php: NÃO aceita parâmetros file_name/file_url/mime_type
  → ChatMessageDTO criado sem metadados de arquivo
  → Mensagem salva sem file_name
```

## Evidências da Codebase

### Frontend (Chat)

- `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.store.ts` (linhas 336-344) — `handleRealtimeNewMessage()` mapeia apenas 7 campos, ignora file_name/file_url/mime_type/file_size
- `app/src/app/core/services/called-message.service.ts` (linhas 61-93) — Interface `CalledMessage` já suporta file_name, file_url, mime_type, file_size
- `app/src/app/pages/chat/components/user-chat-thread/user-chat-message-bubble.component.ts` (linha 49) — Componente usa `file_name` para exibir nome quando não há texto
- `app/src/app/pages/chat/components/chat-message-media/chat-message-media.component.ts` (linha 415) — Componente espera `fileName` como Input
- `app/src/app/pages/chat/store/chat.store.ts` (linhas 379-383) — Status updates JÁ preservam file_name corretamente (modelo a seguir)

### Gateway (Z-API)

- `gateway/src/domains/chat/providers/zapi/zapi.normalizer.ts` (linhas 193-214) — `extractContent()` só extrai `fileName` para `document`, ignora image/video/audio
- `gateway/src/domains/chat/providers/uazapi/uazapi.provider.ts` (linhas 88-93) — UAZAPI extrai fileName para todos os tipos ✅ (referência)
- `gateway/src/domains/chat/contracts/normalized-event.dto.ts` (linha 71) — DTO já suporta `fileName?: string`

### Backend (AI Tools)

- `api/src/Domain/Ai/Tools/SendMessageTool.php` — `getParameters()` não define file_name/file_url/mime_type/file_size; `handle()` não extrai esses parâmetros
- `api/src/Domain/Chat/DTOs/ChatMessageDTO.php` — DTO já suporta todos os campos de arquivo via `fromArray()`
- `api/src/Domain/Chat/Actions/ChatMessageActions.php` (linhas 326-359) — `create()` já salva file_name no extended ✅

### Shared Components

- `ChatMessageResource.php` (linha 58) — Resource já retorna `file_name` na resposta da API ✅
- `ChatWebhookPayloadExtractor.php` (linha 98) — Extrai fileName com fallback ✅

## Etapas propostas

1. **[Frontend]** Adicionar mapeamento de propriedades de arquivo em `handleRealtimeNewMessage()`
2. **[Frontend]** Tipar explicitamente a interface `ChatNewMessageEvent` com campos de arquivo
3. **[Gateway]** Adicionar extração de `fileName` em `extractContent()` para image, video e audio no Z-API normalizer
4. **[Backend]** Adicionar parâmetros de arquivo no `SendMessageTool.getParameters()`
5. **[Backend]** Extrair parâmetros de arquivo no `SendMessageTool.handle()` e passar ao DTO
6. **[Gateway]** Adicionar normalização de file params no `tool-executor.service.ts` para send_message
7. **Testes** — Cobrir cada cenário com testes unitários/feature

## Entregas derivadas

**Entregas:** 3 | **Tasks:** 6

| Entrega | Descrição | Tasks | Esforço | Status |
|---------|-----------|-------|---------|--------|
| 1 | Frontend: Mapeamento WebSocket + Tipagem | TASK-035.1.1, TASK-035.1.2 | S | todo |
| 2 | Gateway: Z-API fileName para todos os tipos de mídia | TASK-035.2.1 | XS | todo |
| 3 | Backend: SendMessageTool com parâmetros de arquivo | TASK-035.3.1, TASK-035.3.2, TASK-035.3.3 | S | todo |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Z-API não envia fileName para image/audio/video | Média | Médio | API já tem fallback genérico em ChatWebhookIngestor; melhorar fallback com timestamp |
| Quebra de contrato WebSocket ao adicionar campos | Baixa | Baixo | Campos são opcionais (null safety) |
| AI model não usar parâmetros de arquivo | Baixa | Baixo | Parâmetros são opcionais; fluxo existente não quebra |

### Dependências

- Nenhuma dependência externa. As 3 entregas podem ser executadas **em paralelo** (camadas independentes).

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Baixa |
| Camadas afetadas | Frontend / Gateway / Backend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Não (apenas correção de mapeamento) |
