# /validate — Validar Task (PREVC: Validation)

Uso: `/validate [feature] [TASK-X.Y.Z]`

## Quem executa
- @QA

## Passos

1. Localizar TASK-X.Y.Z em `.context/DOCS/TASKS/[feature]-tasks.md`
2. Identificar workspace(s) tocado(s)
3. Rodar gates aplicáveis:

```bash
# api/
cd api && composer gate:all

# gateway/
pnpm --filter gateway lint
pnpm --filter gateway test
pnpm --filter gateway build

# app/
pnpm --filter app lint
pnpm --filter app test
pnpm --filter app build

# electron/
pnpm --filter electron build
```

4. Verificar critérios de aceite (seção E do T.A.C.E)
5. Verificar coverage:
   - Backend: `composer test -- --coverage` ≥ 80%
   - App: Vitest ≥ 70%
6. Verificar testes específicos (multi-tenant isolamento, idempotência se webhook)

## Decisão

- ✅ Aprovado → status pré-CONFIRM, segue para `/confirm-task`
- ❌ Reprovado → status `❌ Reprovada`, volta para EXECUTION

## Output
Evidências (output dos gates) e decisão.
