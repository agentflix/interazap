# Tasks: Upload de Arquivos no Chat Externo (Webchat)

> Decomposição T.A.C.E das tasks da feature FEAT-044

---

## Feature: Webchat File Upload

**ID:** FEAT-044
**Bounded Context:** Chat
**Total Tasks:** 9
**Concluídas:** 0

---

## 📋 Sumário das Tasks

| Task       | Camada   | Título                                                              | Status      |
| ---------- | -------- | ------------------------------------------------------------------- | ----------- |
| TASK-044.1 | Backend  | Criar `WebChatMediaStoreRequest`                                    | ⏳ Pendente |
| TASK-044.2 | Backend  | Criar `WebChatMediaController`                                      | ⏳ Pendente |
| TASK-044.3 | Backend  | Registrar rota `POST /webchat/media`                                | ⏳ Pendente |
| TASK-044.4 | Backend  | Atualizar `WebChatMessageController` para aceitar mídia             | ⏳ Pendente |
| TASK-044.5 | Backend  | Testes Pest para `WebChatMediaController`                           | ⏳ Pendente |
| TASK-044.6 | Frontend | Atualizar `webchat.model.ts` — request com campos de mídia          | ⏳ Pendente |
| TASK-044.7 | Frontend | Adicionar `uploadMedia()` e `sendFileMessage()` em `WebChatService` | ⏳ Pendente |
| TASK-044.8 | Frontend | Vincular upload em `ChatWindowComponent`                            | ⏳ Pendente |
| TASK-044.9 | Frontend | Testes Vitest para `WebChatService`                                 | ⏳ Pendente |

---

## 🔄 FASE BACKEND — Execution (BACKEND agent)

---

### TASK-044.1 ⏳ — Criar `WebChatMediaStoreRequest`

**T — Tarefa:** Criar Form Request de validação para upload de arquivo via webchat público.

**A — Arquivo:**

- **Criar:** `api/src/Domain/Chat/Http/Requests/WebChatMediaStoreRequest.php`

**C — Comportamento:**

```
ANTES:
- Não existe validação específica para upload público de webchat.

DEPOIS:
- authorize() retorna true sem Sanctum (público, autenticado via JWT no controller)
- rules() valida:
    - 'token' => required, string
    - 'file'  => required, file, max:10240 (10 MB)
               mimes: jpg, jpeg, png, gif, webp, mp4, mov, avi,
                      mp3, ogg, wav, pdf, doc, docx, xls, xlsx
- messages() em PT-BR
```

**E — Evidência:**

- [ ] Arquivo criado em `api/src/Domain/Chat/Http/Requests/WebChatMediaStoreRequest.php`
- [ ] `authorize()` retorna `true` (auth é responsabilidade do controller via JWT)
- [ ] Arquivo > 10 MB falha na validação com `max:10240`
- [ ] Tipo não permitido (ex: `.exe`) falha com `mimes` rule
- [ ] Mensagens de erro em PT-BR

**Dependências:** Nenhuma

**Status:** ⏳ Pendente

---

### TASK-044.2 ⏳ — Criar `WebChatMediaController`

**T — Tarefa:** Criar controller público para upload de arquivos do webchat, autenticado via JWT de sessão.

**A — Arquivo:**

- **Criar:** `api/src/Domain/Chat/Http/Controllers/WebChatMediaController.php`

**C — Comportamento:**

```
ANTES:
- Endpoint de upload exige Sanctum + permissões de usuário interno.
- Visitantes webchat não conseguem fazer upload.

DEPOIS:
- POST /api/webchat/media (multipart/form-data)
  Payload: { token: string, file: File }

  1. Valida request via WebChatMediaStoreRequest
  2. Valida JWT token via WebChatJwtService::validateToken(token)
     → 401 se inválido ou expirado
  3. Extrai tenant_id do payload JWT
  4. Verifica que sessão está ativa (ChatSession where id = session_id, tenant_id)
     → 404 se não encontrada
  5. Armazena arquivo em Storage::disk('public')
     path: "chat/webchat/{tenant_id}/{uuid}.{ext}"
  6. Retorna:
     {
       url: "https://host/storage/chat/webchat/{tenant_id}/{uuid}.{ext}",
       file_name: string,
       mime_type: string,
       size: int
     }
```

**E — Evidência:**

- [ ] Arquivo criado em `api/src/Domain/Chat/Http/Controllers/WebChatMediaController.php`
- [ ] Token inválido retorna 401
- [ ] Sessão não encontrada retorna 404
- [ ] Upload válido retorna 201 com `url`, `file_name`, `mime_type`, `size`
- [ ] Arquivo armazenado em `storage/app/public/chat/webchat/{tenant_id}/`
- [ ] Sem dependência de Sanctum ou políticas de usuário interno

**Dependências:** TASK-044.1

**Status:** ⏳ Pendente

---

### TASK-044.3 ⏳ — Registrar rota `POST /webchat/media`

**T — Tarefa:** Adicionar rota pública de upload ao grupo webchat em `webchat.php`.

**A — Arquivo:**

- **Modificar:** `api/src/Domain/Chat/Routes/webchat.php`

**C — Comportamento:**

```php
// ANTES:
Route::middleware(['throttle:webchat'])->group(function (): void {
    Route::get('/webchat/health', ...);
    Route::post('/webchat/sessions', ...);
    Route::get('/webchat/sessions/{id}', ...);
    Route::post('/webchat/messages', ...);
    Route::post('/webchat/close', ...);
});

// DEPOIS: adicionar dentro do grupo throttle:webchat
    Route::post('/webchat/media', [WebChatMediaController::class, 'store']);
```

**E — Evidência:**

- [ ] Rota registrada dentro do grupo `throttle:webchat`
- [ ] `use Domain\Chat\Http\Controllers\WebChatMediaController;` adicionado ao topo
- [ ] `php artisan route:list | grep webchat/media` exibe a rota

**Dependências:** TASK-044.2

**Status:** ⏳ Pendente

---

### TASK-044.4 ⏳ — Atualizar `WebChatMessageController` para aceitar mídia

**T — Tarefa:** Estender o controller de mensagens webchat para receber mensagens do tipo mídia (com `file_url`, `mime_type` e `type`).

**A — Arquivo:**

- **Modificar:** `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`

**C — Comportamento:**

```
ANTES:
- validateStoreRequest() aceita apenas 'content' (obrigatório, não vazio)
- ChatMessage criado com type='text', content obrigatório

DEPOIS:
- Aceitar dois modos mutuamente exclusivos:
  Modo texto: { token, content }
  Modo mídia: { token, file_url, mime_type, type }

- validateStoreRequest():
  - 'content' OU 'file_url' deve estar presente (não ambos vazios)
  - Se 'file_url' presente: validar que 'mime_type' e 'type' também existem
  - 'type' deve ser: 'image' | 'video' | 'audio' | 'document'

- ChatMessage criado com:
  - type = $validated['type'] (ou 'text' se modo texto)
  - content = $validated['content'] ?? ''   (vazio para mídia)
  - file_url = $validated['file_url'] ?? null
  - mime_type = $validated['mime_type'] ?? null

- ChatAutopilotResponder::respond() chamado APENAS para mensagens de texto
  (IA não responde a arquivos nesta iteração)
```

**E — Evidência:**

- [ ] Mensagem de texto continua funcionando sem alteração (retrocompatível)
- [ ] Envio com `file_url` + `mime_type` + `type` cria ChatMessage com tipo correto
- [ ] ChatMessage com `type=image` persiste `file_url` e `mime_type` no banco
- [ ] Autopilot não é disparado para mensagens de mídia
- [ ] Retorno mantém `{ messageId }` para ambos os modos

**Dependências:** TASK-044.3

**Status:** ⏳ Pendente

---

### TASK-044.5 ⏳ — Testes Pest para `WebChatMediaController`

**T — Tarefa:** Escrever testes de feature cobrindo o novo endpoint de upload.

**A — Arquivo:**

- **Criar:** `api/tests/Feature/Chat/WebChatMediaControllerTest.php`

**C — Comportamento:**

```
Cenários obrigatórios:
1. upload_válido_retorna_201_com_url
   → token JWT válido, imagem PNG < 10 MB → 201 { url, file_name, mime_type, size }

2. token_inválido_retorna_401
   → token malformado → 401

3. token_expirado_retorna_401
   → token com exp no passado → 401

4. sessão_não_encontrada_retorna_404
   → token válido mas session_id não existe no DB → 404

5. arquivo_muito_grande_retorna_422
   → arquivo > 10 MB → 422

6. tipo_mime_não_permitido_retorna_422
   → arquivo .exe → 422

7. sem_arquivo_retorna_422
   → payload sem 'file' → 422
```

**E — Evidência:**

- [ ] Arquivo criado em `api/tests/Feature/Chat/WebChatMediaControllerTest.php`
- [ ] Todos os 7 cenários passam (`./vendor/bin/pest tests/Feature/Chat/WebChatMediaControllerTest.php`)
- [ ] Testes usam `Storage::fake('public')` para não gravar em disco real
- [ ] Testes usam `UploadedFile::fake()` do Laravel

**Dependências:** TASK-044.2, TASK-044.3

**Status:** ⏳ Pendente

---

## 🔄 FASE FRONTEND — Execution (FRONTEND agent)

---

### TASK-044.6 ⏳ — Atualizar `webchat.model.ts` com campos de mídia

**T — Tarefa:** Estender `WebChatMessageRequest` e adicionar interfaces de resposta de upload.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/webchat.model.ts`

**C — Comportamento:**

```typescript
// ANTES:
export interface WebChatMessageRequest {
    token: string;
    content: string;
}

// DEPOIS:
export interface WebChatMessageRequest {
    token: string;
    content?: string; // opcional — vazio para mensagens de mídia
    file_url?: string; // URL retornada pelo endpoint de upload
    mime_type?: string; // MIME type do arquivo
    type?: WebChatMessageType; // 'text' | 'image' | 'video' | 'audio' | 'document'
}

// NOVO: resposta do endpoint de upload
export interface WebChatMediaUploadResponse {
    url: string;
    file_name: string;
    mime_type: string;
    size: number;
}

// NOVO: tipo de mensagem unificado (extrair de WebChatMessage.type)
export type WebChatMessageType = 'text' | 'image' | 'video' | 'file' | 'audio' | 'document';
```

**E — Evidência:**

- [ ] `WebChatMessageRequest` com campos opcionais corretos
- [ ] `WebChatMediaUploadResponse` exportada
- [ ] `WebChatMessageType` exportado (ou inlined)
- [ ] Sem erros TypeScript (`tsc --noEmit`)

**Dependências:** Nenhuma (pode ser paralela ao backend)

**Status:** ⏳ Pendente

---

### TASK-044.7 ⏳ — Adicionar `uploadMedia()` e `sendFileMessage()` ao `WebChatService`

**T — Tarefa:** Implementar os dois métodos de service para o fluxo de upload e envio de mensagem com mídia.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/services/webchat.service.ts`

**C — Comportamento:**

```typescript
// ANTES:
// Apenas sendMessage(sessionId, content, tempId) existe

// DEPOIS — novos métodos:

/**
 * Faz upload de um arquivo para o webchat.
 * POST /api/webchat/media
 */
uploadMedia(file: File): Observable<WebChatMediaUploadResponse> {
  const token = this.sessionToken?.trim();
  if (!token) {
    return throwError(() => new Error('Sessão inválida'));
  }
  const form = new FormData();
  form.append('token', token);
  form.append('file', file);
  return this.http.post<unknown>(`${this.apiBase}/api/webchat/media`, form).pipe(
    map((r) => this.unwrapData<WebChatMediaUploadResponse>(r)),
    catchError((err) => {
      this._error.set(err?.error?.message ?? 'Falha ao enviar arquivo');
      throw err;
    }),
  );
}

/**
 * Envia mensagem de mídia (após upload bem-sucedido).
 * POST /api/webchat/messages  { token, file_url, mime_type, type }
 */
sendFileMessage(
  sessionId: string,
  fileUrl: string,
  mimeType: string,
  messageType: WebChatMessageType,
  tempId?: string,
): Observable<WebChatMessageResponse> {
  // similar a sendMessage() mas com file_url em vez de content
}
```

**E — Evidência:**

- [ ] `uploadMedia(file)` envia `FormData` com `token` + `file`
- [ ] `sendFileMessage()` envia `{ token, file_url, mime_type, type }`
- [ ] Erro durante upload é setado em `this._error`
- [ ] Mensagem otimista é adicionada localmente com `type` correto
- [ ] Sem erros TypeScript (`tsc --noEmit`)

**Dependências:** TASK-044.6

**Status:** ⏳ Pendente

---

### TASK-044.8 ⏳ — Vincular upload em `ChatWindowComponent`

**T — Tarefa:** Implementar o handler de anexo no componente de chat, incluindo file picker nativo, chamada de upload e feedback visual.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- **Modificar:** `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`

**C — Comportamento:**

```html
<!-- ANTES: composer sem handler de anexo -->
<af-chat-composer placeholder="Digite sua mensagem..." (messageSent)="onMessageSent($event)" />

<!-- DEPOIS: composer com handler de anexo + input file oculto -->
<af-chat-composer
    placeholder="Digite sua mensagem..."
    (messageSent)="onMessageSent($event)"
    (attachmentTypeSelected)="onAttachmentSelected($event)"
    [disabled]="isUploading() || isClosed()"
/>
<input #fileInput type="file" class="hidden" [accept]="fileAccept()" (change)="onFileSelected($event)" />
```

```typescript
// ANTES: sem handler de anexo

// DEPOIS: novos membros em ChatWindowComponent
readonly isUploading = signal(false);
readonly uploadError = signal<string | null>(null);

private pendingAttachmentType = signal<AttachmentType | null>(null);

protected readonly fileAccept = computed(() => {
  switch (this.pendingAttachmentType()) {
    case 'image':    return 'image/*';
    case 'video':    return 'video/*';
    case 'audio':    return 'audio/*';
    case 'document': return '.pdf,.doc,.docx,.xls,.xlsx';
    default:         return '*/*';
  }
});

onAttachmentSelected(type: AttachmentType): void {
  if (this.isClosed() || this.isUploading()) return;
  this.pendingAttachmentType.set(type);
  this.fileInputRef().nativeElement.click();
}

onFileSelected(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  const type = this.pendingAttachmentType() ?? 'document';
  const sessionId = this.sessionId();
  if (!sessionId) return;

  this.isUploading.set(true);
  this.uploadError.set(null);

  this.webchatService.uploadMedia(file).pipe(
    switchMap((upload) =>
      this.webchatService.sendFileMessage(sessionId, upload.url, upload.mime_type, type as any),
    ),
    takeUntilDestroyed(this.destroyRef),
    finalize(() => {
      this.isUploading.set(false);
      (event.target as HTMLInputElement).value = '';
    }),
  ).subscribe({
    error: (err) => this.uploadError.set(err?.message ?? 'Falha no envio'),
  });
}
```

**E — Evidência:**

- [ ] Clicar no botão de anexo abre menu de tipos
- [ ] Selecionar tipo abre file picker nativo com filtro correto
- [ ] Arquivo válido faz upload e envia mensagem (aparece na lista)
- [ ] `isUploading()` desabilita o composer durante o upload
- [ ] Erro de upload exibe mensagem ao usuário
- [ ] `takeUntilDestroyed` garante limpeza de subscription
- [ ] Sem erros TypeScript nem ESLint

**Dependências:** TASK-044.7

**Status:** ⏳ Pendente

---

### TASK-044.9 ⏳ — Testes Vitest para `WebChatService`

**T — Tarefa:** Escrever testes unitários cobrindo os novos métodos de upload.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/services/webchat.service.spec.ts`

**C — Comportamento:**

```
Cenários obrigatórios:
1. uploadMedia_envia_FormData_com_token_e_file
   → mock HTTP POST retorna upload response → observable emite WebChatMediaUploadResponse

2. uploadMedia_sem_sessionToken_retorna_erro
   → sessionToken = null → throwError()

3. uploadMedia_erro_HTTP_seta_error_signal
   → mock retorna 422 → _error signal atualizado

4. sendFileMessage_envia_payload_correto
   → mock HTTP POST → emite WebChatMessageResponse
   → mensagem otimista adicionada ao _messages signal com type correto

5. sendFileMessage_sem_token_retorna_erro
   → sessionToken = null → throwError()
```

**E — Evidência:**

- [ ] Todos os 5 cenários passam (`npm run test -- webchat.service`)
- [ ] Mocks via `HttpClientTestingModule` + `HttpTestingController`
- [ ] Sem warnings de unsubscribe

**Dependências:** TASK-044.7

**Status:** ⏳ Pendente

---

## 📊 Ordem de Execução

```
TASK-044.1 → TASK-044.2 → TASK-044.3 → TASK-044.4
                                              ↓
                                        TASK-044.5

TASK-044.6 → TASK-044.7 → TASK-044.8
                    ↓
              TASK-044.9
```

> As duas trilhas (Backend e Frontend) são **independentes** e podem ser executadas em paralelo por agentes distintos (BACKEND e FRONTEND).

---

## ✅ Gate de Conclusão da Feature

- [ ] `./vendor/bin/pest tests/Feature/Chat/WebChatMediaControllerTest.php` — todos passam
- [ ] `npm run test -- webchat.service` — todos passam
- [ ] `tsc --noEmit` no projeto Angular sem erros
- [ ] `npm run lint` sem erros novos nos arquivos modificados
- [ ] Teste manual: selecionar imagem no webchat → aparece na conversa
- [ ] Teste manual: arquivo > 10 MB → exibe erro sem enviar
