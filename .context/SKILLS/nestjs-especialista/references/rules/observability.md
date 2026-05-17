# Observabilidade

## Logs

- Use logger estruturado quando disponível.
- Inclua `trace_id`, `correlation_id`, `tenant_id`, provider, domínio, ação e duração quando aplicável.
- Nunca registre segredo, token, API key, senha, assinatura ou payload sensível completo.
- Logs de erro devem preservar contexto operacional sem vazar dados.

## Métricas

- Use métricas para latência HTTP, status codes, filas, DLQ, circuit breaker, Redis, WebSocket e providers.
- Métricas não devem ter labels de alta cardinalidade como mensagem, telefone bruto ou token.
- Para webhooks, meça ACK time, duplicados, falhas de assinatura e falhas de processamento.

## Trace

- Preserve `TraceIdInterceptor`.
- Propague `X-Trace-ID` e `correlation_id` entre API, Gateway, Redis Streams, filas e logs.
- Em eventos realtime, mantenha rastreabilidade sem expor dados sensíveis ao cliente.
