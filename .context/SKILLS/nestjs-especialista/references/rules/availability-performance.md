# Alta Disponibilidade e Performance

## Disponibilidade

- O Gateway deve degradar funcionalidade quando provider externo falhar, não derrubar o processo inteiro.
- Use timeout explícito em HTTP externo, Redis blocking reads e chamadas à API Laravel.
- Circuit breaker deve proteger providers instáveis.
- Health checks devem indicar estado de Redis, filas, providers críticos e banco quando aplicável.
- Encerramento deve fechar Redis, filas e conexões de forma graciosa.

## Performance

- Webhooks devem responder rápido e delegar trabalho pesado.
- Não bloqueie o event loop com CPU pesada, parse massivo ou arquivo grande.
- Use streams, filas e processamento incremental para cargas grandes.
- Evite logs excessivos em caminho quente.
- Limite cardinalidade de métricas para não sobrecarregar Prometheus.

## Multi-instância

- Coordenação entre instâncias deve usar Redis, BullMQ, locks ou consumer groups.
- Não dependa de estado em memória para idempotência ou autorização.
- Consumer names devem ser únicos por instância quando usar Redis Streams.
