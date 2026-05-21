# Autopilot Stability — Decisões Técnicas

**Tipo:** Decisão
**Data:** 2026-05-21
**Autor:** PREVEC autopilot-stability
**Tags:** autopilot, redis, guardrails, approvals, correlation_id, permissions

## Situação

Análise estática do módulo Autopilot identificou 15 gaps antes de ir para produção em escala.

## Decisões

### Lock Redis TTL = 300s (igual job timeout)
Elimina janela de race condition em duplicate webhooks.
**Trade-off:** retry legítimo da mesma msg em 5min bloqueado (idempotência > retry).

### Approval expirado → status `expired` (não `rejected`)
Distingue causa: timeout vs. rejeição humana explícita. Facilita observabilidade e suporte.

### Guardrails DB-driven com Cache TTL 300s + Observer
Cache invalidado em saved/deleted via AiAutopilotGuardrailObserver.
TTL 300s como backup caso observer falhe.

### Correlation ID nasce no evento AutopilotTriggerFired
UUID gerado no evento, não no job. Propaga via payload Redis/BullMQ (gateway nunca acessa PostgreSQL).

### Permission split zero-downtime
`ai.autopilots.view` + `.run` adicionadas com `manage` permanecendo como superset.
Deploy sem break em produção.

### AiCancellationRegistry via Redis (não Set in-memory)
Multi-pod safe. TTL 300s = job timeout. Set in-memory seria silenciosamente ignorado em pods diferentes.

### MetricsService expandido (não substituído)
Já existia com 2 métodos autopilot. BUILDER expandiu com 4 novos. Reusar evita divergência de implementação.

## Consequências

- **Positivas:** Módulo preparado para produção em escala, observabilidade completa, sem runs zombie
- **Negativas / Trade-offs:** Lock 300s bloqueia retry legítimo da mesma msg no mesmo período
- **Ação necessária:** CI deve configurar Redis gateway para E2E tests (ConcurrentWebhooks + CorrelationId)
