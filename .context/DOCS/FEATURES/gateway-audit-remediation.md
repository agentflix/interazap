# Feature: gateway-audit-remediation

**Status:** [ ] Em planejamento | [ ] Em execucao | [ ] Concluida
**Data:** 2026-05-24
**PRD:** `.context/DOCS/PRDS/0004-PRD-gateway-audit-remediation.md`

## Metadados

- ID: FEAT-004
- PRD: `.context/DOCS/PRDS/0004-PRD-gateway-audit-remediation.md`
- Bounded Context: gateway/ (chat, billing, realtime, metrics, infrastructure)
- Complexidade: G (Grande — 5 fases, 10 tasks)
- Status: Em planejamento

## Visao Geral

Remediar 5 achados de auditoria no gateway NestJS 11: eliminacao de acesso direto ao PostgreSQL (7 call-sites), remediacao de 29 CVEs de dependencias (8 HIGH), correcao de session hijack no WebSocket, protecao do endpoint /v1/metrics e correcao de lint. A estrategia central e substituir `DatabaseService` por `InternalApiClient` (HTTP → api/) com cache em camadas L1 LRU + L2 Redis para preservar latencia nas hot paths.

## Modulos Afetados

- [ ] api/ (Laravel 12) — 7 endpoints internos novos
- [x] gateway/ (NestJS 11) — refactor de infraestrutura + 3 dominios + security fixes
- [ ] app/ (Angular 20) — nenhuma alteracao
- [ ] Infraestrutura — nenhuma alteracao

## Arquitetura Proposta

### Camada de infraestrutura gateway/ (nova)

```
gateway/src/infrastructure/
  internal-api/
    internal-api.module.ts        — NestJS module (global)
    internal-api-client.service.ts — axios com keep-alive + retries + circuit breaker
    internal-api.config.ts        — tipos de configuracao
  cache/
    gateway-cache.module.ts       — NestJS module (global)
    gateway-cache.service.ts      — facade L1 LRU + L2 Redis
    gateway-cache.types.ts        — interfaces CacheStrategy, CacheEntry
```

### Remocao pos-migracao

`gateway/src/infrastructure/database/` — removido completamente apos Fase 4.

### Fluxo de resolucao (instance-resolver, hot path)

```
Request webhook
  → L1 LRU (memoria, TTL 30s)
      HIT → retorna em ~0.1ms
      MISS →
  → L2 Redis (TTL 120s)
      HIT → popula L1 → retorna em ~1-2ms
      MISS →
  → HTTP GET /api/internal/chat/instances/by-webhook-token/{token}
      → popula L2 → popula L1 → retorna em ~20-60ms
      ERRO → retorna stale L2 se disponivel (stale-while-revalidate)
              senao → retorna null (webhook rejeitado)
```

### Fluxo de invalidacao de cache

```
api/ rotaciona token de instancia
  → publica Redis pub/sub: PUBLISH cache:invalidate:instance {instanceId}
gateway/
  → subscriber em GatewayCacheService
  → deleta entradas L1 e L2 para o instanceId afetado
```

## Contratos API Internos (7 endpoints novos na api/)

Todos requerem header `x-api-key: {INTERNAL_API_KEY}`. Resposta sempre JSON.

### 1. Resolve instancia por webhook token

```
GET /api/internal/chat/instances/by-webhook-token/{token}
Response 200: { instance_id, tenant_id, provider, status }
Response 404: { message: "Not found" }
```

Substitui: `instance-resolver.service.ts:297-320` (queries webhook_token + settings_json token).

Obs: este endpoint deve fazer a mesma logica de dois steps (webhook_token primary, settings_json->>'token' fallback) que o gateway fazia diretamente.

### 2. Resolve instancia por id

```
GET /api/internal/chat/instances/{id}
Response 200: { instance_id, tenant_id, provider, status, settings_json? }
Response 404: { message: "Not found" }
```

Substitui: `channels.controller.ts:223`.

Obs: endpoint pode ja existir ou estar proximo de `/api/internal/chat/instances/by-waba/{wabaId}`. Verificar antes de criar.

### 3. Update status de conexao de instancia

```
PATCH /api/internal/chat/instances/{id}/connection-status
Body: { status: string, last_connection: string (ISO 8601) }
Response 200: { ok: true }
Response 404: { message: "Not found" }
```

Substitui: `connection-status.service.ts:144` (UPDATE chat_instances).

Nota: gateway chama via BullMQ job (fire-and-forget) — nao bloqueia request principal.

### 4. Resolve tenant de billing por webhook token

```
GET /api/internal/billing/tenants/by-webhook-token/{token}
Response 200: { tenant_id, name }
Response 404: { message: "Not found" }
```

Substitui: `billing-tenant-resolver.service.ts:41`.

### 5. Registra evento de webhook de billing

```
POST /api/internal/billing/webhook-events
Body: { event_type, payload, tenant_id, provider, ... }
Response 201: { event_id }
```

Substitui: `billing-webhook.service.ts:343` (INSERT billing_webhook_events).

### 6. Update stream_id de evento de billing

```
PATCH /api/internal/billing/webhook-events/{eventId}/stream-id
Body: { stream_id: string }
Response 200: { ok: true }
```

Substitui: `billing-webhook.service.ts:343` (UPDATE stream_id).

### 7. Lista instancias WhatsApp conectadas por tenant

```
GET /api/internal/chat/instances?tenant_id={tenantId}&provider=whatsapp&status=connected
Response 200: { data: [{ instance_id, tenant_id, provider, status }] }
```

Substitui: `billing-collection.service.ts:88`.

### 8. Valida acesso a room WebSocket

```
GET /api/internal/realtime/room-access?room={room}&tenant_id={tenantId}
Response 200: { allowed: true|false }
Response 400: { message: "Invalid room format" }
```

Substitui: `ws-room-access.service.ts:70,97` (queries chat_tickets + ai_autopilot_runs).

Obs: endpoint recebe room no formato `ticket:{uuid}` ou `run:{uuid}` e faz o SELECT correto com tenant scope.

**Total: 8 endpoints** (o item 7 serve para listing com filtros, separado do endpoint 2 por id).

## InternalApiClient — Especificacao

**Arquivo:** `gateway/src/infrastructure/internal-api/internal-api-client.service.ts`

Comportamento:
- Usa `axios.create()` com `baseURL`, `timeout: 5000ms`, `headers: { x-api-key }`.
- HTTP agent com `keepAlive: true`, `maxSockets: 20`, `maxFreeSockets: 5` — elimina TCP handshake em requests subsequentes.
- Retries: 2 tentativas em erros 5xx e timeout, com jitter exponencial (50ms base).
- Metodo `get<T>`, `post<T>`, `patch<T>` tipados.
- Instrumentacao: histograma Prometheus `gateway_internal_api_duration_seconds{operation, status_code}`.
- Circuit breaker simples: apos 5 erros consecutivos em 10s, retorna erro imediato sem tentar HTTP (evita cascata).

**Arquivo:** `gateway/src/infrastructure/cache/gateway-cache.service.ts`

Comportamento:
- `GatewayCacheService` com metodos `get<T>(key)`, `set<T>(key, value, ttlSeconds)`, `del(key)`, `delPattern(pattern)`.
- L1: `lru-cache` com max 2000 entries. TTL configuravel por estrategia.
- L2: `RedisService` existente.
- `GatewayCacheService` subscreve ao canal Redis `cache:invalidate:instance` e limpa L1+L2 ao receber invalidacao.
- Metodo `getOrFetch<T>(key, fetcher, strategy)` — retorna L1 hit, L2 hit populando L1, ou executa fetcher populando ambos.
- Instrumentacao: contadores Prometheus `gateway_cache_hits_total{level,operation}` e `gateway_cache_misses_total{operation}`.

## Cache Strategies por Hot Path

| Servico | Operacao | L1 TTL | L2 TTL | L2 Key Pattern | Invalidacao |
|---|---|---|---|---|---|
| instance-resolver | by-webhook-token | 30s | 120s | `chat:instance_by_webhook_token:{token}` | pub/sub instancia |
| ws-room-access | ticket ownership | 10s | 60s | `ws:room_access:ticket:{id}:{tenantId}` | nenhuma (TTL curto) |
| ws-room-access | run ownership | 10s | 60s | `ws:room_access:run:{id}:{tenantId}` | nenhuma (TTL curto) |
| billing-tenant-resolver | by-billing-token | — | 120s | `billing.tenant_by_webhook_token:{token}` | nenhuma |
| billing-collection | WhatsApp connected | — | 60s | `billing:connected_instances:{tenantId}` | nenhuma |
| channels.controller | by-id | — | 300s | `chat:instance_by_id:{id}` | pub/sub instancia |

## BullMQ: UpdateConnectionStatusJob

**Quando:** `connection-status.service.ts` recebe UPDATE de status de instancia.
**Comportamento:** Enfileira job `update-connection-status` com payload `{ instanceId, status, lastConnection }`. Retries: 3, backoff exponencial 2s. Sem resultado esperado (fire-and-forget).
**Worker:** novo processor em `gateway/src/domains/chat/processors/update-connection-status.processor.ts`. Chama `InternalApiClient.patch('/api/internal/chat/instances/{id}/connection-status', ...)`.

## Correcao de Session Hijack (Medio-3)

**Arquivo:** `gateway/src/domains/realtime/gateways/webchat.gateway.ts`

Comportamento atual (vulneravel):
```typescript
// :132
const sessionId = data?.sessionId ?? clientData.sessionId;
```

Comportamento corrigido:
```typescript
// sessionId deve vir EXCLUSIVAMENTE do JWT decodificado (clientData),
// nunca do payload do cliente. data.sessionId e ignorado.
const sessionId = clientData.sessionId;
if (!sessionId) { socket.disconnect(); return; }
```

Igual para `handleWebChatLeave:194`.

Specs a adicionar: `webchat.gateway.spec.ts` cobrindo tentativa de hijack (token valido + sessionId de outro usuario → rejeitado).

## Protecao /v1/metrics (Medio-4)

**Arquivo:** `gateway/src/metrics/metrics.controller.ts`

Adicionar:
```typescript
@UseGuards(InternalApiKeyGuard)
@Controller({ path: 'metrics', version: '1' })
```

Canonica: `gateway/src/domains/chat/channels.controller.ts:29`.

## Remocao DatabaseModule (Fase 4)

Apos todos os call-sites migrados:
1. Remover `DatabaseModule` de `gateway/src/app.module.ts:10`.
2. Remover `gateway/src/infrastructure/database/` (arquivos: `database.service.ts`, `database.module.ts`, models/).
3. Remover `pg` e `@types/pg` de `gateway/package.json`.
4. Verificar que `pnpm --filter gateway build` passa.

## pnpm Audit — Versoes Target

```
axios                ^1.15.2
@nestjs/core         ^11.1.18
@nestjs/common       ^11.1.18
@nestjs/platform-express ^11.1.18
express              ^5.2.1
```

Dependencias transitivas (ws, uuid, fast-xml-builder) — verificar se bump das diretas resolve; senao adicionar overrides em `gateway/package.json`.

## Estrategia de Testes

### Por fase (executado no phase-close):

**Fase 1:** `pnpm --filter gateway test` — nenhum teste novo, apenas validar que guards e lint nao quebram existentes.

**Fase 2:** Testes unitarios de `InternalApiClientService` (mock axios) e `GatewayCacheService` (mock Redis + LRU). Cobertura das estrategias L1/L2 e invalidacao.

**Fase 3:** Testes unitarios de `InstanceResolverService`, `WsRoomAccessService`, `BillingTenantResolverService` — mock `InternalApiClient` e `GatewayCacheService`, sem mock de `DatabaseService`. Spec novo de `WebchatGateway` cobrindo session hijack.

**Fase 4:** `pnpm --filter gateway build` — build limpo sem `DatabaseService`. `pnpm --filter gateway test` completo.

**Fase 5:** Verificacao de SLO via histogramas Prometheus. Ajuste de TTLs se p95 acima do SLO.

### Criterios de aceite finais:

- [ ] `pnpm --filter gateway build` — zero erros
- [ ] `pnpm --filter gateway test` — zero falhas
- [ ] `pnpm audit --prod` no gateway — zero HIGH CVEs
- [ ] `grep -r "DatabaseService" gateway/src/` retorna zero resultados
- [ ] `grep -r "DatabaseModule" gateway/src/` retorna zero resultados
- [ ] GET `/v1/metrics` sem header retorna 401
- [ ] `handleWebChatJoin` com sessionId externo diferente do JWT retorna erro
- [ ] Lint/Prettier: `pnpm --filter gateway lint` — zero erros
- [ ] Prometheus: metricas `gateway_internal_api_duration_seconds` e `gateway_cache_hits_total` presentes

## Dependencias Entre Fases

```
Fase 1 — independente, pode executar em paralelo com outras fases
Fase 2 — pre-requisito para Fase 3
Fase 3 — pre-requisito para Fase 4
Fase 4 — pre-requisito para Fase 5
```

## Tasks

Ver `.context/DOCS/TASKS/gateway-audit-remediation-tasks.md`
