# FEAT-044 — Upload de Arquivos no Chat Externo (Webchat)

## Metadados

| Campo                  | Valor                                 |
| ---------------------- | ------------------------------------- |
| **ID**                 | FEAT-044                              |
| **Nome**               | Upload de arquivos no webchat externo |
| **Bounded Context**    | Chat                                  |
| **Complexidade**       | M                                     |
| **Prioridade**         | Must                                  |
| **Status**             | 🟡 Em Planning                        |
| **Criada em**          | 2026-04-21                            |
| **Última atualização** | 2026-04-21                            |

---

## Resumo

Habilitar o envio de arquivos (imagens, documentos, vídeos e áudios) pelos visitantes no webchat público. O botão de anexo já existe na UI (`AfChatComposerComponent`) mas não realiza nenhuma ação — a feature toda está ausente nas camadas de service (Angular), controller (Laravel) e rota (webchat.php).

---

## Diagnóstico — Root Cause

A investigação identificou **5 gaps simultâneos** que formam a cadeia de falha:

| #   | Camada               | Arquivo                      | Gap                                                                   |
| --- | -------------------- | ---------------------------- | --------------------------------------------------------------------- |
| 1   | Frontend — UI        | `chat-window.component.html` | `(attachmentTypeSelected)` não está vinculado no `<af-chat-composer>` |
| 2   | Frontend — Service   | `webchat.service.ts`         | Sem método `uploadMedia()` nem `sendFileMessage()`                    |
| 3   | Frontend — Model     | `webchat.model.ts`           | `WebChatMessageRequest` sem campos `file_url`, `mime_type`, `type`    |
| 4   | Backend — Rota       | `webchat.php`                | Sem rota `POST /api/webchat/media`                                    |
| 5   | Backend — Controller | `WebChatMessageController`   | `store()` aceita apenas `content` (texto)                             |

> **O que JÁ existe e pode ser reutilizado:**
>
> - `WebChatJwtService::validateToken()` — validação de JWT da sessão (mesmo padrão do `WebChatMessageController`)
> - `ChatMessage` model — colunas `file_url`, `mime_type`, `type` já existem no schema
> - `AfChatComposerComponent` — botão de anexo + dropdown de tipos já funcional (outputs `attachmentClicked` / `attachmentTypeSelected`)
> - `ChatMediaController` — upload autenticado (usuários internos) como referência de implementação

---

## Objetivo

Permitir que visitantes do webchat público enviem arquivos (imagens, documentos, vídeos, áudios) dentro de um atendimento ativo, de forma segura e consistente com o padrão já estabelecido nas demais rotas públicas do webchat (auth via JWT de sessão, rate-limited, sem Sanctum).

---

## Escopo

### Dentro do Escopo ✅

- [ ] Endpoint público `POST /api/webchat/media` autenticado pelo token JWT de sessão
- [ ] Validação de arquivo: max 10 MB, tipos aceitos (`image/*`, `video/*`, `audio/*`, `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.*`)
- [ ] `WebChatMessageController::store()` aceitar `file_url`, `mime_type` e `type` para criar mensagens de mídia
- [ ] `WebChatService.uploadMedia()` — upload multipart para o novo endpoint
- [ ] `WebChatService.sendFileMessage()` — envia mensagem com `file_url` após upload
- [ ] Vinculação do output `(attachmentTypeSelected)` em `ChatWindowComponent` — abre file picker nativo, faz upload e envia mensagem
- [ ] Feedback visual de progresso/erro durante upload
- [ ] Testes backend (Pest) para `WebChatMediaController`
- [ ] Testes frontend (Vitest) para `WebChatService` — métodos de upload

### Fora do Escopo ❌

- Prévia de imagem na bolha de mensagem (renderização de mídia inline) — iteração futura
- Envio de múltiplos arquivos simultâneos
- Limites de storage por plano para visitantes webchat (já existe para usuários internos)
- Compressão de imagem no frontend antes do upload
- Progresso de upload com porcentagem (usar indicador simples de loading)

---

## Dependências

| Feature/Sistema                    | Tipo            | Status                 | Blocker |
| ---------------------------------- | --------------- | ---------------------- | ------- |
| FEAT-040 Webchat Widget            | É bloqueado por | Concluída              | Não     |
| FEAT-042 Encerramento pelo cliente | É bloqueado por | Concluída              | Não     |
| `storage/public` configurado       | Runtime         | Deve estar configurado | Sim     |

---

## Critérios de Aceite

| ID     | Critério                                                                                | Status |
| ------ | --------------------------------------------------------------------------------------- | ------ |
| CA-001 | Visitante clica no botão de anexo → abre menu com tipos (Documento, Foto, Vídeo, Áudio) | ❌     |
| CA-002 | Ao selecionar um tipo, abre file picker nativo filtrado pelo tipo correto               | ❌     |
| CA-003 | Arquivo válido é enviado via `POST /api/webchat/media` com token JWT                    | ❌     |
| CA-004 | Mensagem do tipo correto (`image`, `document`, `video`, `audio`) aparece na conversa    | ❌     |
| CA-005 | Arquivo > 10 MB exibe erro sem enviar                                                   | ❌     |
| CA-006 | Token inválido ou expirado retorna 401 no endpoint de upload                            | ❌     |
| CA-007 | Upload com tipo MIME não permitido retorna 422                                          | ❌     |
| CA-008 | Testes Pest cobrem o novo controller (happy path + erros)                               | ❌     |
| CA-009 | Testes Vitest cobrem `uploadMedia()` e `sendFileMessage()`                              | ❌     |

---

## Arquitetura da Solução

### Fluxo de Upload

```
Visitante seleciona arquivo
        ↓
ChatWindowComponent.onAttachmentSelected(type)
        ↓
file picker nativo (input[type=file])
        ↓
WebChatService.uploadMedia(file, token)
  POST /api/webchat/media  (multipart/form-data)
        ↓
WebChatMediaController.store()
  → validateToken(JWT)
  → file validation
  → Storage::disk('public')->put(...)
  → return { url, fileName, mimeType, size }
        ↓
WebChatService.sendFileMessage(sessionId, fileUrl, mimeType, messageType)
  POST /api/webchat/messages  { token, file_url, mime_type, type }
        ↓
WebChatMessageController.store() — cria ChatMessage tipo mídia
```

### Novos arquivos a criar

| Arquivo                                                           | Camada         |
| ----------------------------------------------------------------- | -------------- |
| `api/src/Domain/Chat/Http/Controllers/WebChatMediaController.php` | Backend        |
| `api/src/Domain/Chat/Http/Requests/WebChatMediaStoreRequest.php`  | Backend        |
| `tests/Feature/Chat/WebChatMediaControllerTest.php`               | Testes backend |

### Arquivos a modificar

| Arquivo                                                                       | Mudança                                                 |
| ----------------------------------------------------------------------------- | ------------------------------------------------------- |
| `api/src/Domain/Chat/Routes/webchat.php`                                      | Adicionar `POST /webchat/media`                         |
| `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`           | Aceitar `file_url`, `mime_type`, `type`                 |
| `app/src/app/pages/webchat/webchat.model.ts`                                  | Adicionar campos opcionais em `WebChatMessageRequest`   |
| `app/src/app/pages/webchat/services/webchat.service.ts`                       | Métodos `uploadMedia()` e `sendFileMessage()`           |
| `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`   | Handler `onAttachmentSelected()`                        |
| `app/src/app/pages/webchat/components/chat-window/chat-window.component.html` | Vincular `(attachmentTypeSelected)` e input file oculto |

---

## Decisões de Design

| Decisão                    | Escolha                                                     | Motivo                                                                                  |
| -------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Auth do endpoint de upload | JWT de sessão webchat (mesmo padrão de `/webchat/messages`) | Consistência com o padrão estabelecido (MEMORY: 2026-04-21-webchat-client-close-ticket) |
| Storage                    | `disk('public')` em `chat/webchat/{tenantId}/`              | Consistência com upload interno; URLs públicas sem signed link                          |
| Rate limit                 | `throttle:webchat` (60 req/min por IP)                      | Reaproveitar limiter já configurado                                                     |
| Tamanho máximo             | 10 MB                                                       | Dobro do limite interno (5 MB) para cobrir documentos maiores enviados por visitantes   |
| Tipos aceitos              | imagem, vídeo, áudio, PDF, Word/Excel                       | Tipos de uso comum em atendimento; rejeitar executáveis e tipos desconhecidos           |
