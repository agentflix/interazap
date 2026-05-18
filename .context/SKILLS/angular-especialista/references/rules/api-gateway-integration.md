# Integração API e Gateway

## API Laravel

- Use `environment.apiUrl` como base. No desenvolvimento, `/api` passa pelo proxy para `localhost:8000`.
- Respeite envelopes existentes, geralmente `{ data: ... }`, e modelos de paginação compartilhados.
- Modele erros conforme respostas reais da API; trate `401`, `402` e `403` pelos interceptors centrais quando transversal.
- Use `HttpParams` para filtros, paginação e datas; evite concatenação manual de query string.
- Não contorne contratos Laravel nem acesse banco direto.

## Gateway

- Gateway em tempo real usa `environment.gateway.url` e `environment.gateway.path`; no desenvolvimento, `/ws` passa pelo proxy para `localhost:3000`.
- Use `RealtimeService`/stores existentes para conexão Socket.IO, rooms, reconexão e streams.
- A autenticação do socket deve usar token/tenant do estado autenticado e o servidor valida pertencimento a rooms.
- Não replique no frontend a idempotência de Redis Streams entre API e Gateway; o app consome eventos e envia comandos HTTP/socket conforme contrato.

## Contratos

- Antes de alterar payload de app, verifique contrato em API/Gateway e documentação funcional correspondente, quando existir.
- Mudanças cross-workspace devem ser compatíveis ou coordenadas no mesmo task plan.
- Para endpoints novos, inclua testes frontend com mocks e, quando possível, alinhe nomes com bounded context do backend.
