# Redis, Streams e Idempotência

## Redis

- Use `RedisService` para conexões Redis; não crie clientes soltos sem necessidade.
- Preserve conexões separadas para comandos, Pub/Sub e operações bloqueantes.
- Defina TTL para cache, idempotência e locks.
- Nunca deixe streams, chaves temporárias ou filas crescerem sem limite.

## Redis Streams

- Use `RedisStreamsService` para publicar, ler, reconhecer e responder mensagens.
- Mensagens devem carregar `correlation_id`, `timestamp`, `domain`, `action`, `provider`, `payload` e `metadata` quando aplicável.
- Use consumer groups para processamento concorrente e ACK somente após sucesso real.
- Para respostas, publique em stream de resposta compatível com o `correlation_id`.
- Defina comportamento para timeout, falha de parse e mensagem inválida.

## Idempotência

- Webhooks, comandos vindos da API e eventos de provider devem ter chave idempotente estável.
- Use Redis `SET NX EX` ou `IdempotencyService` quando aplicável.
- Inclua tenant/provider/event id na chave para evitar colisão.
- Evento duplicado deve retornar sucesso quando o efeito já foi aplicado.

## API ↔ Gateway

- Comunicação API ↔ Gateway deve ser idempotente e rastreável.
- Mantenha contrato de streams compatível com `api/src/Domain/Gateway`.
- Não use Redis Streams como substituto para regra de negócio que pertence à API.
