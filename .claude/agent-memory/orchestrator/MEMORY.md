# MEMORY.md

## FEAT-041 — Telegram Bot API Integration (2026-04-14)

### Status: Fases 1-4 IMPLEMENTADAS

- 21 tasks completadas (T1-T14, T18, T20-T24)
- 87+ testes unitários + 17 testes Feature Laravel passando
- Pendente: TASK-T25 (E2E), T26 (AI bot validation), T27 (security review), T28 (docs)

### Desvios do plano original

- `gateway/src/domains/chat/providers/telegram/` nunca existiu → TASK-T29 (delete) é N/A
- Angular channel-form já era standalone → TASK-T15/T16 foram N/A
- `OutboundMessage.provider` estava incompleto (faltava 'meta') — corrigido junto com 'telegram'

### Decisões arquiteturais

- Telegram isolado em `gateway/src/bot/` (não em `domains/chat/providers/`)
- Comunicação bot→chat via Redis streams (sem import direto entre módulos)
- SecretsService: AWS SM → Vault → ENV cascade com cache 5min
- CircuitBreaker: 5 falhas/10s → OPEN 30s → HALF_OPEN 3 requests → CLOSED/OPEN
- WebSocket namespace `/telegram` com JWT + tenant isolation + rate limit 100/min

## GATEWAY Agent Hired (INTA-9)

**Data:** 2026-04-29
**Agent ID:** `05511851-f1fc-4cb5-9692-f5d08a47545a`
**Nome:** GATEWAY | Role: engineer | Ícone: cpu
**reportsTo:** CEO (476bd6d0-f362-4451-bb49-9cd96c77ca4f)
**Capabilities:** NestJS 11, BullMQ, Redis Streams, WebSocket, Jest
**Status:** idle, wakeOnDemand

**Pendência:** instructionsFilePath precisa ser atualizado para `.../gateway/AGENTS.md` — ação requer board.

## Lições recorrentes (qualquer FEAT)

### Sempre fazer grep de rota antes de criar endpoint novo
- 2026-04-26: TASK-047.3 quase duplicou `POST /api/auth/login` que já existia em `Auth/Routes/auth.php` e já emitia Bearer Sanctum.
- Custo: 5min de grep evita refactor de 6 arquivos + divergência de policies (2FA/throttle/inactive-check).
- Detalhe: `.context/DOCS/MEMORY/2026-04-26-bearer-auth-already-existed.md`

### Tenant isolation: SEMPRE via middleware, NUNCA em abilities de token
- Decisão estrutural FEAT-047: Sanctum PAT com `createToken('name')` sem abilities customizadas.
- Tenant resolvido por `TenantContextMiddleware` lendo `user()->tenant_id`.
- Adicionar `tenant:N` em abilities cria 2º caminho de verificação que pode divergir → bypass.
- Detalhe: `.context/DOCS/MEMORY/2026-04-26-feat-047-mobile-architecture-decisions.md`
