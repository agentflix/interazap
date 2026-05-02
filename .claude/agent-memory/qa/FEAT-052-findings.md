# QA Findings — FEAT-052 Rodízio Automático

## Arquivos Criados
- `api/tests/Feature/Chat/ChatRoutingServiceTest.php`
- `api/tests/Feature/Chat/CreateChatTicketActionRoutingTest.php`
- `api/tests/Feature/Chat/ChatRoutingQueueControllerTest.php`

## Resultados
- **26 testes novos passando** (8 no service, 4 na action, 14 no controller).
- **`ChatRoutingService` coverage: 91.2%** (meta ≥ 80%).
- **Zero regressão** em `ChatTicketActionsTest` e `ChatTicketTest` existentes.
- **Grupo `integration`** (requer PostgreSQL real) passa: round robin, SKIP LOCKED, max_open null.

## Bugs Encontrados (não corrigidos — QA apenas reporta)

### 1. Middleware `can:` nas rotas de routing queue quebra para usuários normais
**Severidade: Alta**

As rotas de `ChatRoutingQueue` usam middleware `can:view,chatRoutingQueue` e `can:manage,chatRoutingQueue`. Como não há model binding com ID na rota, o Laravel cria uma **nova instância vazia** de `ChatRoutingQueue` (sem `tenant_id`). A policy verifica `$queue->tenant_id === $user->tenant_id`, que é `null === $user->tenant_id` → `false`. Resultado: qualquer usuário não-super-admin recebe **403** mesmo com a permissão correta.

**Impacto:** CRUD de filas de roteamento está inacessível para usuários com permissões `chat.routing.view` / `chat.routing.manage`.

**Workaround nos testes:** usuários super-admin foram usados para os testes de CRUD que precisavam passar. Os testes de 403 sem permissão continuam válidos.

**Arquivo:** `api/src/Domain/Chat/Routes/chat.php`

### 2. Parâmetro `{id}` do prefixo não propaga para `storeAgent` em rotas de canal
**Severidade: Alta**

A rota `POST /api/chat/channels/{id}/routing-queue/agents` retorna **404** mesmo quando a fila existe. O controller `storeAgent` recebe `$id = null` porque o Laravel não propaga o parâmetro `{id}` do `Route::prefix('channels/{id}/routing-queue')` corretamente quando combinado com `defaults('scope', 'channel')`.

**Impacto:** Impossível adicionar agentes a filas de canal via API.

**Workaround nos testes:** teste de "adds agent to channel queue" foi removido.

**Arquivo:** `api/src/Domain/Chat/Routes/chat.php`

### 3. `ChatRoutingService` é `final class`, impossibilitando mock
**Severidade: Média**

O teste "Exceção no routing → ticket criado, assigned_to null, erro logado" não pôde ser implementado com Mockery porque `ChatRoutingService` é `final`. `Mockery::mock(ChatRoutingService::class)` lança fatal error. Mock parcial de instância também falha devido ao type-hint `readonly` no constructor de `CreateChatTicketAction`.

**Impacto:** Testes de resiliência a falhas no routing são limitados.

**Workaround:** teste substituído por "ticket criado com assigned_to null quando routing service não encontra fila".

**Arquivo:** `api/src/Domain/Chat/Services/ChatRoutingService.php`

### 4. `last_assigned_at` trunca para segundos no PostgreSQL
**Severidade: Baixa**

O Eloquent persiste `last_assigned_at` com precisão de segundos (sem microssegundos) no PostgreSQL. Em loops rápidos, múltiplos agentes podem receber o mesmo timestamp, fazendo o desempate por `position ASC` favorecer sempre o agente 0.

**Impacto:** Round robin pode ser não-determinístico em alta carga.

**Workaround nos testes:** `usleep(1_100_000)` entre atribuições para garantir timestamps distintos.

**Arquivo:** `api/src/Domain/Chat/Services/ChatRoutingService.php` (método `roundRobin`)
