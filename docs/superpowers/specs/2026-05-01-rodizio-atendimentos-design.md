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
