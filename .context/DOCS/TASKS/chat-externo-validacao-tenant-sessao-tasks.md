# Tasks: Chat Externo — Validação de Tenant e Sessão

> Feature: .context/DOCS/FEATURES/chat-externo-validacao-tenant-sessao.md
> Total: 8 tasks | Pendentes: 0 | Em progresso: 0 | Concluídas: 8

---

## ✅ FASE 1: PLANNING
> Feature doc aprovada e fases mapeadas.

---

## ⏳ FASE 3: BACKEND

### 3.1 — Laravel API (endpoint público de tenant)

- [x] **TASK-3.1.1** ✅
  **T — Tarefa:** Criar `WebChatTenantController` invokable que retorna nome do tenant via `DB::table`
  **A — Arquivo:** `api/src/Domain/Chat/Http/Controllers/WebChatTenantController.php` (criar)
  **Referência:** `api/src/Domain/Chat/Http/Controllers/WebChatHealthController.php` — mesmo padrão invokable + BaseController
  **Imports autorizados:** `Domain\Chat\`, `Domain\Shared\`, `Illuminate\Support\Facades\DB`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`
  **PROIBIDO:** importar `Domain\Platform\` (Chat→Platform é forbidden em `dependencies.yaml`)
  **Decisão de arquitetura:** usar `DB::table('platform_tenants')` para acessar dados do tenant sem violar DDD — padrão já usado em `ChatRoutingQueueController.php` (linha 1, `use Illuminate\Support\Facades\DB`)
  **C — Comportamento:**
  ANTES: `GET /api/webchat/tenant/{tenantId}` não existe — retorna 404 no roteamento
  DEPOIS: `GET /api/webchat/tenant/{tenantId}` retorna `{ data: { name: "Nome da Empresa" } }` para tenant ativo, 404 para inválido/inativo
  **Implementação:**
  ```php
  <?php
  declare(strict_types=1);
  namespace Domain\Chat\Http\Controllers;
  use Domain\Shared\Http\Controllers\BaseController;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\DB;

  final class WebChatTenantController extends BaseController
  {
      public function __invoke(Request $request, string $tenantId): JsonResponse
      {
          $tenant = DB::table('platform_tenants')
              ->where('id', $tenantId)
              ->where('is_active', true)
              ->whereNull('deleted_at')
              ->select(['id', 'name'])
              ->first();

          if (! $tenant) {
              return $this->notFound('Empresa não encontrada');
          }

          return $this->success(['name' => $tenant->name]);
      }
  }
  ```
  **E — Evidência:**
  - [x] `cd api && php artisan route:list | grep webchat/tenant` → mostra a rota (após TASK-3.1.2)
  - [x] `cd api && composer gate:all` → zero erros
  **Status:** ✅ Concluída

- [x] **TASK-3.1.2** ✅
  **T — Tarefa:** Registrar rota `GET /webchat/tenant/{tenantId}` no arquivo de rotas webchat
  **A — Arquivo:** `api/src/Domain/Chat/Routes/webchat.php` (editar)
  **Referência:** linha 25 do mesmo arquivo — `Route::get('/webchat/health', ...)` — mesmo padrão invokable dentro do grupo throttle:webchat
  **Imports autorizados:** já usa `use Domain\Chat\Http\Controllers\...` no topo do arquivo
  **C — Comportamento:**
  ANTES: arquivo tem 6 rotas, sem rota para tenant info
  DEPOIS: arquivo tem 7 rotas, incluindo `Route::get('/webchat/tenant/{tenantId}', [WebChatTenantController::class, '__invoke'])` dentro do grupo `throttle:webchat`
  **E — Evidência:**
  - [ ] `cd api && php artisan route:list | grep webchat/tenant` → exibe `GET | api/webchat/tenant/{tenantId}`
  - [ ] `cd api && composer gate:all` → zero erros
  **Status:** ✅ Concluída

### 3.2 — Gateway NestJS (proxies para novos endpoints)

- [x] **TASK-3.2.1** ✅
  **T — Tarefa:** Adicionar métodos `getTenantInfo()` e `getSession()` em `WebChatProxyService`
  **A — Arquivo:** `gateway/src/domains/realtime/services/webchat-proxy.service.ts` (editar)
  **Referência:** método `getSessionMessages()` (linha 88) do mesmo arquivo — mesmo padrão axios GET com params
  **C — Comportamento:**
  ANTES: serviço tem 5 métodos (createSession, sendMessage, uploadMedia, closeTicket, getSessionMessages)
  DEPOIS: serviço tem 7 métodos, adicionando:
  - `getTenantInfo(tenantId)` → `GET /api/webchat/tenant/:tenantId`
  - `getSession(sessionId, tenantId)` → `GET /api/webchat/sessions/:id?tenant_id=:tenantId`
  **Implementação dos dois métodos a adicionar:**
  ```typescript
  async getTenantInfo(tenantId: string): Promise<unknown> {
    this.logger.log(`Proxying GET /api/webchat/tenant/${tenantId}`);
    try {
      const response = await axios.get(
        `${this.apiUrl}/api/webchat/tenant/${tenantId}`,
        { timeout: this.timeoutMs },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(`GET /api/webchat/tenant/${tenantId}`, error);
    }
  }

  async getSession(sessionId: string, tenantId: string): Promise<unknown> {
    this.logger.log(`Proxying GET /api/webchat/sessions/${sessionId}`);
    try {
      const response = await axios.get(
        `${this.apiUrl}/api/webchat/sessions/${sessionId}`,
        { params: { tenant_id: tenantId }, timeout: this.timeoutMs },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(`GET /api/webchat/sessions/${sessionId}`, error);
    }
  }
  ```
  **E — Evidência:**
  - [ ] `cd gateway && pnpm lint && pnpm test` → zero erros
  **Status:** ✅ Concluída

- [x] **TASK-3.2.2** ✅
  **T — Tarefa:** Adicionar endpoints `GET /api/webchat/tenant/:tenantId` e `GET /api/webchat/sessions/:id` no `WebChatController`
  **A — Arquivo:** `gateway/src/domains/realtime/controllers/webchat.controller.ts` (editar)
  **Referência:** método `getSessionMessages()` (linha 100) do mesmo arquivo — mesmo padrão `@Get + @Param + @Query`
  **C — Comportamento:**
  ANTES: controller tem 5 endpoints (POST sessions, GET sessions/:id/messages, POST messages, POST media, POST close)
  DEPOIS: controller tem 7 endpoints, adicionando:
  - `GET /api/webchat/tenant/:tenantId` → chama `webchatProxy.getTenantInfo(tenantId)`
  - `GET /api/webchat/sessions/:id` → chama `webchatProxy.getSession(id, tenantId)` com `@Query('tenant_id')`
  **Implementação dos dois métodos a adicionar:**
  ```typescript
  @Get('tenant/:tenantId')
  getTenantInfo(@Param('tenantId') tenantId: string): Promise<unknown> {
    this.logger.log(`Fetching tenant info for ${tenantId}`);
    return this.webchatProxy.getTenantInfo(tenantId);
  }

  @Get('sessions/:id')
  getSession(
    @Param('id') id: string,
    @Query('tenant_id') tenantId: string,
  ): Promise<unknown> {
    this.logger.log(`Fetching session ${id}`);
    return this.webchatProxy.getSession(id, tenantId);
  }
  ```
  **E — Evidência:**
  - [ ] `cd gateway && pnpm lint && pnpm test` → zero erros
  **Status:** ✅ Concluída

---

## ⏳ FASE 4: FRONTEND

### 4.1 — Models e Service Angular

- [x] **TASK-4.1.1** ✅
  **T — Tarefa:** Adicionar interfaces `WebChatTenantInfo` e `WebChatSessionDetail` em `webchat.model.ts`
  **A — Arquivo:** `app/src/app/pages/webchat/webchat.model.ts` (editar)
  **Referência:** interface `WebChatCloseResponse` (linha 21) do mesmo arquivo — mesmo padrão de interface simples
  **C — Comportamento:**
  ANTES: arquivo tem 8 interfaces/types, sem tipos para tenant info ou session detail
  DEPOIS: arquivo tem 10 interfaces, adicionando ao final do arquivo:
  ```typescript
  export interface WebChatTenantInfo {
    name: string;
  }

  export interface WebChatSessionDetail {
    id: string;
    ticket: { id: string; status: string; protocol?: string } | null;
  }
  ```
  **E — Evidência:**
  - [ ] `cd app && pnpm build` → sem erros TypeScript
  **Status:** ✅ Concluída

- [x] **TASK-4.1.2** ✅
  **T — Tarefa:** Adicionar métodos `getTenantInfo()` e `getSession()` em `WebChatService`
  **A — Arquivo:** `app/src/app/pages/webchat/services/webchat.service.ts` (editar)
  **Referência:** método `fetchSessionMessages()` (linha 314) do mesmo arquivo — mesmo padrão `http.get + map(unwrapData) + catchError`
  **C — Comportamento:**
  ANTES: serviço tem `createSession`, `sendMessage`, `uploadMedia`, `fetchSessionMessages`, `closeTicket`, etc. Sem métodos para buscar tenant ou verificar status de sessão
  DEPOIS: serviço tem 2 novos métodos públicos:
  - `getTenantInfo(tenantId)` → `GET /api/webchat/tenant/:tenantId` → `Observable<WebChatTenantInfo>`
  - `getSession(sessionId, tenantId)` → `GET /api/webchat/sessions/:id?tenant_id=:tenantId` → `Observable<WebChatSessionDetail>`
  **Imports a adicionar:** `WebChatTenantInfo`, `WebChatSessionDetail` da linha de import do webchat.model.ts
  **Implementação dos dois métodos:**
  ```typescript
  getTenantInfo(tenantId: string): Observable<WebChatTenantInfo> {
    return this.http
      .get<unknown>(`${this.apiBase}/api/webchat/tenant/${tenantId}`)
      .pipe(
        map((response) => this.unwrapData<WebChatTenantInfo>(response)),
        catchError((err) => {
          return throwError(() => err);
        }),
      );
  }

  getSession(sessionId: string, tenantId: string): Observable<WebChatSessionDetail> {
    const params = new URLSearchParams({ tenant_id: tenantId });
    return this.http
      .get<unknown>(`${this.apiBase}/api/webchat/sessions/${sessionId}?${params}`)
      .pipe(
        map((response) => this.unwrapData<WebChatSessionDetail>(response)),
        catchError((err) => {
          return throwError(() => err);
        }),
      );
  }
  ```
  **E — Evidência:**
  - [ ] `cd app && pnpm build` → sem erros TypeScript
  - [ ] `cd app && pnpm lint` → zero erros ESLint
  **Status:** ✅ Concluída

### 4.2 — Componente Angular (lógica + template)

- [x] **TASK-4.2.1** ✅
  **T — Tarefa:** Refatorar `WebChatPageComponent` para validar tenant e verificar status da sessão no `ngOnInit`
  **A — Arquivo:** `app/src/app/pages/webchat/webchat-page.component.ts` (editar)
  **Referência:** arquivo inteiro atual (lido na análise) — refatoração in-place dos métodos `ngOnInit` e `attemptSessionRestore`
  **C — Comportamento:**
  ANTES: `ngOnInit` extrai `tenantId` e chama `attemptSessionRestore()` direto, sem validar tenant. `attemptSessionRestore()` restaura sessão sem verificar status do ticket no backend.
  DEPOIS: `ngOnInit` executa fluxo sequencial:
  1. `isCarregando = true` (spinner)
  2. `getTenantInfo(tenantId)` → sucesso: `tenantName.set(name)`, avança; erro: `tenantError.set(true)`, para
  3. `restoreSession()` → se nenhuma sessão local, `isCarregando = false`, retorna (mostra pré-chat)
  4. Se sessão local encontrada: `getSession(sessionId, tenantId)` → se `ticket.status === 'closed'`: `clearSession()`, `isCarregando = false`; senão: fluxo atual (connectWebSocket, hasSession=true)
  **Novos signals a adicionar (além dos existentes):**
  ```typescript
  readonly tenantName = signal<string | null>(null);
  readonly tenantError = signal(false);
  readonly isCarregando = signal(true); // substitui isRestoring
  ```
  **Computed a atualizar:**
  ```typescript
  readonly showPreChat = computed(() => !this.hasSession() && !this.isCarregando());
  readonly showChat = computed(() => this.hasSession());
  ```
  **Statuses de ticket fechado:** `'closed'` e `'resolved'`
  **E — Evidência:**
  - [ ] `cd app && pnpm build` → sem erros TypeScript
  - [ ] `cd app && pnpm lint` → zero erros ESLint
  - [ ] Acessar `/chat/external/{uuid-invalido}` → não renderiza pré-chat (validado no TASK-5.1.1)
  **Status:** ✅ Concluída

- [x] **TASK-4.2.2** ✅
  **T — Tarefa:** Atualizar template `webchat-page.component.html` com tela de erro e nome do tenant
  **A — Arquivo:** `app/src/app/pages/webchat/webchat-page.component.html` (editar)
  **Referência:** arquivo atual (4 blocos @if) — adicionar bloco de erro e nome do tenant
  **Design:** sem wireframe formal — layout consistente com o visual existente do pré-chat (bg-surface-50, text-neutral-500)
  **C — Comportamento:**
  ANTES: template tem 3 blocos (@if isRestoring → spinner, @if showPreChat → pré-chat, @if showChat → chat window). Sem tela de erro. Sem nome do tenant.
  DEPOIS: template tem 4 blocos:
  1. `@if (isCarregando())` → spinner com texto "Verificando..."
  2. `@if (tenantError())` → tela de erro bloqueante (empresa não encontrada)
  3. `@if (showPreChat())` → `<app-pre-chat>` com novo input `[tenantName]="tenantName()"`
  4. `@if (showChat())` → chat window (sem mudança)
  **Template do bloco de erro:**
  ```html
  @if (tenantError()) {
    <div class="flex items-center justify-center min-h-screen bg-surface-50">
      <div class="flex flex-col items-center gap-3 text-center px-6">
        <p class="text-lg font-medium text-neutral-700">Empresa não encontrada</p>
        <p class="text-sm text-neutral-500">O link de atendimento que você acessou é inválido ou não está mais disponível.</p>
      </div>
    </div>
  }
  ```
  **Nota:** `app-pre-chat` recebe `[tenantName]="tenantName()"` — o `PreChatComponent` deve aceitar esse input (verificar se já existe ou adicionar `@Input() tenantName: string | null = null`)
  **E — Evidência:**
  - [ ] `cd app && pnpm build` → sem erros TypeScript
  - [ ] `cd app && pnpm lint` → zero erros ESLint
  **Status:** ✅ Concluída

---

## ⏳ FASE 5: INTEGRATION

### 5.1 — Verificação ponta a ponta

- [x] **TASK-5.1.1** ✅
  **T — Tarefa:** Verificar os 5 cenários do fluxo completo no browser com serviços rodando
  **A — Arquivo:** sem arquivo a criar — verificação manual
  **Referência:** sem referência — verificação de comportamento
  **C — Comportamento:**
  ANTES: cenários de erro (tenant inválido, sessão fechada) não são tratados
  DEPOIS: todos os 5 cenários funcionam conforme critérios de aceite do feature doc
  **Cenários a verificar:**
  1. `/chat/external/3453efd7-1344-4551-999b-340b37b8d501` (UUID inválido) → exibe tela de erro, não o formulário
  2. `/chat/external/{tenant-uuid-valido}` → exibe nome da empresa no header do pré-chat
  3. Com sessão de ticket fechado no sessionStorage + `?s=sessionId` → exibe pré-chat limpo (sem restaurar)
  4. Com sessão de ticket aberto no sessionStorage → restaura normalmente
  5. Sem sessão no sessionStorage → exibe pré-chat normalmente
  **E — Evidência:**
  - [ ] Cenário 1: tela de erro visível, sem formulário de pré-chat
  - [ ] Cenário 2: nome da empresa visível no header
  - [ ] Cenário 3: pré-chat limpo (sem nome de visitante pré-preenchido)
  - [ ] Cenário 4: chat window aberto com histórico
  - [ ] Cenário 5: formulário de pré-chat vazio
  **Status:** ✅ Concluída
