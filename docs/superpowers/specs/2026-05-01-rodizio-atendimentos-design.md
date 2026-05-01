# Spec: Rodízio Automático de Atendimentos

**Data:** 2026-05-01
**Feature:** FEAT-052 (proposta)
**Status:** Aprovado — pronto para implementação

---

## Problema

Atualmente os tickets chegam e ficam sem agente atribuído até que um gestor ou agente os reivindique manualmente. Isso gera atraso no primeiro atendimento e distribuição desigual de carga entre a equipe.

## Solução

Atribuição automática no momento de criação do ticket (inbound), usando um sistema de filas de rodízio configurável por tenant (global) e por canal (override).

---

## Modelo Incremental

| Fase | Estratégia | Status |
|------|-----------|--------|
| 1 | Round Robin | Fase inicial |
| 2 | Least Busy (Menor Carga) | Fase futura |
| 3 | Skill-Based (Por Habilidade) | Fase futura |

---

## Schema

### `chat_routing_queues`

| Coluna | Tipo | Notas |
|--------|------|-------|
| id | UUID PK | |
| tenant_id | UUID FK | |
| instance_id | UUID FK nullable | `NULL` = escopo global; `NOT NULL` = override por canal |
| name | string | |
| is_enabled | boolean | default `false` |
| strategy | enum | `round_robin` \| `least_busy` \| `skill_based`, default `round_robin` |
| max_open_tickets_per_agent | integer nullable | `NULL` = ilimitado (Fase 1 e 2) |
| created_at, updated_at | timestamps | |

**Escopo derivado de `instance_id`:** `NULL` = global, `NOT NULL` = canal. Não há coluna `scope` — a FK nullable é a única fonte de verdade, evitando inconsistências.

**Constraints:**
```sql
UNIQUE(tenant_id) WHERE instance_id IS NULL        -- uma config global por tenant
UNIQUE(tenant_id, instance_id) WHERE instance_id IS NOT NULL  -- uma por canal
```

**Índices de performance:**
```sql
INDEX(tenant_id, instance_id)          -- lookup de fila por canal
INDEX(tenant_id) WHERE instance_id IS NULL  -- lookup de fila global
```

---

### `chat_routing_queue_agents`

| Coluna | Tipo | Notas |
|--------|------|-------|
| id | UUID PK | |
| queue_id | UUID FK | |
| user_id | UUID FK | |
| position | integer NOT NULL DEFAULT 0 | critério secundário de ordenação |
| last_assigned_at | timestamp nullable | critério primário de ordenação |
| is_active | boolean NOT NULL | default `true` |
| created_at, updated_at | timestamps | |

**Constraints:** `UNIQUE(queue_id, user_id)`

**Índice crítico para o algoritmo:**
```sql
INDEX(queue_id, is_active, last_assigned_at)  -- usado no SELECT FOR UPDATE SKIP LOCKED
```

**Ordenação do round robin:**
1. `last_assigned_at ASC NULLS FIRST` — quem esperou mais (ou nunca foi atribuído)
2. `position ASC` — desempate pela posição configurada pelo gestor

---

### `chat_routing_agent_skills` (Fase 3)

| Coluna | Tipo | Notas |
|--------|------|-------|
| id | UUID PK | |
| queue_id | UUID FK | |
| user_id | UUID FK | |
| skill | string | deve bater com o campo `category` do `ChatTicket` |
| created_at, updated_at | timestamps | |

**Nota Fase 3:** O matching de skills usa o campo `category` já existente em `chat_tickets`. Nenhuma migration é necessária no ticket para a Fase 3.

---

## Camada de Aplicação

### Novos arquivos (path DDD)

```
api/src/Domain/Chat/Models/ChatRoutingQueue.php
api/src/Domain/Chat/Models/ChatRoutingQueueAgent.php
api/src/Domain/Chat/Services/ChatRoutingService.php
api/src/Domain/Chat/Policies/ChatRoutingQueuePolicy.php
```

---

### `ChatRoutingService`

Único responsável pela lógica de seleção de agente. Reside em `Domain/Chat/Services/`.

**Resolução de fila:**
1. Existe fila com `instance_id = ticket->instance_id` (is_enabled=true)? → usa ela
2. Existe fila com `instance_id IS NULL` para o tenant (is_enabled=true)? → usa ela
3. Nenhuma → retorna `null` (sem atribuição automática)

**Round Robin (Fase 1) — dentro de DB::transaction:**
```sql
BEGIN;
SELECT * FROM chat_routing_queue_agents
WHERE queue_id = ? AND is_active = true
ORDER BY last_assigned_at ASC NULLS FIRST, position ASC
FOR UPDATE SKIP LOCKED
LIMIT 1;

UPDATE chat_routing_queue_agents
SET last_assigned_at = NOW()
WHERE id = ?;
COMMIT;
```
`SKIP LOCKED` garante que requests concorrentes não esperem — cada um pega o próximo agente disponível sem bloqueio.

**Least Busy (Fase 2):**
- Conta tickets com `status IN ('pending','open')` por agente
- Exclui agentes que atingiram `max_open_tickets_per_agent` (quando `NOT NULL`)
- Ordena por contagem ASC, desempata por `last_assigned_at ASC NULLS FIRST`

**Skill-Based (Fase 3):**
- Filtra agentes cujo `skill` bate com `ticket->category`
- Aplica round robin no grupo filtrado

---

### Hook em `CreateChatTicketAction`

Arquivo: `api/src/Domain/Chat/Actions/CreateChatTicketAction.php`

O routing e o assign ocorrem na **mesma transação de banco** iniciada pelo action:

```php
DB::transaction(function () use ($ticket) {
    $userId = $this->routingService->route($ticket); // SELECT FOR UPDATE SKIP LOCKED aqui
    if ($userId) {
        $this->assignAction->transfer($ticket, $userId, null); // commit junto
    }
});
```

Falha no routing **não bloqueia** a criação do ticket — o try/catch envolve apenas o bloco de routing, não o create:

```php
$ticket = $this->createTicket($dto); // sempre executa

try {
    DB::transaction(function () use ($ticket) {
        $userId = $this->routingService->route($ticket);
        if ($userId) {
            $this->assignAction->transfer($ticket, $userId, null);
        }
    });
} catch (\Throwable $e) {
    Log::error('[Routing] Falha ao rotear ticket', [
        'ticket_id' => $ticket->id,
        'error'     => $e->getMessage(),
    ]);
}
```

---

## API Endpoints

### Autorização

Permissões via Spatie Laravel Permission:

| Permissão | Quem tem | Uso |
|-----------|----------|-----|
| `chat.routing.manage` | Admin, Gestor | criar/editar filas e agentes |
| `chat.routing.view` | Admin, Gestor, Agente | visualizar configuração |

Policy: `ChatRoutingQueuePolicy` — valida que o `user_id` do agente pertence ao mesmo `tenant_id` da fila (impede cross-tenant via 403).

---

### Endpoints por canal

Dentro do `Route::prefix('channels')` existente em `chat.php`:

```
GET    /chat/channels/{id}/routing-queue
POST   /chat/channels/{id}/routing-queue
PUT    /chat/channels/{id}/routing-queue

GET    /chat/channels/{id}/routing-queue/agents
POST   /chat/channels/{id}/routing-queue/agents       body: { user_id, position? }
DELETE /chat/channels/{id}/routing-queue/agents/{user}
PUT    /chat/channels/{id}/routing-queue/agents/reorder  body: { agents: [{ user_id, position }] }
```

### Endpoints globais (fora do prefix `channels`)

```
GET    /chat/routing-queue/global
POST   /chat/routing-queue/global                     body: { name, strategy }
PUT    /chat/routing-queue/global                     body: { name?, strategy?, is_enabled?, max_open_tickets_per_agent? }

GET    /chat/routing-queue/global/agents
POST   /chat/routing-queue/global/agents              body: { user_id, position? }
DELETE /chat/routing-queue/global/agents/{user}
PUT    /chat/routing-queue/global/agents/reorder      body: { agents: [{ user_id, position }] }
```

### Fase 3

```
GET/POST/DELETE /chat/channels/{id}/routing-queue/agents/{user}/skills   body: { skill }
GET/POST/DELETE /chat/routing-queue/global/agents/{user}/skills           body: { skill }
```

**Novos arquivos DDD:**
```
api/src/Domain/Chat/Http/Controllers/ChatRoutingQueueController.php
api/src/Domain/Chat/Http/Requests/ChatRoutingQueueRequest.php
api/src/Domain/Chat/Http/Resources/ChatRoutingQueueResource.php
```

---

## Frontend

### Config global — `chat/configuration` (nova página)

- Nova rota em `app/src/app/app.routes.ts`:
  ```ts
  { path: 'chat/configuration', loadComponent: ..., data: { title: 'Configuração', permission: 'chat.routing.manage' } }
  ```
- Guard de rota: usa o guard de permissão já existente no app (baseado em `permission` no `data`)
- Nova entrada no menu em `menu-config.ts`:
  ```ts
  { type: 'item', label: 'Configuração', link: '/chat/configuration', iconName: 'settings', requiredPermission: 'chat.routing.manage' }
  ```
- Padrão visual: `af-page-title` + `space-y-6` (idêntico a `media-transcription`)
- Conteúdo: toggle ativar, seletor de estratégia, lista de agentes com drag-and-drop

### Override por canal — modal em `chat/channel`

- Novo botão na tabela de canais (ícone `users-round`, Lucide) — visível apenas para `chat.routing.manage`
- Modal com toggle "Sobrepor configuração global"
  - Ativo: exibe campos de estratégia + lista de agentes
  - Inativo: exibe badge "Usando configuração global"

### Sincronização em tempo real

**Não implementada no MVP.** O estado dos signals é local à sessão — nenhuma sincronização WebSocket para a tela de configuração de rodízio. Gestor precisa recarregar para ver mudanças de outra sessão. Aceitável para Fase 1.

### Novos componentes

```
app/src/app/pages/chat/
  configuration/
    chat-configuration.ts
    chat-configuration.html
  channel/components/
    channel-routing/
      channel-routing.ts
      channel-routing.html
      components/
        routing-agent-list/
        routing-agent-form/
```

### Service Angular

`ChatRoutingQueueService` em `app/src/app/pages/chat/services/`
- Signals para estado da fila e lista de agentes
- Métodos: `loadGlobal()`, `loadForChannel(id)`, `save()`, `addAgent()`, `removeAgent()`, `reorder()`
- Sem invalidação automática de cache — cada abertura do modal faz novo GET

---

## Testes

### Backend (Pest) — `ChatRoutingServiceTest`

**Requer PostgreSQL real** (não SQLite) para testar `SKIP LOCKED`. Usar `@group integration` ou rodar contra o banco de desenvolvimento com `APP_ENV=testing` e `DB_CONNECTION=pgsql`.

Cenários:
- Round robin distribui tickets sequencialmente
- SKIP LOCKED evita atribuição duplicada: 10 coroutines simultâneas com 5 agentes → cada agente recebe no máximo 2 tickets
- Agente `is_active=false` é ignorado
- Fila com `is_enabled=false` retorna `null`
- Canal com `instance_id` e fila ativa → usa fila do canal
- Canal sem fila → usa config global
- Sem fila global e sem override → retorna `null`
- [Fase 2] Agente com `max_open_tickets_per_agent` atingido é pulado
- [Fase 2] `max_open_tickets_per_agent = NULL` trata como ilimitado
- [Fase 3] Agente sem skill que bate com `ticket->category` é ignorado

### Backend — `CreateChatTicketActionTest`

- Ticket com fila ativa → `assigned_to` preenchido após create
- Ticket sem fila → `assigned_to` null, ticket criado normalmente
- Exceção no routing → ticket criado, `assigned_to` null, erro logado

### Backend — `ChatRoutingQueueControllerTest`

- CRUD completo para config global e por canal
- `POST agents` com `user_id` de outro tenant → 403
- `PUT agents/reorder` atualiza `position` de todos os agentes da lista

### Frontend (Vitest)

- `ChatRoutingQueueService.loadGlobal()` chama `GET /chat/routing-queue/global`
- `ChatRoutingQueueService.addAgent()` chama `POST .../agents` e atualiza signal
- `ChatRoutingQueueService.removeAgent()` chama `DELETE .../agents/{user}` e remove do array
- `ChannelRoutingComponent`: toggle "Sobrepor" exibe/oculta campos
- `ChannelRoutingComponent`: badge "Usando configuração global" aparece quando override=false
- `ChannelRoutingComponent`: agentes renderizados em ordem de `position`

### Gate de qualidade

- Coverage ≥ 80% em `ChatRoutingService`
- Zero regressão nos testes existentes de `ChatTicketActions`

---

## Rollout por Fase

| Fase | Branch | Entrega |
|------|--------|---------|
| 1 | `feature/FEAT-052-routing-round-robin` | Migrations + Models + Service (roundRobin) + Policy + Hook + API + Frontend completo (estratégias 2 e 3 desabilitadas na UI) |
| 2 | `feature/FEAT-052-routing-least-busy` | Método `leastBusy()` + habilitar na UI (`max_open_tickets_per_agent`) |
| 3 | `feature/FEAT-052-routing-skill-based` | Migration `chat_routing_agent_skills` + `skillBased()` + UI de skills |

**Notas de compatibilidade:**
- Schema da Fase 1 já inclui `max_open_tickets_per_agent` (`NULL` = ilimitado) — sem migration extra na Fase 2
- Tabela de skills da Fase 3 é migration separada e aditiva
- Fases 2 e 3 são zero-downtime: novas colunas nullable / nova tabela, sem alteração em tabelas existentes

---

## Arquivos Críticos

| Arquivo | Mudança |
|---------|---------|
| `api/src/Domain/Chat/Actions/CreateChatTicketAction.php` | Hook de routing pós-criação |
| `api/src/Domain/Chat/Routes/chat.php` | Novos endpoints (canal e global em grupos separados) |
| `app/src/app/app.routes.ts` | Rota `chat/configuration` com guard de permissão |
| `app/src/app/layout/components/sidenav/menu-config.ts` | Item "Configuração" com `requiredPermission` |
| `app/src/app/pages/chat/channel/channel.html` | Botão de rodízio por canal (visível para `chat.routing.manage`) |

---

## Tasks T.A.C.E — Fase 1 (Round Robin)

> Escopo: `feature/FEAT-052-routing-round-robin`
> Agente responsável indicado em cada task.

---

### TASK-052-01 — Migrations (DBA)

**T — Tarefa:** Criar migrations para `chat_routing_queues` e `chat_routing_queue_agents`.

**A — Arquivos:**
```
api/database/migrations/2026_05_XX_000001_create_chat_routing_queues_table.php
api/database/migrations/2026_05_XX_000002_create_chat_routing_queue_agents_table.php
```

**C — Comportamento:**
- Antes: tabelas inexistentes
- Depois:
  - `chat_routing_queues`: `id`, `tenant_id` (FK→tenants), `instance_id` (UUID nullable FK→chat_instances), `name`, `is_enabled` (bool default false), `strategy` (enum round_robin|least_busy|skill_based default round_robin), `max_open_tickets_per_agent` (int nullable), timestamps. UNIQUE parciais + índices conforme spec.
  - `chat_routing_queue_agents`: `id`, `queue_id` (FK→chat_routing_queues onDelete cascade), `user_id` (FK→auth_users), `position` (int NOT NULL default 0), `last_assigned_at` (timestamp nullable), `is_active` (bool NOT NULL default true), timestamps. UNIQUE(queue_id, user_id). Índice composto `(queue_id, is_active, last_assigned_at)`.

**E — Evidência:**
- `php artisan migrate` sem erros
- `php artisan migrate:rollback` e re-migrate sem erros
- Constraints UNIQUE e índices verificados via `\d chat_routing_queues` no psql

---

### TASK-052-02 — Models Eloquent (BACKEND)

**T — Tarefa:** Criar `ChatRoutingQueue` e `ChatRoutingQueueAgent` com relacionamentos e casts.

**A — Arquivos:**
```
api/src/Domain/Chat/Models/ChatRoutingQueue.php
api/src/Domain/Chat/Models/ChatRoutingQueueAgent.php
```

**C — Comportamento:**
- Antes: modelos inexistentes
- Depois:
  - `ChatRoutingQueue`: `BelongsToTenant`, `$fillable` completo, cast `is_enabled→boolean`, cast `strategy→string`, relacionamento `hasMany(ChatRoutingQueueAgent)`, relacionamento `belongsTo(ChatInstance, 'instance_id')` nullable, scope `forInstance(string $instanceId)`, scope `global()`.
  - `ChatRoutingQueueAgent`: `$fillable` completo, cast `is_active→boolean`, cast `last_assigned_at→datetime`, relacionamento `belongsTo(ChatRoutingQueue)`, relacionamento `belongsTo(AuthUser, 'user_id')`.

**E — Evidência:**
- `ChatRoutingQueue::global()->first()` retorna fila com `instance_id IS NULL`
- `ChatRoutingQueue::forInstance($id)->first()` retorna fila do canal
- Relacionamentos resolvem sem N+1 em eager load

---

### TASK-052-03 — ChatRoutingService (BACKEND)

**T — Tarefa:** Implementar `ChatRoutingService` com métodos `route()` e `roundRobin()`.

**A — Arquivo:**
```
api/src/Domain/Chat/Services/ChatRoutingService.php
```

**C — Comportamento:**
- Antes: serviço inexistente
- Depois:
  - `route(ChatTicket $ticket): ?string` — resolve fila (canal → global → null) e despacha para a estratégia configurada
  - `roundRobin(ChatRoutingQueue $queue): ?string` — executa `SELECT ... FOR UPDATE SKIP LOCKED LIMIT 1` ordenado por `last_assigned_at ASC NULLS FIRST, position ASC`; atualiza `last_assigned_at = now()`; retorna `user_id` ou `null` se nenhum agente ativo disponível
  - Toda a operação de `roundRobin()` ocorre dentro de uma transação de banco

**E — Evidência:**
- Testes unitários do `ChatRoutingServiceTest` passando (ver seção Testes do spec)
- Teste de concorrência (10 coroutines, 5 agentes) → sem duplicatas
- `route()` retorna `null` quando `is_enabled=false`

---

### TASK-052-04 — Policy de Autorização (BACKEND)

**T — Tarefa:** Criar `ChatRoutingQueuePolicy` com gates de permissão.

**A — Arquivo:**
```
api/src/Domain/Chat/Policies/ChatRoutingQueuePolicy.php
```

**C — Comportamento:**
- Antes: política inexistente — qualquer usuário autenticado poderia chamar os endpoints
- Depois:
  - `view()`: requer `chat.routing.view`
  - `manage()`: requer `chat.routing.manage`
  - Validação cross-tenant: `user_id` de agente deve pertencer ao mesmo `tenant_id` da fila → aborta 403
  - Policy registrada em `AuthServiceProvider`

**E — Evidência:**
- Request com agente de outro tenant → 403
- Request com role sem `chat.routing.manage` → 403 no POST/PUT/DELETE
- Request com role com permissão → 200/201

---

### TASK-052-05 — Controller, Request e Resource (BACKEND)

**T — Tarefa:** Criar camada HTTP para a API de routing queue.

**A — Arquivos:**
```
api/src/Domain/Chat/Http/Controllers/ChatRoutingQueueController.php
api/src/Domain/Chat/Http/Requests/ChatRoutingQueueRequest.php
api/src/Domain/Chat/Http/Resources/ChatRoutingQueueResource.php
```

**C — Comportamento:**
- Antes: endpoints inexistentes
- Depois: controller com métodos `showChannel`, `storeChannel`, `updateChannel`, `showGlobal`, `storeGlobal`, `updateGlobal`, `indexAgents`, `storeAgent`, `destroyAgent`, `reorderAgents`
- `ChatRoutingQueueRequest` valida: `name` (string max 100), `strategy` (in: round_robin,least_busy,skill_based), `is_enabled` (bool), `max_open_tickets_per_agent` (int nullable min 1), `user_id` (uuid exists:auth_users), `agents` (array), `agents.*.user_id` (uuid), `agents.*.position` (int min 0)
- `ChatRoutingQueueResource` serializa fila + agentes em array ordenado por `position`

**E — Evidência:**
- `GET /chat/channels/{id}/routing-queue` → 200 com estrutura correta ou 404 se não existe
- `POST /chat/routing-queue/global` → 201 com fila criada
- `PUT /chat/routing-queue/global/agents/reorder` → 200, positions atualizadas na ordem enviada
- Testes HTTP do `ChatRoutingQueueControllerTest` passando

---

### TASK-052-06 — Rotas (BACKEND)

**T — Tarefa:** Registrar os novos endpoints em `chat.php`.

**A — Arquivo:**
```
api/src/Domain/Chat/Routes/chat.php
```

**C — Comportamento:**
- Antes: sem rotas de routing queue
- Depois: dois grupos adicionados dentro do middleware `auth:sanctum`:
  - Grupo 1: `Route::prefix('channels/{id}/routing-queue')` — endpoints por canal
  - Grupo 2: `Route::prefix('routing-queue/global')` — endpoints globais (fora do prefix `channels`)
  - Ambos protegidos pelo middleware de policy `ChatRoutingQueuePolicy`

**E — Evidência:**
- `php artisan route:list | grep routing-queue` lista todos os endpoints esperados
- Nenhuma rota existente quebrada

---

### TASK-052-07 — Hook em CreateChatTicketAction (BACKEND)

**T — Tarefa:** Adicionar chamada ao `ChatRoutingService` após criação do ticket.

**A — Arquivo:**
```
api/src/Domain/Chat/Actions/CreateChatTicketAction.php
```

**C — Comportamento:**
- Antes: ticket criado sem atribuição automática
- Depois: após `$ticket = $this->createTicket($dto)`, executa bloco try/catch com `DB::transaction()` que chama `ChatRoutingService::route($ticket)` e, se retornar `user_id`, chama `AssignChatTicketAction::transfer($ticket, $userId, null)`
- Falha no routing → loga erro, ticket permanece sem `assigned_to`, fluxo não interrompido
- `ChatRoutingService` e `AssignChatTicketAction` injetados via construtor

**E — Evidência:**
- Ticket criado com fila ativa → `assigned_to` preenchido no banco
- Ticket criado sem fila → `assigned_to` null, sem exceção
- Testes de `CreateChatTicketActionTest` passando

---

### TASK-052-08 — ChatRoutingQueueService Angular (FRONTEND)

**T — Tarefa:** Criar service Angular para consumir a API de routing queue.

**A — Arquivo:**
```
app/src/app/pages/chat/services/chat-routing-queue.service.ts
```

**C — Comportamento:**
- Antes: service inexistente
- Depois: injectable com signals `queue`, `agents`, `loading`, `error`. Métodos:
  - `loadGlobal()` → GET `/chat/routing-queue/global`
  - `loadForChannel(id: string)` → GET `/chat/channels/{id}/routing-queue`
  - `save(scope, data)` → POST ou PUT conforme existência
  - `addAgent(scope, userId, position?)` → POST `.../agents`
  - `removeAgent(scope, userId)` → DELETE `.../agents/{user}`
  - `reorder(scope, agents)` → PUT `.../agents/reorder`
- Cada chamada bem-sucedida atualiza os signals correspondentes

**E — Evidência:**
- Testes Vitest do service passando (ver spec)
- `loadGlobal()` → signal `queue` atualizado com resposta da API
- `addAgent()` → signal `agents` contém o novo agente

---

### TASK-052-09 — Página `chat/configuration` (FRONTEND)

**T — Tarefa:** Criar página de configuração global de rodízio.

**A — Arquivos:**
```
app/src/app/pages/chat/configuration/chat-configuration.ts
app/src/app/pages/chat/configuration/chat-configuration.html
app/src/app/pages/chat/configuration/components/routing-agent-list/
app/src/app/pages/chat/configuration/components/routing-agent-form/
```

**C — Comportamento:**
- Antes: página inexistente
- Depois: página com `af-page-title` + seção de rodízio contendo:
  - Toggle `is_enabled` com auto-save
  - Seletor de `strategy` (round_robin ativo; least_busy e skill_based desabilitados com tooltip "Em breve")
  - Lista de agentes com drag-and-drop para reordenar (`position`), toggle `is_active` inline, botão remover
  - Botão "+ Adicionar agente" → dropdown de usuários do tenant (excluindo já adicionados)
- Padrão visual idêntico a `media-transcription` (`space-y-6`, cards com borda)

**E — Evidência:**
- Rota `/chat/configuration` carrega sem erro
- Toggle ativar → PATCH para API → feedback visual
- Arrastar agente → PUT reorder → nova posição persistida
- Usuário sem `chat.routing.manage` → redirect (guard ativo)

---

### TASK-052-10 — Componente `channel-routing` (FRONTEND)

**T — Tarefa:** Criar modal de override de rodízio por canal.

**A — Arquivos:**
```
app/src/app/pages/chat/channel/components/channel-routing/channel-routing.ts
app/src/app/pages/chat/channel/components/channel-routing/channel-routing.html
```

**C — Comportamento:**
- Antes: modal inexistente
- Depois: modal disparado pelo botão `users-round` na tabela de canais
  - Toggle "Sobrepor configuração global para este canal"
  - Quando `false`: badge "Usando configuração global", campos ocultos
  - Quando `true`: exibe mesmos campos da página global (strategy + lista de agentes), mas com escopo do canal
  - Ao fechar sem salvar: sem side effects

**E — Evidência:**
- Botão `users-round` visível na lista de canais para role com `chat.routing.manage`
- Toggle OFF → badge exibido, campos ocultos
- Toggle ON → campos visíveis, salvar → fila do canal criada/atualizada
- Testes Vitest do `ChannelRoutingComponent` passando

---

### TASK-052-11 — Rota e Menu Angular (FRONTEND)

**T — Tarefa:** Registrar rota `chat/configuration` e item de menu.

**A — Arquivos:**
```
app/src/app/app.routes.ts
app/src/app/layout/components/sidenav/menu-config.ts
```

**C — Comportamento:**
- `app.routes.ts`: nova entrada com `path: 'chat/configuration'`, `loadComponent` apontando para `ChatConfigurationComponent`, `data: { title: 'Configuração', permission: 'chat.routing.manage' }`
- `menu-config.ts`: novo item `{ type: 'item', label: 'Configuração', link: '/chat/configuration', iconName: 'settings', requiredPermission: 'chat.routing.manage' }` no grupo Chat, após "Canais"

**E — Evidência:**
- Item "Configuração" aparece no sidebar para admin/gestor
- Item ausente para role sem `chat.routing.manage`
- Navegação direta via URL → guard redireciona usuário sem permissão

---

### TASK-052-12 — Testes Backend (QA)

**T — Tarefa:** Implementar suíte de testes Pest para o backend de rodízio.

**A — Arquivos:**
```
api/tests/Feature/Chat/ChatRoutingServiceTest.php
api/tests/Feature/Chat/CreateChatTicketActionRoutingTest.php
api/tests/Feature/Chat/ChatRoutingQueueControllerTest.php
```

**C — Comportamento:**
- Antes: sem testes para routing
- Depois: cobertura completa dos cenários da seção Testes do spec
- `ChatRoutingServiceTest` marcado com `@group integration` (requer PostgreSQL real para SKIP LOCKED)
- Demais testes rodam com banco de testes padrão do projeto

**E — Evidência:**
- `./vendor/bin/pest --group=integration` → todos passando
- `./vendor/bin/pest` → zero regressão em testes existentes
- Coverage de `ChatRoutingService` ≥ 80%

---

### TASK-052-13 — Testes Frontend (QA)

**T — Tarefa:** Implementar testes Vitest para o service e componentes de routing.

**A — Arquivos:**
```
app/src/app/pages/chat/services/chat-routing-queue.service.spec.ts
app/src/app/pages/chat/channel/components/channel-routing/channel-routing.spec.ts
```

**C — Comportamento:**
- Antes: sem testes para routing no frontend
- Depois: testes com `vi.spyOn` para HTTP e signal assertions
- Cenários: ver seção "Frontend (Vitest)" do spec

**E — Evidência:**
- `pnpm run gate:test` → todos passando, zero regressão
- Coverage dos novos componentes ≥ 70%

---

### TASK-052-14 — CHANGELOG e MEMORY (DOC)

**T — Tarefa:** Registrar a conclusão da Fase 1 no CHANGELOG e decisões relevantes na MEMORY.

**A — Arquivos:**
```
.context/DOCS/CHANGELOG/2026-05-XX.md
.context/DOCS/MEMORY/2026-05-XX-rodizio-atendimentos-decisoes.md
.context/ARCHITECTURE/project-state.yaml
```

**C — Comportamento:**
- CHANGELOG: o que mudou (tabelas criadas, arquivos novos, endpoints, frontend), quais arquivos afetados
- MEMORY: decisões tomadas (SKIP LOCKED vs lockForUpdate, scope via instance_id nullable, config global + override por canal, transação única para routing+assign)
- `project-state.yaml`: incrementar `features_completed`, atualizar status do módulo `Chat`

**E — Evidência:**
- Arquivo de CHANGELOG criado com data correta e conteúdo factual
- Arquivo de MEMORY criado com alternativas e motivos das decisões
- `project-state.yaml` reflete FEAT-052 Fase 1 como concluída
