# Arquitetura NestJS/Gateway

## Fontes locais

- Use `gateway/package.json` para versões, scripts e dependências reais.
- Use `gateway/src/main.ts` para bootstrap, `ValidationPipe`, Helmet, CORS, body limit e logger.
- Use `gateway/src/app.module.ts` para módulos globais, guards, interceptors e wiring de infraestrutura.
- Use `gateway/src/core/config/configuration.ts` e `models/configuration.model.ts` para configuração tipada.
- Use `gateway/AGENTS.md` como regra operacional específica do Gateway.

## Organização

- `src/domains/{domain}/`: domínios de negócio como `chat`, `billing`, `ai`, `realtime`, `internal` e `webhooks`.
- `src/infrastructure/`: conexões e abstrações de Redis e banco.
- `src/shared/`: circuit breaker, idempotência, filas, utilitários, modelos e interceptors reutilizáveis.
- `src/common/`: logger estruturado, config comum, secrets, interceptors globais e modelos transversais.
- `src/health` e `src/metrics`: saúde, métricas e operação.

## Camadas

- Controllers recebem HTTP/WebSocket/webhook, validam contrato e delegam.
- Services concentram regra de orquestração e integração.
- Providers/clients encapsulam APIs externas.
- DTOs definem entrada/saída e validação.
- Redis/BullMQ/Streams ficam atrás de services próprios, não espalhados por controllers.

## Princípios

- Mantenha módulos coesos e imports explícitos.
- Evite lógica de negócio em controllers.
- Não crie abstração global antes de haver repetição real.
- Preserve compatibilidade com a API Laravel e com o App Angular.
- Toda mudança cross-workspace deve considerar contrato API ↔ Gateway ↔ App.
