# /implement-task — Executar Task (PREVC: Execution)

Uso: `/implement-task [feature] [TASK-X.Y.Z]`

## Quem executa
- Depende da task: @BACKEND, @GATEWAY, @FRONTEND, @DBA, @DEV, @DEBUG

## Passos

1. Ler `.context/DOCS/TASKS/[feature]-tasks.md` e localizar TASK-X.Y.Z
2. Marcar status como `🔄 Em Progresso`
3. Ler T (Tarefa), A (Arquivo), C (Comportamento), E (Evidência)
4. Identificar workspace tocado (api, gateway, app, electron)
5. Consultar regras invioláveis do agent correspondente em `.claude/agents/`
6. Implementar
7. Escrever testes (Pest, Vitest, spec.ts)
8. Encaminhar para `/validate [feature] [TASK-X.Y.Z]`

## Regras críticas por workspace

### api/
- `declare(strict_types=1)`, phpDoc, `final class`, `$fillable`, UUID, BelongsToTenant
- `composer gate:all` antes de declarar pronto

### gateway/
- TypeScript strict, idempotência, circuit breaker, HMAC
- `pnpm --filter gateway test && build`

### app/ + electron/
- Standalone components, signals, control flow novo
- Sem acesso direto a DB
- `pnpm --filter <ws> test && build`

## Output
Código + testes + status `🔄` aguardando validação.
