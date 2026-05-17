# Filas e BullMQ

## Filas

- Use BullMQ para trabalho assíncrono, retries, DLQ e desacoplamento de webhooks.
- Separe filas por perfil de carga: webhooks, outbound messaging, IA, reprocessamento e tarefas administrativas.
- Não misture jobs lentos com eventos que exigem baixa latência.

## Jobs e workers

- Jobs devem ter payload pequeno, tipado e idempotente.
- Defina `attempts`, `backoff`, timeout, TTL e remoção de jobs concluídos/falhos conforme criticidade.
- Use rate limit por provider/tenant quando a API externa tiver limite.
- Workers devem registrar falha com contexto suficiente para reprocessamento.

## DLQ

- Falhas irreversíveis devem ir para DLQ com motivo, payload mínimo e metadados.
- Reprocessamento deve respeitar idempotência.
- Não descarte falhas sem métrica, log ou trilha operacional.

## Performance

- Evite serializar payload grande em job; use IDs, URLs temporárias ou storage quando possível.
- Para arquivos e mídia, evite bloquear event loop com processamento síncrono pesado.
