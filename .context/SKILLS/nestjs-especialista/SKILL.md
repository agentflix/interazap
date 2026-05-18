---
name: nestjs-especialista
description: Especialista NestJS/Gateway do InteraZap. Use quando Codex precisar implementar, revisar ou planejar código em gateway/ com NestJS 11, TypeScript, BullMQ, Redis Streams, Socket.IO, webhooks, integrações externas, guards, controllers, services, DTOs, idempotência, circuit breaker, observabilidade, segurança, performance, testes Jest e gates específicos deste repositório.
---

# NestJS Especialista

## Metadados

- Autor: Rafael Silva
- Versão: 1.0

## Fluxo

Use esta skill para qualquer trabalho em `gateway/`. Antes de implementar, leia a task ou documentação funcional correspondente quando existir, MEMORY relevante em `.context/DOCS/MEMORY/`, o workflow em `.context/SKILLS/workflow-prevc/references/prevc.md`, os gates em `.context/SKILLS/workflow-prevc/references/validation-flow.md` e as regras de `gateway/AGENTS.md`.

Quando houver divergência entre documentação geral e configuração local, trate os arquivos do workspace como fonte operacional: `gateway/package.json`, `gateway/src/main.ts`, `gateway/src/app.module.ts`, `gateway/src/core/config/configuration.ts`, `gateway/test/jest-e2e.json` e `.env.example`.

## Regras

- Responda em português brasileiro formal e preserve nomes técnicos como `Gateway`, `Socket.IO`, `BullMQ`, `Redis Streams`, `provider`, `guard`, `interceptor`, `controller`, `service` e `DTO`.
- Siga a arquitetura existente: `Controller -> Service -> External APIs / Redis / Laravel API`.
- Use `src/domains/{domain}/` para domínios de negócio, `src/shared/` para capacidades reutilizáveis, `src/infrastructure/` para Redis/DB e `src/common/` para logger, interceptors, config e modelos transversais.
- Todo controller deve depender do `ValidationPipe` global com `whitelist`, DTOs tipados e guards/interceptors apropriados.
- Webhooks devem responder rápido, ser idempotentes, validar assinatura quando aplicável e empurrar trabalho pesado para fila/stream.
- Chamadas externas devem ter timeout, retry/backoff quando seguro, circuit breaker e logs sem segredos.
- Nunca registre tokens, senhas, API keys, payloads sensíveis completos ou assinaturas.
- Todo comportamento novo deve ter teste Jest (`*.spec.ts`) ou e2e quando a superfície for HTTP/WebSocket.
- Rode `pnpm --filter gateway test && pnpm --filter gateway build` quando a task tocar `gateway/`; se aplicável, rode `pnpm --filter gateway test:e2e` e `pnpm --filter gateway typecheck:specs`.

## Regras por Tema

Carregue apenas os arquivos necessários conforme a demanda em `references/rules/`:

- `references/rules/architecture.md`: módulos, domínios, camadas e organização do Gateway.
- `references/rules/controllers-dtos.md`: controllers, DTOs, pipes, validação e contratos HTTP.
- `references/rules/services-providers.md`: services, providers externos, clients HTTP e dependências.
- `references/rules/redis-streams.md`: Redis, Streams, Pub/Sub, idempotência e comunicação API ↔ Gateway.
- `references/rules/queues-bullmq.md`: BullMQ, filas, DLQ, retries, rate limit e workers.
- `references/rules/realtime-socketio.md`: Socket.IO, guards WS, rooms, throttling e eventos.
- `references/rules/webhooks.md`: webhooks, HMAC, ACK rápido, normalização e deduplicação.
- `references/rules/security.md`: autenticação interna, JWT, CORS, Helmet, secrets e hardening.
- `references/rules/availability-performance.md`: alta disponibilidade, timeouts, circuit breaker, performance e degradação.
- `references/rules/observability.md`: logs estruturados, métricas, trace ID, health checks e alertas.
- `references/rules/testing.md`: Jest, e2e, mocks, Redis, HTTP, WebSocket e cobertura.
- `references/rules/configuration.md`: configuração tipada, `.env`, validação operacional e deploy.
- `references/rules/validation.md`: gates obrigatórios e evidências de validação.

## Regras Complementares

Use estes arquivos quando precisar de uma visão mais detalhada ou transversal:

- `references/rules/controllers-services-providers.md`: controllers, services, providers, adapters e consumers em uma visão integrada.
- `references/rules/dto-validation.md`: DTOs, pipes, validação contextual e contratos internos.
- `references/rules/integrations.md`: WhatsApp, Meta, Telegram, IA, Asaas e HTTP externo.
- `references/rules/security-idempotency-rate-limit.md`: segredos, guards, webhooks, idempotência, rate limit e erros.
- `references/rules/performance-availability.md`: latência, backpressure, resiliência e múltiplas instâncias.
- `references/rules/configuration-env.md`: `ConfigModule`, variáveis de ambiente, CORS, segurança HTTP e testes de configuração.
- `references/rules/testing-validation.md`: testes unitários, e2e, cobertura, typecheck e gates combinados.
