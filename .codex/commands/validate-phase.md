# Validar Fase (PREVC: Validation de Fase)

Uso: `/validate-phase [fase]`

Exemplo: `/validate-phase 3` (valida Fase 3 — Backend)

## Processo (@QA)

1. Executar gates do workspace da fase:

| Fase | Workspace | Gate |
|------|-----------|------|
| 3 | `api/` | `composer gate:all` |
| 4 | `gateway/` | `pnpm --filter gateway lint test build` |
| 5 | `app/` | `pnpm --filter app lint test build` |
| 6 | Todos | Gates de todos os workspaces afetados |

2. Verificar critérios E de todas as tasks TASK-[fase].*.*
3. Verificar critérios especiais por tipo de mudança (multi-tenancy, webhook, migration, WebSocket)
4. Marcar gate de fase no arquivo de tasks

## Saída Esperada

```
Fase [N] — Validation:
  Gates: [✅ / ❌ — qual falhou]
  Coverage: [backend X% / frontend Y%]
  Critérios E: [X/N tasks ok]
  Gate de Fase: [✅ PASSOU / ❌ REPROVADO — motivo]
Próximo: /review-phase [fase] (se aprovado) ou corrigir e re-validar
```
