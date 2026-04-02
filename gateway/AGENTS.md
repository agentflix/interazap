# AGENTS.md — InteraZap Gateway (NestJS 11)

> 📌 This extends the root `../AGENTS.md` — read it first for full project context.

## Stack

- **NestJS 11** with TypeScript 5.7
- **BullMQ** for job queues
- **Redis 7** for Streams, PubSub, cache, idempotency
- **WebSocket** for real-time communication
- **Jest** for testing

## Mandatory Rules

1. `ValidationPipe` with `whitelist: true` on all controllers
2. Dedicated `Logger` instance per controller/service
3. Idempotency on all webhook handlers (Redis SETNX with TTL)
4. Circuit breaker on all external HTTP calls
5. Webhook ACK response < 150ms
6. **NEVER** log tokens, passwords, API keys

## Architecture

```
Controller → Service → External APIs / Redis / Laravel API
```

Files in `src/domains/{domain}/`:

- `controllers/{entity}.controller.ts`
- `services/{entity}.service.ts`
- `dto/{entity}.dto.ts`
- `{domain}.module.ts`

## Redis Patterns

- **BullMQ**: Job queues for webhook processing, AI tasks
- **Streams**: Real-time event propagation
- **PubSub**: WebSocket broadcast coordination
- Always set job TTL and retry policies

## Testing (Jest)

- Unit tests for services, e2e for controllers
- Mock external dependencies (Redis, HTTP clients)
- 0 skipped tests, ≥80% coverage
- Run: `pnpm test`

## Gates

```bash
pnpm lint && pnpm test
```

**All gates must pass before commit.**
