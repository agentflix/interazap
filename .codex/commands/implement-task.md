# Implementar Task (PREVC: Execution)

Uso: `/implement-task [feature] [TASK-NNN]`

Exemplo: `/implement-task crm-proposals TASK-3.2.1`

## Processo

1. Ler `.context/DOCS/TASKS/[feature]-tasks.md` completamente
2. Localizar `TASK-NNN` e ler T.A.C.E integralmente
3. Atualizar status para `🔄 Em Progresso` no arquivo de tasks
4. Implementar respeitando:
    - Arquitetura DDD (Domain Layer sem imports de infra)
    - Convenções do workspace (ver `AGENTS.md`)
    - Multi-tenancy: `BelongsToTenant` se tocar Platform/dados tenant
    - Skill especialista do workspace: `.context/SKILLS/[workspace]-especialista/`
5. Escrever testes (Pest, Vitest ou `spec.ts`)
6. Verificar que comportamento C (antes→depois) está correto
7. Ler a skill `code-review-confiavel` e executar suas acoes
8. Preparar evidências para seção E

## Agent por Workspace

| Workspace   | Agent            | Gates                                   |
| ----------- | ---------------- | --------------------------------------- |
| `api/`      | @BACKEND ou @DBA | `composer gate:all`                     |
| `gateway/`  | @GATEWAY         | `pnpm --filter gateway lint test build` |
| `app/`      | @FRONTEND        | `pnpm --filter app lint test build`     |
| `electron/` | @FRONTEND        | `pnpm --filter electron build`          |

## Saída Esperada

```
TASK-NNN: implementada
Evidências: [output dos testes]
Próximo: /validate [feature] TASK-NNN
```
