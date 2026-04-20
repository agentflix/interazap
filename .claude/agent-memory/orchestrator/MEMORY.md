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
