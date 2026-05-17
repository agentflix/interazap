# Performance E Alta Disponibilidade

## Latência

- Mantenha ACK de webhook abaixo de 150 ms; mova trabalho pesado para filas, streams ou fire-and-forget monitorado.
- Configure body limit conscientemente; payloads grandes exigem stream, armazenamento externo ou processamento assíncrono.
- Evite serialização/deserialização repetida de payloads grandes.

## Backpressure

- Use `COUNT`, `BLOCK`, concorrência de workers, rate limit e DLQ para controlar pressão em Redis Streams e BullMQ.
- Evite loops sem pausa em polling, consumers e workers.
- Para Socket.IO, reduza fanout desnecessário e payloads de alta frequência.

## Resiliência

- Toda chamada externa precisa de timeout; chamadas críticas precisam de circuit breaker.
- Use retry apenas para falhas transitórias e com backoff.
- Forneça fallback ou degradação graciosa quando API externa estiver indisponível.
- Consumers devem tolerar eventos duplicados, fora de ordem e atrasados.

## Alta Disponibilidade

- Código deve ser seguro para múltiplas instâncias do Gateway.
- Idempotência, locks e consumer groups devem considerar concorrência entre pods/processos.
- Não dependa de memória local para estado que precise sobreviver a restart ou balanceamento.
- Encerramento deve fechar conexões Redis, filas, workers e recursos WebSocket sem perda desnecessária.
