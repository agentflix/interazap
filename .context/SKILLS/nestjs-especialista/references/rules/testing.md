# Testes Jest

## Padrão

- Todo comportamento novo em `gateway/` deve ter teste.
- Use `*.spec.ts` para unit/integration leve e `*.e2e-spec.ts` para superfícies HTTP/WebSocket completas.
- Não deixe `it.skip`, `describe.skip` ou testes frágeis sem justificativa.
- Mocks devem substituir Redis, HTTP externo, providers e banco quando a intenção não for teste real.

## Services

- Teste sucesso, erro recuperável, erro fatal, timeout e idempotência.
- Para providers externos, teste normalização de resposta e mascaramento de segredo.
- Para circuit breaker, teste estado aberto/fechado/meio-aberto quando aplicável.

## Controllers e webhooks

- Teste DTO/validação, assinatura/HMAC, API key interna, status HTTP e ACK rápido.
- Use fixtures para payloads de provider.
- Teste duplicidade de webhook.

## Redis, filas e realtime

- Redis Streams devem testar publish, parse, ACK e falha de parse.
- Filas devem testar payload, retry/DLQ e reprocessamento.
- WebSocket deve testar autenticação, throttling, rooms e autorização por tenant.

## Comandos

- Gates mínimos: `pnpm --filter gateway test` e `pnpm --filter gateway build`.
- Quando tocar contrato e tipos de teste, rode `pnpm --filter gateway typecheck:specs`.
- Quando tocar HTTP/WebSocket end-to-end, rode `pnpm --filter gateway test:e2e`.
