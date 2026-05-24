# 0004-PRD-gateway-audit-remediation

**Versao:** 1.0
**Data:** 2026-05-24
**Autor:** Rafael Silva
**Status:** [ ] Rascunho | [ ] Em revisao | [x] Aprovado

---

## Visao Geral

Remediacao dos 5 achados da auditoria de seguranca e qualidade do gateway InteraZap (NestJS 11). O achado mais grave — gateway acessando PostgreSQL diretamente em 7 call-sites — viola a regra arquitetural central do projeto (AGENTS.md:18, dependencies.yaml:R01). Os demais achados incluem 29 CVEs de dependencias, vulnerabilidade de session hijack em WebSocket, endpoint de metricas sem autenticacao e falhas de lint.

A solucao para o achado Alto-1 exige estrategia de cache em camadas (L1 LRU em memoria + L2 Redis) para preservar ou melhorar a latencia atual nas hot paths de webhook ingress e WebSocket join — ambas as paths onde uma regressao de performance seria inaceitavel em producao.

## Problema

### Alto-1: Violacao arquitetural — gateway acessa PostgreSQL diretamente

O gateway contem `DatabaseService` (pg.Pool) registrado como modulo global e usado em 7 arquivos de 3 dominios. Isso viola:
- `AGENTS.md:18` — "Gateway nunca acessa PostgreSQL diretamente — sempre via api REST"
- `dependencies.yaml:R01` — `from: gateway, to: postgresql, allowed: false`

**Hot paths afetados com latencia critica:**
- `instance-resolver.service.ts` — hot path de webhook ingress (Z-API/Meta/Uazapi). Cada mensagem entrante resolve webhook_token. Frequencia: proporcional ao volume de mensagens recebidas.
- `ws-room-access.service.ts` — hot path de WebSocket join (ticket/run). Frequencia: proporcional a conexoes de agentes e usuarios simultaneos.

**Call-sites completos:**

| Arquivo | Tabela | Operacao |
|---|---|---|
| `channels.controller.ts:223` | chat_instances | SELECT por id |
| `instance-resolver.service.ts:297-320` | chat_instances | SELECT por webhook_token + settings_json token |
| `connection-status.service.ts:144` | chat_instances | UPDATE status/last_connection |
| `billing-tenant-resolver.service.ts:41` | platform_tenants | SELECT por billing_webhook_token |
| `billing-webhook.service.ts:343` | billing_webhook_events | INSERT + UPDATE stream_id |
| `billing-collection.service.ts:88` | chat_instances | SELECT WhatsApp conectado por tenant |
| `ws-room-access.service.ts:70,97` | chat_tickets, ai_autopilot_runs | SELECT ownership |

### Alto-2: 29 CVEs de dependencias (8 HIGH)

`pnpm audit --prod` reporta:
- axios 1.13.2 — CVE-2026-40175 (header injection) → ^1.15.2
- @nestjs/core + @nestjs/common — CVE-2026-4926 (path-to-regexp ReDoS HIGH) → ^11.1.18
- express — CVE-2026-8723 (qs DoS) → ^5.2.1
- ws, uuid, fast-xml-builder — transitivos via openai/bullmq/@aws-sdk

### Medio-3: Session hijack no WebChatGateway

`webchat.gateway.ts:132,194` — `handleWebChatJoin` e `handleWebChatLeave` aceitam `data?.sessionId ?? clientData.sessionId`. Um cliente com token JWT valido pode fornecer o sessionId de outra sessao e entrar na room correspondente. Ausencia de specs cobrindo este gateway.

### Medio-4: /v1/metrics sem autenticacao

`metrics.controller.ts:16-31` expoe labels Prometheus com `tenant`, `agent_id`, `source_agent_id`, `target_agent_id`, `provider` sem `InternalApiKeyGuard`. Outros controllers internos ja usam o guard (`channels.controller.ts:29`).

### Baixo-5: Lint/Prettier falha

3 arquivos com erros de formatacao: `billing-usage-client.service.ts:89`, `billing-usage.metrics.ts:50`, `jest.setup.ts:2`.

## Objetivos

1. Eliminar todos os acessos diretos ao PostgreSQL no gateway, substituindo por chamadas HTTP ao api com cache em camadas.
2. Preservar ou melhorar a latencia das hot paths: p95 do webhook ingress nao deve aumentar mais de 5ms vs baseline (estado estacionario com cache quente).
3. Remediar os 8 CVEs HIGH e demais CVEs das dependencias.
4. Corrigir a vulnerabilidade de session hijack no WebSocket.
5. Proteger o endpoint /v1/metrics com autenticacao.
6. Zeraar falhas de lint/Prettier no gateway.

## Metricas de Sucesso

| Metrica | Baseline | Target |
|---|---|---|
| `pnpm --filter gateway build` passes | sim | sim |
| `pnpm --filter gateway test` passes | sim | sim |
| `pnpm audit --prod` HIGH CVEs | 8 | 0 |
| Arquivos com acesso direto ao DB no gateway | 7 | 0 |
| `DatabaseModule` registrado em `app.module.ts` | sim | nao |
| p95 latencia adicional webhook ingress (cache quente) | 0ms (DB local) | <= 5ms |
| p95 latencia webhook ingress (cache frio, primeiro request) | ~2-5ms (DB) | <= 80ms |
| Cache hit rate instance-resolver (producao estacionaria) | N/A | >= 95% |
| `/v1/metrics` retorna 401 sem x-api-key | nao | sim |
| Session hijack via sessionId externo bloqueado | nao | sim |
| Lint/Prettier erros | 3 | 0 |

## SLO de Performance (Definicao Formal)

**SLO-1 (Webhook Ingress):** p95 de latencia adicional introduzida pela migracao DB->HTTP nao deve exceder 5ms medido em estado estacionario (cache L1 ou L2 quente, >= 95% hit rate). Medido via histograma Prometheus `gateway_internal_api_duration_seconds` com label `operation=instance_resolve`.

**SLO-2 (WebSocket Join):** p95 de latencia de `ws_room_access_duration_ms` nao deve exceder 10ms adicional em estado estacionario.

**SLO-3 (Cache Cold Start):** primeiro request apos restart do gateway (cache L1 e L2 frios) nao deve exceder 80ms total para instance-resolver e 100ms para ws-room-access.

## Escopo

### Incluido

- [x] 7 endpoints internos novos na api/ para substituir os 7 call-sites de DB direto no gateway
- [x] `InternalApiClient` — cliente HTTP compartilhado com keep-alive, connection pooling e retries
- [x] Cache L1 LRU em memoria (lru-cache) + L2 Redis — strategy por hot path
- [x] Invalidacao de cache via Redis pub/sub (channel `cache:invalidate:instance`) ao rotacionar tokens
- [x] BullMQ job `UpdateConnectionStatusJob` para connection-status UPDATE (fire-and-forget)
- [x] Correcao de session hijack no WebChatGateway
- [x] InternalApiKeyGuard em MetricsController
- [x] pnpm update para CVEs HIGH
- [x] Fix lint/Prettier nos 3 arquivos
- [x] Instrumentacao Prometheus: `gateway_internal_api_duration_seconds`, `gateway_cache_hits_total`, `gateway_cache_misses_total`
- [x] Remocao de `DatabaseModule` e `DatabaseService` apos migracao completa
- [x] Specs para WebchatGateway cobrindo session hijack

### Fora de Escopo

- Refactor do sistema de autenticacao JWT (claims de ticket/run no token) — decisao adiada
- Endpoints batch para validacao de N tickets simultaneos — pode ser adicionado em iteracao futura
- Migracao das queries SQL para ORM Eloquent na api/ — ja estao em Actions/Controllers corretos
- Mudancas no schema do PostgreSQL
- Alteracoes no frontend (app/)

## Arquitetura de Cache por Hot Path

### Hot Path 1: Webhook Ingress (instance-resolver)

- L1: LRU em memoria, max 500 entries, TTL 30s — absorve rajadas de webhooks do mesmo sender
- L2: Redis, TTL 120s, chave `chat:instance_by_webhook_token:{token}`
- Invalidacao: Redis pub/sub `cache:invalidate:instance:{instanceId}` publicado pela api/ ao rotacionar token
- Fallback: HTTP GET `/api/internal/chat/instances/by-webhook-token/{token}`

### Hot Path 2: WebSocket Room Access (ws-room-access)

- L1: LRU em memoria, max 1000 entries, TTL 10s — curto por ser ownership check de seguranca
- L2: Redis, TTL 60s, chave `ws:room_access:{room}:{tenantId}`
- Sem invalidacao explicita (TTL curto e natural em tickets/runs fechados)
- Fallback: HTTP GET `/api/internal/realtime/room-access?room={room}&tenantId={tenantId}`

### Hot Path 3: Billing Tenant Resolver

- L2: Redis existente, TTL 120s — manter comportamento atual, trocar DB por HTTP
- Fallback: HTTP GET `/api/internal/billing/tenants/by-webhook-token/{token}`

### Nao-hot paths (sem cache adicional)

- `channels.controller.ts` — raramente chamado, HTTP direto suficiente
- `connection-status.service.ts` — UPDATE fire-and-forget via BullMQ, sem round-trip no request principal
- `billing-webhook.service.ts` — INSERT/UPDATE via HTTP POST, sem cache
- `billing-collection.service.ts` — HTTP GET com cache Redis TTL 60s

## Riscos

| Risco | Probabilidade | Impacto | Mitigacao |
|---|---|---|---|
| Cache stale em producao apos rotacao de token | Media | Alto | Invalidacao Redis pub/sub; TTL maximo 120s garante convergencia em 2min |
| api/ indisponivel durante webhook ingress | Baixa | Alto | Circuit breaker com fallback para stale Redis (stale-while-revalidate no L2) |
| pnpm update quebra tipos do @nestjs/* | Media | Medio | Rodar `pnpm --filter gateway build` imediatamente apos update; corrigir antes de avancar |
| BullMQ job `UpdateConnectionStatusJob` perdido em restart | Baixa | Baixo | Job e fire-and-forget; status sera atualizado no proximo webhook do mesmo provider |
| Latencia p95 acima de 5ms em cache miss no L1 (L2 hit) | Baixa | Medio | Redis colocado em localhost/mesma VPC; latencia esperada L2 < 2ms |

## Cronograma Estimado

- Fase 1 (Quick wins): 0.5 dia
- Fase 2 (InternalApiClient + cache layer): 0.5 dia
- Fase 3 (Migracao por dominio): 1 dia
- Fase 4 (Remocao DatabaseModule): 0.25 dia
- Fase 5 (Validacao SLO): 0.25 dia
- **Total:** ~2.5 dias
