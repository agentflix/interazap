# /validate-phase — Validation de Fase (PREVC: Validation)

Uso: `/validate-phase [N]`

## Quem executa
- @QA

## Passos

1. Identificar workspace(s) da Fase N
2. Rodar gates completos do(s) workspace(s):

| Fase | Gates |
|------|-------|
| 3 (Backend) | `cd api && composer gate:all` |
| 4 (Gateway) | `pnpm --filter gateway lint && test && test:e2e && build` |
| 5 (Frontend) | `pnpm --filter app lint && test && build` + `pnpm --filter electron build` |
| 6 (Integration) | E2E (Playwright se disponível) + smoke tests |

3. Verificar coverage da fase
4. Marcar gate de fase como aprovado/reprovado

## Output
Gate de Fase N: ✅ aprovado / ❌ reprovado.
