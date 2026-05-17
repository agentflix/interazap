# Testes E Validação

## Unit Tests

- Todo comportamento novo ou alterado em `gateway/` deve ter `*.spec.ts`.
- Use Jest e `@nestjs/testing` conforme o padrão local.
- Mocke Redis, database, HTTP clients, queues, providers externos, Socket.IO e timers.
- Teste sucesso, erro, timeout, retry, duplicata, payload inválido e autorização negada quando aplicável.
- Não faça chamadas reais para WhatsApp, Telegram, OpenAI, Asaas, Redis externo ou banco real em unit test.

## E2E

- Use `gateway/test/*.e2e-spec.ts` para contratos HTTP, CORS, headers, webhooks e fluxos críticos.
- Real e2e com providers externos deve ficar isolado por sufixo/padrão já ignorado quando depender de credenciais reais.
- Fixtures devem ser anonimizadas e sem segredos.

## Cobertura E Qualidade

- Não deixe testes pulados.
- Preserve cobertura mínima esperada no `gateway/AGENTS.md`: 80% quando a task exigir relatório.
- Rode `pnpm --filter gateway typecheck:specs` quando alterar tipos consumidos por testes.

## Gates

- Gate padrão para Gateway: `pnpm --filter gateway test && pnpm --filter gateway build`.
- Para mudanças HTTP/WebSocket relevantes, rode também `pnpm --filter gateway test:e2e`.
- Para qualidade completa, rode lint, typecheck de specs e testes de cobertura quando o risco justificar.
- Se algum gate não puder rodar, informe comando, motivo e risco residual.
