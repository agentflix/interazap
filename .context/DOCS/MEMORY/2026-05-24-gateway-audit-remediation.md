# Decisoes Arquiteturais: gateway-audit-remediation

**Data:** 2026-05-24
**Feature:** FEAT-004
**Autor:** Rafael Silva (PLANNER)

---

## Contexto

Auditoria identificou 5 achados no gateway NestJS 11. O achado mais grave (Alto-1) e que o gateway acessa PostgreSQL diretamente em 7 call-sites, violando a regra R01 de `dependencies.yaml` e `AGENTS.md:18`. A migracao DB→HTTP introduziria round-trip adicional que poderia regredir a performance de hot paths criticos.

---

## Decisao 1: Estrategia de cache em camadas (L1 LRU + L2 Redis)

**Decisao:** Implementar cache em duas camadas para hot paths do gateway.
- L1: lru-cache em memoria do processo NestJS (sem I/O de rede)
- L2: Redis existente (mesma VPC, ~1ms de latencia)

**Alternativas descartadas:**
- Cache somente L2 Redis: introduz ~1-2ms em todo request, mesmo hit; L1 elimina esse overhead para hot keys.
- JWT claims para ws-room-access (ticket/run ownership no token): exigiria refatorar o sistema de autenticacao e emissao de tokens, escopo grande demais para essa remediacao. Adiado.
- Endpoint batch para N tickets simultaneos: complexidade adicional sem demanda imediata; pode ser adicionado se hit rate do cache nao atingir 95%.
- Stale-while-revalidate para connection-status UPDATE: preferido BullMQ porque ja e infraestrutura existente e garante que o UPDATE nao se perde.

**Razao:** L1 LRU absorve rajadas de webhooks do mesmo sender (tipico em producao) sem nenhum I/O. L2 Redis serve como fallback com TTL mais longo e como stale-while-revalidate em caso de falha da api/.

---

## Decisao 2: GatewayCacheService como facade centralizada

**Decisao:** Criar `GatewayCacheService` centralizando logica de L1+L2 em vez de cada servico gerenciar seu proprio cache.

**Alternativa descartada:** Manter cache ad-hoc por servico (padrao atual de `BillingTenantResolverService`). Descartado por duplicacao de logica e impossibilidade de instrumentar hit rate de forma unificada.

---

## Decisao 3: connection-status UPDATE via BullMQ fire-and-forget

**Decisao:** `connection-status.service.ts` nao bloqueia o request principal enquanto atualiza o status da instancia. Enfileira `UpdateConnectionStatusJob` e retorna imediatamente.

**Razao:** UPDATE de status e eventual — toleravel latencia de segundos. Bloquear o webhook ingress por um UPDATE nao-critico seria regressao de performance desnecessaria.

**Risco aceito:** Em caso de restart do gateway com job nao processado, o status nao e atualizado. Mitigacao: proximo webhook do mesmo provider aciona novo UPDATE. Aceitavel.

---

## Decisao 4: Invalidacao de cache via Redis pub/sub

**Decisao:** api/ publica no canal `cache:invalidate:instance:{instanceId}` ao rotacionar token de instancia. `GatewayCacheService` subscreve e limpa entradas correspondentes em L1 e L2.

**Alternativa descartada:** TTL puro sem invalidacao explicita. Com TTL de 120s, haveria janela de 2 minutos onde gateway usaria token antigo apos rotacao. Para tokens de webhook isso e inaceitavel — pode resultar em webhooks rejeitados.

---

## Decisao 5: session hijack — fix minimo sem refatorar JWT

**Decisao:** Corrigir `data?.sessionId ?? clientData.sessionId` para `clientData.sessionId` exclusivamente. Nao refatorar estrutura do JWT ou mecanismo de autenticacao WebSocket.

**Razao:** O fix e cirurgico — 2 linhas em `webchat.gateway.ts`. Refatorar JWT exigiria coordenacao com o frontend (app/) e escopo de varios dias adicionais.

---

## SLOs Definidos

| SLO | Metrica Prometheus | Target |
|---|---|---|
| SLO-1 Webhook Ingress (steady state) | `histogram_quantile(0.95, gateway_internal_api_duration_seconds{operation="instance_resolve"})` | <= 5ms |
| SLO-2 WebSocket Join (steady state) | `histogram_quantile(0.95, gateway_internal_api_duration_seconds{operation="ws_room_access"})` | <= 10ms |
| SLO-3 Cold Start instance_resolve | medicao manual pos-restart | <= 80ms |
| Cache hit rate | `gateway_cache_hits_total / (hits + misses)` para instance_resolve | >= 95% |

**Valores medidos:** pendente (TASK-5.1.1)

---

## Impacto em Arquitetura

- `dependencies.yaml:R01` — voltara a ser respeitado apos conclusao da Fase 4.
- `gateway/src/infrastructure/database/` — removido na Fase 4.
- `gateway/src/infrastructure/internal-api/` — novo modulo global.
- `gateway/src/infrastructure/cache/` — novo modulo global.
- api/ ganha 8 endpoints internos novos nos dominios Chat e Billing.
