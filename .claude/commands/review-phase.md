# /review-phase — Revisão de Fase (PREVC: Review)

Uso: `/review-phase [N]` (N = 1..6)

## Quem executa
- @REVIEWER

## Passos

1. Localizar checkpoint de revisão da Fase N em `.context/DOCS/TASKS/[feature]-tasks.md`
2. Verificar checklist da fase:
   - Fase 3: DDD respeitado, BelongsToTenant aplicado, phpDoc, final class
   - Fase 4: Circuit breaker, idempotência, HMAC
   - Fase 5: Standalone, signals, sem acesso direto a DB
3. Rodar checks específicos:

```bash
# Fase 3 (api/)
cd api && composer analyse && composer format

# Fase 4 (gateway/)
pnpm --filter gateway lint

# Fase 5 (app/, electron/)
pnpm --filter app lint
pnpm --filter electron build
```

4. Decidir:
   - ✅ Aprovado → fase pode seguir para CONFIRM
   - 🔄 Ajustes → tasks afetadas voltam para EXECUTION

## Output
Checkpoint marcado como aprovado ou ajustes solicitados.
