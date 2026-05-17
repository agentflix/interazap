# Regras Laravel: Observabilidade

Use estas regras ao adicionar logs, métricas, health checks, jobs e integrações.

## Logs

- Logs estruturados devem incluir quando disponível: `trace_id`, `correlation_id`, `tenant_id`, `user_id`, entidade, provider e evento.
- Nunca logar token, senha, assinatura, chave API, bearer token, payload sensível completo ou dados de cartão.
- Logar início/fim apenas em fluxos críticos; evitar ruído em caminho quente.
- Erros recuperáveis devem ter nível coerente (`warning`); falhas inesperadas, `error`.

## Metricas

- Medir latência HTTP, taxa de erro, tempo de jobs, tamanho/espera de filas, falhas por provider e uso de IA.
- Para filas, acompanhar `wait`, throughput, falhas, retries e jobs presos.
- Para banco, acompanhar queries lentas e conexões.
- Para cache/Redis, acompanhar disponibilidade e latência.

## Tracing

- Propagar `correlation_id` entre API, Gateway, Redis Streams e jobs.
- Incluir IDs de stream/mensagem em logs de publish/consume.
- Em webhook, correlacionar provider event id com evento interno.

## Health Checks

- Health básico deve funcionar sem Redis.
- Deep health pode validar DB, Redis e filas, mas não deve ficar exposto sem controle.
- Health não deve testar providers externos de forma que gere custo ou rate limit.

## Alertas

- Alertar por fila crescendo, falhas repetidas, circuit breaker aberto, latência externa alta, banco indisponível e erro 5xx elevado.
- Alertas devem apontar para ação operacional clara.
