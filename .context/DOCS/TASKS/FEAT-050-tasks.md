# Tasks: Meta WhatsApp Templates Management + 24h Window Composer

> Decomposição T.A.C.E. Feature: **FEAT-050**.
> Bounded Contexts: Chat (api), Gateway (gateway), App (frontend).

---

## Sumário

| Bloco | Escopo                                | Tasks  | Agente        |
| ----- | ------------------------------------- | ------ | ------------- |
| **A** | Backend: schema + CRUD + sync + guard | A1–A10 | DBA + BACKEND |
| **B** | Gateway: endpoints + webhook status   | B1–B6  | GATEWAY       |
| **C** | Frontend: admin de templates          | C1–C5  | FRONTEND      |
| **D** | Frontend: composer com janela         | D1–D7  | FRONTEND      |
| **E** | QA: testes + evidência                | E1–E3  | QA            |

Total: **31 tasks**.

---

## 🔧 BLOCO A — Backend (Laravel)

### TASK-050.A1 — Migration: estender `chat_message_templates`

**T:** Adicionar colunas Meta sem quebrar templates locais existentes.

**A:**

- `api/database/migrations/2026_04_30_000001_extend_chat_message_templates_for_meta.php`
- `api/src/Domain/Chat/Models/ChatMessageTemplate.php`

**C:**

```
ANTES: tabela tem id, tenant_id, name, shortcut, content, category, is_active.
DEPOIS: + chat_instance_id (uuid NULL FK), provider ('local'|'meta'),
        external_id, language, status, rejected_reason, components_json, last_synced_at.
        UNIQUE (chat_instance_id, name, language) quando chat_instance_id NOT NULL.
        Modelo expõe casts: components_json=>array, last_synced_at=>datetime.
        Templates existentes recebem provider='local', status='approved'.
```

**E:**

- [ ] `php artisan migrate` sem erro
- [ ] `ChatMessageTemplate` retorna novos campos via `->toArray()`
- [ ] Templates existentes continuam aparecendo via `list()`
- [ ] `php artisan migrate:rollback` reverte sem perda

**Agente:** DBA

---

### TASK-050.A2 — Action `SyncMetaTemplatesAction`

**T:** Buscar templates da Meta via Gateway e fazer upsert local.

**A:** `api/src/Domain/Chat/Actions/SyncMetaTemplatesAction.php`

**C:**

```
ANTES: não existe.
DEPOIS: execute(string $tenantId, string $chatInstanceId): int
  1. Busca ChatInstance (provider='meta')
  2. GET {GATEWAY_URL}/channels/{id}/templates?include_all=true (header GATEWAY_SECRET)
  3. Para cada template: upsert por (chat_instance_id, name, language)
     - mapeia status, rejected_reason, components_json, external_id
     - last_synced_at = now()
  4. Marca como 'disabled' templates locais não mais presentes na Meta
  5. Retorna count de templates sincronizados
```

**E:**

- [ ] Teste Pest com Http::fake() que retorna 3 templates
- [ ] Templates locais não-Meta não são tocados
- [ ] Idempotente (chamar 2x não duplica)

**Agente:** BACKEND

---

### TASK-050.A3 — Action `CreateMetaTemplateAction` + Job

**T:** Criar template localmente como `pending` e submeter à Meta de forma assíncrona.

**A:**

- `api/src/Domain/Chat/Actions/CreateMetaTemplateAction.php`
- `api/src/Domain/Chat/Jobs/SubmitMetaTemplateJob.php`

**C:**

```
ANTES: store() só cria registro local sem comunicar Meta.
DEPOIS:
  CreateMetaTemplateAction::execute(tenantId, chatInstanceId, dto):
    1. Cria row com status='pending', external_id=null
    2. Dispatch SubmitMetaTemplateJob
    3. Retorna o modelo
  SubmitMetaTemplateJob::handle():
    1. POST {GATEWAY_URL}/channels/{id}/templates com payload Meta
    2. Atualiza external_id e status (pode vir 'pending' ou 'approved')
    3. Em falha: marca status='rejected', rejected_reason=erro
    4. Retry: 3 tentativas, backoff exponencial
```

**E:**

- [ ] Teste com Queue::fake() valida dispatch
- [ ] Teste com Http::fake() valida payload enviado
- [ ] Falha HTTP marca como `rejected`

**Agente:** BACKEND

---

### TASK-050.A4 — Actions `Update` + `Delete` template

**T:** Atualizar (apenas templates locais; Meta não permite update — só recriar) e deletar (local + remoto se Meta).

**A:**

- `api/src/Domain/Chat/Actions/UpdateChatMessageTemplateAction.php`
- `api/src/Domain/Chat/Actions/DeleteChatMessageTemplateAction.php`

**C:**

```
ANTES: ChatMessageTemplateActions só tem list/create.
DEPOIS:
  Update: provider='local' → permitido; provider='meta' → 422 "Use delete + create".
          Apenas is_active e shortcut editáveis para Meta.
  Delete: provider='local' → soft delete local.
          provider='meta' → DELETE no Gateway; em sucesso, soft delete; em falha, status='disabled' local.
```

**E:**

- [ ] Update em template Meta retorna 422
- [ ] Delete em template Meta chama Gateway DELETE
- [ ] Soft delete preserva histórico de mensagens enviadas

**Agente:** BACKEND

---

### TASK-050.A5 — Estender controller + Resource + Request

**T:** Expor CRUD completo + sync + show.

**A:**

- `api/src/Domain/Chat/Http/Controllers/ChatMessageTemplateController.php`
- `api/src/Domain/Chat/Http/Requests/ChatMessageTemplateRequest.php`
- `api/src/Domain/Chat/Http/Resources/ChatMessageTemplateResource.php`

**C:**

```
ANTES: controller só index + store; resource não expõe status.
DEPOIS:
  Métodos: index (filtro por chat_instance_id, status), show, store, update, destroy, sync.
  Request valida: name, language, category, components (estrutura Meta), chat_instance_id.
  Resource expõe: id, name, language, category, status, rejected_reason,
                  provider, chat_instance_id, components, is_active, last_synced_at.
  Filtros via query: ?status=pending&chat_instance_id=...&search=...
```

**E:**

- [ ] Pest: cada método CRUD coberto
- [ ] Resource serializa corretamente componentes Meta
- [ ] Filtros funcionam combinados

**Agente:** BACKEND

---

### TASK-050.A6 — Rotas REST

**T:** Registrar rotas completas em `chat.php`.

**A:** `api/src/Domain/Chat/Routes/chat.php`

**C:**

```
ANTES:
  GET  message-templates
  POST message-templates
DEPOIS:
  GET    chat/message-templates
  POST   chat/message-templates
  GET    chat/message-templates/{id}
  PUT    chat/message-templates/{id}
  DELETE chat/message-templates/{id}
  POST   chat/message-templates/sync  (body: chat_instance_id)
```

**E:**

- [ ] `php artisan route:list --path=message-templates` mostra 6 rotas
- [ ] Todas exigem `auth:sanctum`

**Agente:** BACKEND

---

### TASK-050.A7 — Action + rota: enviar template em ticket

**T:** Enviar mensagem via template a partir de um ticket aberto.

**A:**

- `api/src/Domain/Chat/Actions/SendTemplateMessageAction.php`
- `api/src/Domain/Chat/Http/Controllers/ChatMessageController.php` (novo método `sendTemplate`)
- `api/src/Domain/Chat/Routes/chat.php` (`POST tickets/{id}/messages/template`)
- `api/src/Domain/Chat/Http/Requests/SendTemplateMessageRequest.php`

**C:**

```
ANTES: só envio de texto/mídia; nenhuma rota envia template via ticket.
DEPOIS:
  POST tickets/{ticketId}/messages/template
    body: { template_id: uuid, params: string[] }
  Action:
    1. Carrega ticket + valida ownership tenant
    2. Carrega template (mesmo tenant, status='approved')
    3. Cria ChatMessage outgoing com source='agent', metadata.template_name, metadata.params
    4. Dispatch para gateway via ChatMessageGatewayDispatcher (path: /channels/{id}/send-template)
    5. Broadcast realtime (mesmo fluxo de outgoing message)
```

**E:**

- [ ] Pest: envio cria ChatMessage com metadata correta
- [ ] Mensagem aparece via WS para o atendente
- [ ] Template não aprovado é rejeitado

**Agente:** BACKEND

---

### TASK-050.A8 — Guard 24h server-side

**T:** Bloquear envio de texto livre fora da janela em canais Meta.

**A:** `api/src/Domain/Chat/Actions/SendChatMessageAction.php`

**C:**

```
ANTES: envio de texto não checa janela 24h.
DEPOIS:
  Antes de gravar/dispatchar, se ticket.chat_instance.provider == 'meta':
    1. Reutiliza VerifyContactWindowAction
    2. Se canSendFreeText == false → throw ValidationException com
       errors[message] = ['Janela 24h expirada — use template']
       código HTTP 422, código semântico WINDOW_24H_EXPIRED
```

**E:**

- [ ] Pest: cenário Meta + janela expirada retorna 422
- [ ] Cenário Meta + janela aberta passa
- [ ] Cenário não-Meta passa em qualquer caso

**Agente:** BACKEND

---

### TASK-050.A9 — Listener `MetaTemplateStatusUpdatedListener`

**T:** Atualizar status local quando Meta enviar webhook `message_template_status_update`.

**A:**

- `api/src/Domain/Chat/Listeners/MetaTemplateStatusUpdatedListener.php`
- Registro em `ChatEventServiceProvider` ou `ChatWebhookRouter`

**C:**

```
ANTES: webhook de status não tem handler.
DEPOIS:
  Listener recebe payload normalizado pelo Gateway:
    { external_id, event: APPROVED|REJECTED|PAUSED|DISABLED, reason?, language, name }
  1. Localiza template por (chat_instance_id, external_id) ou (name, language)
  2. Atualiza status + rejected_reason + last_synced_at
  3. Broadcast event 'chat.template.updated' para tenant
```

**E:**

- [ ] Pest com payload sintético atualiza row
- [ ] Não cria novo se template não existe (apenas log warn)

**Agente:** BACKEND

---

### TASK-050.A10 — Policy `ChatMessageTemplatePolicy`

**T:** Autorização por role.

**A:**

- `api/src/Domain/Chat/Policies/ChatMessageTemplatePolicy.php`
- Registro em `AuthServiceProvider`

**C:**

```
DEPOIS:
  viewAny: qualquer usuário autenticado do tenant
  view:    mesmo tenant
  create:  permission 'chat.templates.manage'
  update:  permission 'chat.templates.manage'
  delete:  permission 'chat.templates.manage'
  sync:    permission 'chat.templates.manage'
```

**E:**

- [ ] Pest: usuário sem permissão recebe 403 em create
- [ ] Multi-tenant: user de tenant A não vê templates de tenant B

**Agente:** BACKEND

---

## 🌐 BLOCO B — Gateway (NestJS)

### TASK-050.B1 — `MetaClient.createTemplate`

**T:** Adicionar método para criar template na Meta.

**A:** `gateway/src/domains/chat/providers/meta/meta.client.ts` + `meta.dto.ts`

**C:**

```
ANTES: client só lista e envia template.
DEPOIS:
  createTemplate(wabaId, accessToken, payload: MetaTemplateCreatePayload)
    POST /{wabaId}/message_templates
    payload: { name, language, category, components: [...] }
    retorna { id, status }
```

**E:**

- [ ] Spec com axios mock
- [ ] Erro 400 (nome duplicado) propaga mensagem da Meta

**Agente:** GATEWAY

---

### TASK-050.B2 — `MetaClient.deleteTemplate`

**T:** Excluir template via Meta Graph API.

**A:** `gateway/src/domains/chat/providers/meta/meta.client.ts`

**C:**

```
DEPOIS:
  deleteTemplate(wabaId, accessToken, name)
    DELETE /{wabaId}/message_templates?name={name}
```

**E:**

- [ ] Spec verifica query param
- [ ] 200 retorna `{ success: true }`

**Agente:** GATEWAY

---

### TASK-050.B3 — `MetaAdapter.listTemplates(includeAll)`

**T:** Sobrescrever filtro APPROVED para retornar todos status quando solicitado.

**A:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts`

**C:**

```
ANTES: listTemplates(token) sempre filtra APPROVED.
DEPOIS: listTemplates(token, includeAll = false)
        - includeAll=false: cache key 'meta:templates:approved:{token}'
        - includeAll=true:  cache key 'meta:templates:all:{token}', sem filtro
```

**E:**

- [ ] Cache keys distintos
- [ ] Spec valida ambos modos

**Agente:** GATEWAY

---

### TASK-050.B4 — Endpoints REST em `channels.controller.ts`

**T:** Expor CRUD de templates ao Backend.

**A:** `gateway/src/domains/chat/channels.controller.ts`

**C:**

```
DEPOIS (todos protegidos por GatewaySecretGuard):
  GET    /channels/:id/templates?include_all=bool
  POST   /channels/:id/templates              body: MetaTemplateCreatePayload
  DELETE /channels/:id/templates/:name
  POST   /channels/:id/templates/sync         (no-op além de invalidar cache)
```

**E:**

- [ ] e2e supertest cobrindo 4 endpoints
- [ ] 401 sem GATEWAY_SECRET

**Agente:** GATEWAY

---

### TASK-050.B5 — Webhook normalize: `message_template_status_update`

**T:** Reconhecer e normalizar evento.

**A:** `gateway/src/domains/chat/providers/meta/meta.provider.ts`

**C:**

```
ANTES: normalize só trata messages e statuses.
DEPOIS: se field == 'message_template_status_update':
  - direction = 'template_status'
  - payload normalizado: { external_id, event, reason?, language, name, mmlite_status? }
  - publica em redis stream chat-events com type='meta.template.status_updated'
```

**E:**

- [ ] Spec: payload sintético produz evento correto
- [ ] Stream contém `event_type='meta.template.status_updated'`

**Agente:** GATEWAY

---

### TASK-050.B6 — Invalidar cache no write

**T:** Após create/delete/sync, apagar `meta:templates:*:{token}`.

**A:** `gateway/src/domains/chat/providers/meta/meta.adapter.ts`

**C:**

```
DEPOIS: novo método invalidateCache(token) chamado no fim de create/delete/sync.
       DEL meta:templates:approved:{token}
       DEL meta:templates:all:{token}
```

**E:**

- [ ] Spec verifica chamada Redis DEL
- [ ] Após create, lista subsequente busca da Meta

**Agente:** GATEWAY

---

## 🎨 BLOCO C — Frontend (admin de templates)

### TASK-050.C1 — `ChatMessageTemplateService`

**T:** Service Angular com CRUD completo.

**A:**

- `app/src/app/pages/chat/templates/services/template.service.ts`
- `app/src/app/core/models/chat-message-template.model.ts`

**C:**

```
DEPOIS: signals + RxJS
  list(filters): Observable<Paginated<ChatMessageTemplate>>
  get(id): Observable<ChatMessageTemplate>
  create(dto): Observable<ChatMessageTemplate>
  update(id, dto): Observable<ChatMessageTemplate>
  delete(id): Observable<void>
  sync(chatInstanceId): Observable<{ count: number }>
  Mapeia response.data conforme padrão API.
```

**E:**

- [ ] Vitest spec com HttpTestingController
- [ ] Mapeamento `data` correto

**Agente:** FRONTEND

---

### TASK-050.C2 — Página `TemplateListPage`

**T:** Lista com filtros + status badges + ações.

**A:**

- `app/src/app/pages/chat/templates/templates-page.{ts,html}`
- `app/src/app/pages/chat/templates/components/template-status-badge/...`
- Atualizar rotas em `app/src/app/pages/chat/chat.routes.ts`

**C:**

```
DEPOIS:
  Tabela com: Nome, Canal, Idioma, Categoria, Status (badge), Atualizado em.
  Filtros: chat_instance_id (select), status, search.
  Botões: "Novo template", "Sincronizar com Meta" (por canal).
  Linhas REJECTED exibem ícone de aviso com tooltip = rejected_reason.
  Ação por linha: Editar, Excluir, Visualizar.
  ChangeDetection.OnPush; takeUntilDestroyed.
```

**E:**

- [ ] Vitest: filtro reflete em chamada de service
- [ ] Botão sync dispara service.sync()
- [ ] Tooltip de rejected_reason visível

**Agente:** FRONTEND (com DESIGNER prévio)

---

### TASK-050.C3 — Componente `TemplateForm`

**T:** Form criar/editar template.

**A:**

- `app/src/app/pages/chat/templates/components/template-form/template-form.{ts,html}`

**C:**

```
DEPOIS: Reactive Form
  Campos: chat_instance_id (select canais Meta), name, language, category,
          components.body.text, components.body.examples[],
          components.footer.text (opcional), buttons[] (quick-reply opcional).
  Submit:
    - Em criação Meta: avisa "será enviado para aprovação Meta (24-48h)"
    - Em edição template Meta: somente shortcut e is_active editáveis
```

**E:**

- [ ] Vitest: validações funcionam (name required, body required)
- [ ] Componente integrado à página List

**Agente:** FRONTEND (com DESIGNER prévio)

---

### TASK-050.C4 — Confirmação de exclusão

**T:** Modal confirmação que avisa exclusão remota.

**A:** Reuso `ConfirmModalComponent` existente; integração na página.

**C:**

```
DEPOIS: ao clicar Excluir em template Meta:
  Modal: "Excluir 'X'? Será removido também na Meta WhatsApp."
  Confirmar → service.delete(id) → invalida lista.
```

**E:**

- [ ] Cancelar não chama service
- [ ] Confirmar atualiza lista

**Agente:** FRONTEND

---

### TASK-050.C5 — Item de menu lateral

**T:** Adicionar entrada "Templates de mensagens" no sidebar.

**A:** `app/src/app/layout/sidebar/sidebar.{ts,html}` (caminho exato a confirmar)

**C:**

```
DEPOIS: novo item visível para users com permission 'chat.templates.manage',
        ícone lucideMessageSquareText, link /chat/templates.
```

**E:**

- [ ] Visível só para roles autorizadas
- [ ] Click navega corretamente

**Agente:** FRONTEND

---

## 💬 BLOCO D — Frontend (composer da tela de chat)

### TASK-050.D1 — Mover `template-selector` para shared

**T:** Extrair `TemplateSelectorComponent` para reuso.

**A:**

- DE: `app/src/app/pages/chat/components/new-conversation-modal/components/template-selector/`
- PARA: `app/src/app/shared/components/template-selector/`
- Atualizar imports em `new-conversation-modal.ts`.

**C:**

```
ANTES: componente acoplado ao modal.
DEPOIS: componente standalone reutilizável; aceita inputs:
        chatInstanceId, contactId; emite TemplateSelectedEvent.
        Service de templates injetado via providedIn:root.
```

**E:**

- [ ] Modal de nova conversa continua funcionando (vitest passa)
- [ ] Componente importável de qualquer lugar

**Agente:** FRONTEND

---

### TASK-050.D2 — Signal `composerMode` no `ChatStore`

**T:** Computed mode baseado em provider + janela.

**A:** `app/src/app/pages/chat/chat.store.ts`

**C:**

```
DEPOIS: signals
  windowStatus = signal<WindowStatus | null>(null)
  composerMode = computed(() => {
    const t = this.selectedTicket();
    if (!t || t.chat_instance?.provider !== 'meta') return 'free';
    const ws = this.windowStatus();
    if (ws?.canSendFreeText) return 'mixed';
    return 'template-only';
  })
```

**E:**

- [ ] Vitest cobrindo 3 cenários
- [ ] Sem subscriptions vazadas

**Agente:** FRONTEND

---

### TASK-050.D3 — `chat.ts`: efeito ao mudar ticket

**T:** Carregar windowStatus quando ticket Meta selecionado.

**A:** `app/src/app/pages/chat/chat.ts`

**C:**

```
DEPOIS: effect() que observa selectedTicketId():
  Se ticket?.chat_instance?.provider === 'meta':
    windowVerificationService.checkStatus(contact_id)
      .pipe(takeUntilDestroyed) → store.setWindowStatus(s)
  Senão: store.setWindowStatus(null)
```

**E:**

- [ ] Vitest: trocar ticket dispara HTTP
- [ ] Trocar ticket não-Meta não dispara

**Agente:** FRONTEND

---

### TASK-050.D4 — Composer condicional

**T:** Condicionar `<textarea>` ao composerMode.

**A:**

- `app/src/app/pages/chat/components/chat-conversation-component/chat-conversation-component.html` (linhas ~330-360)
- `chat-conversation-component.ts`: novo input `composerMode`

**C:**

```
ANTES: textarea sempre visível.
DEPOIS:
  @if (composerMode() === 'template-only') {
    <div class="composer-warning">Janela 24h expirada — selecione um template.</div>
    <app-template-selector
      [chatInstanceId]="chatInstanceId()"
      [contactId]="contactId()"
      (templateSelected)="onTemplateSelected($event)" />
  } @else {
    <textarea ... />  <!-- atual -->
    @if (composerMode() === 'mixed') {
      <button (click)="openTemplatePicker()">📋 Template</button>
    }
  }
```

**E:**

- [ ] Vitest snapshot dos 3 modos
- [ ] Visualmente validado pelo DESIGNER

**Agente:** FRONTEND (com DESIGNER prévio)

---

### TASK-050.D5 — Botão template no modo `mixed`

**T:** Picker inline opcional quando dentro da janela.

**A:** mesmo componente do D4

**C:**

```
DEPOIS: botão abre TemplateSelector como popover/sheet;
        ao confirmar, dispara mesmo handler que template-only.
```

**E:**

- [ ] Atendente consegue escolher template mesmo dentro da janela

**Agente:** FRONTEND

---

### TASK-050.D6 — `sendMessage()` bifurcar

**T:** Roteamento entre envio de texto e template.

**A:** `app/src/app/pages/chat/chat.ts` + service de envio

**C:**

```
ANTES: sendMessage() sempre POST tickets/{id}/messages com text.
DEPOIS:
  Se template selecionado:
    POST tickets/{id}/messages/template { template_id, params }
    limpa template selecionado
  Senão: fluxo atual
```

**E:**

- [ ] Vitest: ambos caminhos cobertos
- [ ] Mensagem template aparece com badge "via template"

**Agente:** FRONTEND

---

### TASK-050.D7 — Reabrir composer ao receber inbound

**T:** Quando cliente responde, janela reabre — invalidar cache.

**A:** `app/src/app/pages/chat/chat.ts` (handler WS já existente para `message.created`)

**C:**

```
DEPOIS: dentro do handler de novo inbound:
  Se message.is_from_contact && message.ticket_id === selectedTicketId():
    windowVerificationService.invalidateCache(contact_id)
    re-checa status → store.setWindowStatus(s)
    composerMode automaticamente vira 'mixed'
```

**E:**

- [ ] Vitest: simular WS event reabre composer
- [ ] Sem flicker visual

**Agente:** FRONTEND

---

## 🧪 BLOCO E — QA

### TASK-050.E1 — Backend gates

**T:** `composer gate:all` verde com cobertura ≥80% nos arquivos novos.

**E:**

- [ ] Pest passa
- [ ] PHPStan level 9 sem novos erros
- [ ] Cobertura nos paths `Domain/Chat/Actions` ≥80%

**Agente:** QA

---

### TASK-050.E2 — Frontend gates

**T:** `pnpm gate:all` verde.

**E:**

- [ ] Vitest passa
- [ ] ESLint sem novos erros
- [ ] Build production OK

**Agente:** QA

---

### TASK-050.E3 — E2E manual + evidências

**T:** Validar AC-01 a AC-12 em ambiente local com canal Meta sandbox.

**E:**

- [ ] Vídeo/screenshots dos 12 ACs
- [ ] CHANGELOG diário registra a entrega

**Agente:** QA + DOC

---

## 📋 Sequenciamento

```
Sprint 1 (foundation):  A1, A2, B3, B4, B6
Sprint 2 (CRUD admin):  A4, A5, A6, A10, B1, B2 → C1, C2, C3, C4, C5
Sprint 3 (lifecycle):   A3, A7, A8, A9, B5
Sprint 4 (composer):    D1, D2, D3, D4, D5, D6, D7
Sprint 5 (QA):          E1, E2, E3
```

Dependências críticas:

- C\* depende de A5/A6
- D6 depende de A7
- A9 depende de B5
- D2/D3 dependem de A8 (semântica do guard)
