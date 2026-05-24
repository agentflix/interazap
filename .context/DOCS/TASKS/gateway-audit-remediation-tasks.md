# Tasks: gateway-audit-remediation

**Feature:** FEAT-004
**Feature doc:** `.context/DOCS/FEATURES/gateway-audit-remediation.md`
**PRD:** `.context/DOCS/PRDS/0004-PRD-gateway-audit-remediation.md`
**Criado em:** 2026-05-24

---

## Legenda de Status

- Pendente
- Em Progresso
- Concluida
- Bloqueada

---

## FASE 1 — Quick Wins (tasks independentes, podem correr em sequencia curta)

### TASK-1.1.1 — Fix lint/Prettier nos 3 arquivos do gateway

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Corrigir falhas de formatacao Prettier em `billing-usage-client.service.ts:89`, `billing-usage.metrics.ts:50` e `jest.setup.ts:2` para que `pnpm --filter gateway lint` passe sem erros.

**A — Arquivos:**
- `gateway/src/domains/billing/services/billing-usage-client.service.ts`
- `gateway/src/domains/billing/metrics/billing-usage.metrics.ts`
- `gateway/jest.setup.ts`

**C — Comportamento:**
Antes: `pnpm --filter gateway lint` falha com erros de Prettier nas linhas indicadas.
Depois: `pnpm --filter gateway lint` passa sem erros nos 3 arquivos.

**E — Evidencia:**
`pnpm --filter gateway lint` retorna exit code 0 sem mencionar os 3 arquivos.

---

### TASK-1.2.1 — Adicionar InternalApiKeyGuard em MetricsController

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Adicionar `@UseGuards(InternalApiKeyGuard)` ao `MetricsController` para proteger `GET /v1/metrics` com autenticacao por API key, igual ao padrao de `channels.controller.ts:29`.

**A — Arquivos:**
- `gateway/src/metrics/metrics.controller.ts`

**C — Comportamento:**
Antes: `GET /v1/metrics` responde 200 sem autenticacao. Labels Prometheus com tenant e agent_id expostos publicamente.
Depois: `GET /v1/metrics` sem header `x-api-key` retorna 401. Com header correto retorna 200.

**E — Evidencia:**
- `curl /v1/metrics` (sem header) retorna HTTP 401.
- `curl -H "x-api-key: {INTERNAL_API_KEY}" /v1/metrics` retorna HTTP 200 com conteudo Prometheus.
- `pnpm --filter gateway build` passa.

---

### TASK-1.3.1 — Corrigir session hijack no WebChatGateway

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Corrigir `handleWebChatJoin` e `handleWebChatLeave` em `webchat.gateway.ts` para rejeitar `data.sessionId` do cliente e usar exclusivamente `clientData.sessionId` (proveniente do JWT decodificado). Adicionar spec cobrindo a tentativa de hijack.

**A — Arquivos:**
- `gateway/src/domains/realtime/gateways/webchat.gateway.ts` (linhas 132 e 194)
- `gateway/src/domains/realtime/gateways/webchat.gateway.spec.ts` (novo ou atualizar existente)

**C — Comportamento:**
Antes: `handleWebChatJoin` aceita `data?.sessionId ?? clientData.sessionId` — cliente pode injetar sessionId externo.
Depois: `handleWebChatJoin` usa somente `clientData.sessionId`; se ausente, desconecta o socket e retorna. Mesmo padrao em `handleWebChatLeave`.

**E — Evidencia:**
- Spec: conexao com token JWT valido + `data.sessionId` de outra sessao retorna erro/desconexao sem entrar na room.
- Spec: conexao com token JWT valido + `data.sessionId` correspondente ao JWT → entra na room com sucesso.
- `pnpm --filter gateway test` passa.

---

### TASK-1.4.1 — Atualizar dependencias com CVEs HIGH (pnpm audit)

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Atualizar as dependencias do gateway que tem CVEs HIGH: `axios`, `@nestjs/core`, `@nestjs/common`, `@nestjs/platform-express`, `express`. Verificar se dependencias transitivas (ws, uuid, fast-xml-builder) sao resolvidas automaticamente; adicionar `overrides` em `package.json` se necessario. Rodar build e testes para garantir ausencia de regressoes de tipos.

**A — Arquivos:**
- `gateway/package.json`
- `gateway/pnpm-lock.yaml` (gerado automaticamente)

**C — Comportamento:**
Antes: `pnpm audit --prod` reporta 8 HIGH CVEs (axios CVE-2026-40175, @nestjs/* CVE-2026-4926, express CVE-2026-8723, transitivos).
Depois: `pnpm audit --prod` reporta 0 HIGH CVEs. Build e testes passam.

**E — Evidencia:**
- `pnpm audit --prod` no diretorio gateway retorna zero HIGH CVEs.
- `pnpm --filter gateway build` passa sem erros de tipo.
- `pnpm --filter gateway test` passa.

---

## FASE 2 — Infraestrutura de Cliente HTTP Interno + Cache

### TASK-2.1.1 — Criar InternalApiModule com InternalApiClientService

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Criar o modulo global `InternalApiModule` com `InternalApiClientService` — cliente axios configurado com HTTP keep-alive, connection pooling, retries exponenciais, circuit breaker simples (5 erros em 10s) e histograma Prometheus `gateway_internal_api_duration_seconds{operation,status_code}`.

**A — Arquivos:**
- `gateway/src/infrastructure/internal-api/internal-api.module.ts` (novo)
- `gateway/src/infrastructure/internal-api/internal-api-client.service.ts` (novo)
- `gateway/src/infrastructure/internal-api/internal-api.types.ts` (novo — interfaces de response)
- `gateway/src/infrastructure/internal-api/internal-api-client.service.spec.ts` (novo)
- `gateway/src/app.module.ts` (adicionar `InternalApiModule` como global)

**C — Comportamento:**
Antes: nenhum cliente HTTP interno centralizado no gateway; cada servico usa axios ad-hoc ou DatabaseService.
Depois: `InternalApiClientService` disponivel em toda a aplicacao via injecao de dependencia. Metodos `get<T>(path, operation)`, `post<T>(path, body, operation)`, `patch<T>(path, body, operation)` com retry e circuit breaker. Header `x-api-key` injetado automaticamente.

**E — Evidencia:**
- Spec: mock axios — sucesso retorna dados tipados; erro 5xx com 2 retries antes de falhar; circuit breaker abre apos 5 erros consecutivos e retorna erro sem chamar axios.
- Histograma `gateway_internal_api_duration_seconds` registrado no registro Prometheus default.
- `pnpm --filter gateway build` passa.
- `pnpm --filter gateway test` passa.

---

### TASK-2.2.1 — Criar GatewayCacheModule com GatewayCacheService (L1 LRU + L2 Redis)

**Modo BUILDER:** `gateway`
**Status:** ✅ Concluída

**T — Tarefa:**
Criar `GatewayCacheModule` global com `GatewayCacheService` — facade de cache em duas camadas: L1 LRU em memoria (lru-cache) e L2 Redis. Implementar `getOrFetch<T>`, subscriber de invalidacao Redis pub/sub (`cache:invalidate:instance`), e contadores Prometheus `gateway_cache_hits_total{level,operation}` e `gateway_cache_misses_total{operation}`.

**A — Arquivos:**
- `gateway/src/infrastructure/cache/gateway-cache.module.ts` (novo)
- `gateway/src/infrastructure/cache/gateway-cache.service.ts` (novo)
- `gateway/src/infrastructure/cache/gateway-cache.types.ts` (novo — CacheStrategy, CacheEntry)
- `gateway/src/infrastructure/cache/gateway-cache.service.spec.ts` (novo)
- `gateway/src/app.module.ts` (adicionar `GatewayCacheModule` como global)
- `gateway/package.json` (adicionar `lru-cache` se nao presente)

**C — Comportamento:**
Antes: cada servico gerencia seu proprio cache Redis ad-hoc (ex: BillingTenantResolverService tem Redis direto).
Depois: `GatewayCacheService.getOrFetch(key, fetcher, strategy)` — verifica L1, depois L2, executa fetcher se miss, popula ambas as camadas. Invalidacao via pub/sub limpa L1 e L2 do key pattern recebido. Contadores Prometheus incrementados em cada hit/miss.

**E — Evidencia:**
- Spec: L1 hit — fetcher nao chamado, retorna em < 1ms (mock).
- Spec: L1 miss + L2 hit — L1 populado, fetcher nao chamado.
- Spec: L1 miss + L2 miss — fetcher chamado, ambas as camadas populadas.
- Spec: mensagem pub/sub `cache:invalidate:instance:{id}` — entradas correspondentes removidas de L1 e L2.
- Contadores `gateway_cache_hits_total` e `gateway_cache_misses_total` registrados no registro Prometheus default.
- `pnpm --filter gateway build` e `pnpm --filter gateway test` passam.

---

## FASE 3 — Migracao por Dominio

### TASK-3.1.1 — Criar endpoints internos api/ para dominio chat (5 endpoints)

**Modo BUILDER:** `backend`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE

**T — Tarefa:**
Criar 5 endpoints internos na api/ para substituir os call-sites de DB direto do gateway no dominio chat. Todos protegidos por `InternalApiKeyGuard` (ou middleware equivalente de `x-api-key`). Seguir padrao de rotas internas existentes (`GET /api/internal/chat/instances/by-waba/{wabaId}`).

Endpoints a criar:
1. `GET /api/internal/chat/instances/by-webhook-token/{token}` — lookup primario (webhook_token) + fallback (settings_json->>'token')
2. `GET /api/internal/chat/instances/{id}` — lookup por id (verificar se ja existe)
3. `PATCH /api/internal/chat/instances/{id}/connection-status` — update status + last_connection
4. `GET /api/internal/chat/instances?tenant_id={}&provider={}&status={}` — listing filtrado
5. `GET /api/internal/realtime/room-access?room={}&tenant_id={}` — valida ownership de ticket ou run

**A — Arquivos (api/):**
- `api/src/Domain/Chat/Http/Controllers/Internal/InternalChatInstanceController.php` (novo ou adicionar metodos)
- `api/src/Domain/Chat/Actions/Internal/ResolveInstanceByWebhookTokenAction.php` (novo)
- `api/src/Domain/Chat/Actions/Internal/UpdateInstanceConnectionStatusAction.php` (novo)
- `api/src/Domain/Chat/Routes/internal.php` (atualizar)
- `api/src/Domain/Chat/Http/Controllers/Internal/InternalRoomAccessController.php` (novo)
- `api/src/Domain/Chat/Actions/Internal/ValidateRoomAccessAction.php` (novo)
- Testes Pest para os novos endpoints

**C — Comportamento:**
Antes: nenhum desses endpoints existe; gateway acessa DB diretamente.
Depois: 5 endpoints funcionais com autenticacao, retornando JSON conforme contratos definidos no feature doc. `ValidateRoomAccessAction` faz SELECT em `chat_tickets` ou `ai_autopilot_runs` com tenant scope.

**E — Evidencia:**
- Testes Pest: cada endpoint retorna 200 com payload correto para registro existente.
- Testes Pest: cada endpoint retorna 404 para registro inexistente.
- Testes Pest: endpoint sem `x-api-key` correto retorna 401.
- `composer gate:fast` passa.

---

### TASK-3.1.2 — Criar endpoints internos api/ para dominio billing (3 endpoints)

**Modo BUILDER:** `backend`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE

**T — Tarefa:**
Criar 3 endpoints internos na api/ para substituir os call-sites de DB direto do gateway no dominio billing.

Endpoints a criar:
1. `GET /api/internal/billing/tenants/by-webhook-token/{token}` — resolve tenant por billing_webhook_token
2. `POST /api/internal/billing/webhook-events` — registra evento de webhook de billing
3. `PATCH /api/internal/billing/webhook-events/{eventId}/stream-id` — atualiza stream_id de evento

**A — Arquivos (api/):**
- `api/src/Domain/Billing/Http/Controllers/Internal/InternalBillingController.php` (novo)
- `api/src/Domain/Billing/Actions/Internal/ResolveTenantByBillingTokenAction.php` (novo)
- `api/src/Domain/Billing/Actions/Internal/CreateBillingWebhookEventAction.php` (novo)
- `api/src/Domain/Billing/Actions/Internal/UpdateBillingWebhookEventStreamIdAction.php` (novo)
- `api/src/Domain/Billing/Routes/internal.php` (novo ou atualizar)
- Testes Pest para os novos endpoints

**C — Comportamento:**
Antes: nenhum desses endpoints existe; gateway acessa tabelas `platform_tenants` e `billing_webhook_events` diretamente via pg.Pool.
Depois: 3 endpoints funcionais com autenticacao e tenant scope.

**E — Evidencia:**
- Testes Pest: cada endpoint retorna resposta correta para payload valido.
- Testes Pest: 404 para token/id inexistente; 401 sem API key.
- `composer gate:fast` passa.

---

### TASK-3.2.1 — Migrar dominio chat no gateway: instance-resolver + channels + connection-status

**Modo BUILDER:** `gateway`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE
**Depende de:** TASK-2.1.1, TASK-2.2.1, TASK-3.1.1

**T — Tarefa:**
Substituir `DatabaseService` por `InternalApiClient` + `GatewayCacheService` em 3 servicos do dominio chat do gateway.

Migracoes:
1. `instance-resolver.service.ts:297-320` — substituir `findByAnyToken()` (queries DB) por chamada `InternalApiClient.get('/api/internal/chat/instances/by-webhook-token/{token}', 'instance_resolve')` com cache L1(30s)+L2(120s) via `GatewayCacheService.getOrFetch`.
2. `channels.controller.ts:223` — substituir SELECT direto por `InternalApiClient.get('/api/internal/chat/instances/{id}', 'instance_by_id')` com cache L2(300s).
3. `connection-status.service.ts:144` — substituir UPDATE direto por enfileiramento de `UpdateConnectionStatusJob` via BullMQ (fire-and-forget). Criar `UpdateConnectionStatusProcessor`.

**A — Arquivos:**
- `gateway/src/domains/chat/services/instance-resolver.service.ts`
- `gateway/src/domains/chat/channels.controller.ts`
- `gateway/src/domains/chat/services/connection-status.service.ts`
- `gateway/src/domains/chat/processors/update-connection-status.processor.ts` (novo)
- `gateway/src/domains/chat/chat.module.ts` (registrar processor)
- Testes unitarios dos 3 servicos (mock InternalApiClient + GatewayCacheService)

**C — Comportamento:**
Antes: 3 servicos injetam `DatabaseService` e executam SQL diretamente no PostgreSQL.
Depois: `DatabaseService` removido dos 3 servicos. `instance-resolver` usa cache L1+L2 antes de HTTP. `connection-status` enfileira job sem bloquear. `channels.controller` usa HTTP com cache L2.

**E — Evidencia:**
- Spec instance-resolver: L1 hit retorna sem chamar `InternalApiClient`.
- Spec instance-resolver: L2 hit popula L1 sem chamar `InternalApiClient`.
- Spec instance-resolver: miss total chama `InternalApiClient.get` e popula cache.
- Spec connection-status: enfileira `UpdateConnectionStatusJob` sem await.
- Nenhum `import.*DatabaseService` nos 3 arquivos migrados.
- `pnpm --filter gateway test` passa.

---

### TASK-3.3.1 — Migrar dominio billing no gateway: tenant-resolver + webhook + collection

**Modo BUILDER:** `gateway`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE
**Depende de:** TASK-2.1.1, TASK-2.2.1, TASK-3.1.2

**T — Tarefa:**
Substituir `DatabaseService` por `InternalApiClient` + `GatewayCacheService` em 3 servicos do dominio billing do gateway.

Migracoes:
1. `billing-tenant-resolver.service.ts:41` — substituir query SQL por `InternalApiClient.get('/api/internal/billing/tenants/by-webhook-token/{token}', 'billing_tenant_resolve')`. Cache L2 Redis TTL 120s ja existe no servico — adaptar para usar `GatewayCacheService`.
2. `billing-webhook.service.ts:343` — substituir INSERT por `InternalApiClient.post('/api/internal/billing/webhook-events', payload, 'billing_event_create')`. Substituir UPDATE stream_id por `InternalApiClient.patch('/api/internal/billing/webhook-events/{id}/stream-id', ...)`.
3. `billing-collection.service.ts:88` — substituir SELECT por `InternalApiClient.get('/api/internal/chat/instances?...', 'billing_instances_list')` com cache L2 Redis TTL 60s.

**A — Arquivos:**
- `gateway/src/domains/billing/services/billing-tenant-resolver.service.ts`
- `gateway/src/domains/billing/services/billing-webhook.service.ts`
- `gateway/src/domains/billing/services/billing-collection.service.ts`
- Testes unitarios dos 3 servicos (mock InternalApiClient + GatewayCacheService)

**C — Comportamento:**
Antes: 3 servicos injetam `DatabaseService` e executam SQL diretamente.
Depois: `DatabaseService` removido dos 3 servicos. Cache gerenciado por `GatewayCacheService`. HTTP via `InternalApiClient`.

**E — Evidencia:**
- Spec billing-tenant-resolver: cache L2 hit retorna sem chamar `InternalApiClient`.
- Spec billing-webhook: `post` e `patch` chamam `InternalApiClient` com payloads corretos.
- Nenhum `import.*DatabaseService` nos 3 arquivos migrados.
- `pnpm --filter gateway test` passa.

---

### TASK-3.4.1 — Migrar dominio realtime no gateway: ws-room-access

**Modo BUILDER:** `gateway`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE
**Depende de:** TASK-2.1.1, TASK-2.2.1, TASK-3.1.1

**T — Tarefa:**
Substituir `DatabaseService` por `InternalApiClient` + `GatewayCacheService` em `ws-room-access.service.ts`. Usar cache L1(10s)+L2(60s) para hot path de WebSocket join.

Migracao:
- `ws-room-access.service.ts:70` (ticket ownership) → `InternalApiClient.get('/api/internal/realtime/room-access?room=ticket:{id}&tenant_id={tenantId}', 'ws_room_access')` com cache L1+L2.
- `ws-room-access.service.ts:97` (run ownership) → mesmo endpoint com `room=run:{id}`.
- Metrica: histograma `gateway_ws_room_access_duration_ms` (ou reuso de `gateway_internal_api_duration_seconds` com label operation=ws_room_access).

**A — Arquivos:**
- `gateway/src/domains/realtime/services/ws-room-access.service.ts`
- `gateway/src/domains/realtime/services/ws-room-access.service.spec.ts` (novo ou atualizar)

**C — Comportamento:**
Antes: `ws-room-access.service.ts` injeta `DatabaseService`, faz SELECT direto em `chat_tickets` e `ai_autopilot_runs`.
Depois: `DatabaseService` removido. Cache L1(10s)+L2(60s) absorve conexoes repetidas do mesmo agente ao mesmo ticket/run. HTTP via `InternalApiClient` apenas em miss total.

**E — Evidencia:**
- Spec: L1 hit para mesmo room+tenantId em janela de 10s — sem chamada HTTP.
- Spec: resultado `allowed=true` do endpoint → retorna `true`.
- Spec: resultado `allowed=false` do endpoint → retorna `false`.
- Nenhum `import.*DatabaseService` em `ws-room-access.service.ts`.
- `pnpm --filter gateway test` passa.

---

## FASE 4 — Remocao DatabaseModule + Cleanup

### TASK-4.1.1 — Remover DatabaseModule, DatabaseService e dependencia pg

**Modo BUILDER:** `gateway`
**Status:** 🔄 AGUARDANDO PHASE-CLOSE
**Depende de:** TASK-3.2.1, TASK-3.3.1, TASK-3.4.1

**T — Tarefa:**
Apos todos os call-sites migrados, remover completamente `DatabaseModule`, `DatabaseService` e a dependencia `pg` + `@types/pg` do gateway. Verificar que o build passa limpo.

**A — Arquivos:**
- `gateway/src/app.module.ts` (remover `DatabaseModule` dos imports)
- `gateway/src/infrastructure/database/database.service.ts` (deletar)
- `gateway/src/infrastructure/database/database.module.ts` (deletar)
- `gateway/src/infrastructure/database/models/` (deletar diretorio)
- `gateway/package.json` (remover `pg`, `@types/pg`)
- `gateway/pnpm-lock.yaml` (gerado apos `pnpm install`)

**C — Comportamento:**
Antes: `DatabaseModule` registrado globalmente; `DatabaseService` com pg.Pool ativo; `pg` listado em dependencies.
Depois: `DatabaseModule` nao existe. `gateway/src/infrastructure/database/` removido. `pg` ausente de `package.json`. Gateway nao abre nenhuma conexao TCP direta ao PostgreSQL.

**E — Evidencia:**
- `grep -r "DatabaseService" gateway/src/` retorna zero resultados.
- `grep -r "DatabaseModule" gateway/src/` retorna zero resultados.
- `grep '"pg"' gateway/package.json` retorna zero resultados.
- `pnpm --filter gateway build` passa sem erros.
- `pnpm --filter gateway test` passa.

---

## FASE 5 — Validacao SLO + Ajuste de Cache TTL

### TASK-5.1.1 — Validar SLO de performance e ajustar TTLs se necessario

**Modo BUILDER:** `dev`
**Status:** Pendente
**Depende de:** TASK-4.1.1

**T — Tarefa:**
Consultar os histogramas Prometheus apos deploy em staging/producao para verificar compliance com os SLOs definidos. Ajustar TTLs de cache se p95 estiver acima do target. Documentar os valores medidos em `.context/DOCS/MEMORY/2026-05-24-gateway-audit-remediation.md`.

SLOs a verificar:
- SLO-1: `histogram_quantile(0.95, gateway_internal_api_duration_seconds{operation="instance_resolve"})` <= 0.005 (5ms) em estado estacionario.
- SLO-2: `histogram_quantile(0.95, gateway_internal_api_duration_seconds{operation="ws_room_access"})` <= 0.010 (10ms) em estado estacionario.
- SLO-3: latencia de cold start (cache L1+L2 frios) <= 80ms para instance_resolve, <= 100ms para ws_room_access.
- Cache hit rate: `gateway_cache_hits_total / (gateway_cache_hits_total + gateway_cache_misses_total)` >= 0.95 para operation=instance_resolve.

Acoes possiveis:
- Se p95 > SLO: aumentar TTL L1 (ate 60s) e/ou TTL L2 (ate 300s) para instance-resolver.
- Se hit rate < 95%: aumentar max entries do LRU ou TTL L1.
- Se cold start > 80ms: investigar latencia da api/ ou do Redis.

**A — Arquivos:**
- `.context/DOCS/MEMORY/2026-05-24-gateway-audit-remediation.md` (atualizar secao de SLO medido)
- `gateway/src/infrastructure/cache/gateway-cache.service.ts` (ajustar constantes de TTL se necessario)
- `gateway/src/domains/chat/services/instance-resolver.service.ts` (ajustar constantes se necessario)

**C — Comportamento:**
Antes: SLOs apenas definidos no PRD, sem medicao real.
Depois: valores p95 medidos documentados. Gateway operando dentro dos SLOs ou com TTLs ajustados para atingi-los.

**E — Evidencia:**
- Documento de memoria atualizado com queries PromQL e valores medidos.
- Se ajuste de TTL foi necessario: `pnpm --filter gateway build` e `pnpm --filter gateway test` passam com novos valores.
- `gateway_cache_hits_total{operation="instance_resolve"}` presente no endpoint de metricas.

---

## Sumario de Tasks

| Task | Fase | Dominio | Modo | Depende de |
|---|---|---|---|---|
| TASK-1.1.1 | 1 | gateway | gateway | — |
| TASK-1.2.1 | 1 | gateway | gateway | — |
| TASK-1.3.1 | 1 | gateway | gateway | — |
| TASK-1.4.1 | 1 | gateway | gateway | — |
| TASK-2.1.1 | 2 | gateway | gateway | — |
| TASK-2.2.1 | 2 | gateway | gateway | — |
| TASK-3.1.1 | 3 | api | backend | — |
| TASK-3.1.2 | 3 | api | backend | — |
| TASK-3.2.1 | 3 | gateway | gateway | 2.1.1, 2.2.1, 3.1.1 |
| TASK-3.3.1 | 3 | gateway | gateway | 2.1.1, 2.2.1, 3.1.2 |
| TASK-3.4.1 | 3 | gateway | gateway | 2.1.1, 2.2.1, 3.1.1 |
| TASK-4.1.1 | 4 | gateway | gateway | 3.2.1, 3.3.1, 3.4.1 |
| TASK-5.1.1 | 5 | gateway | dev | 4.1.1 |

**Total: 13 tasks em 5 fases**
