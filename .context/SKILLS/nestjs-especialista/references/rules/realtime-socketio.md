# Realtime e Socket.IO

## Gateway WebSocket

- Use `@WebSocketGateway` e services/stores existentes para eventos em tempo real.
- Autentique sockets com JWT via `WsAuthGuard` ou guard equivalente.
- Inclua `tenant_id` no contexto do socket e valide pertencimento antes de permitir rooms.
- Use rooms por tenant, ticket ou domínio seguindo o contrato existente.

## Segurança

- A origem do WebSocket deve respeitar allowlist de CORS.
- Não aceite room arbitrária do cliente sem validar tenant e permissão.
- Use `WsThrottlerGuard` para limitar abuso por usuário/tenant/socket.
- Não envie payload sensível para room ampla.

## Eventos

- Eventos devem ser tipados, versionáveis e compatíveis com o App.
- Inclua `correlation_id` quando o evento vier de fluxo rastreável.
- Faça deduplicação quando provider ou stream puder repetir evento.
- Desconexão/reconexão deve ser tolerada sem perda catastrófica de estado.
