# /confirm-task — Fechar Task (PREVC: Confirm)

Uso: `/confirm-task [feature] [TASK-X.Y.Z]`

## Quem executa
- @DOC + @GIT_COMMIT (com apoio de @PM se última task da feature)

## Pré-requisito
- Task aprovada em `/validate`

## Passos

### 1. Verificar VALIDATION
- Todos os gates passaram?
- Critérios de aceite (seção E) atendidos?
- Se não → abortar e voltar para EXECUTION

### 2. Registrar Evidências na Task
- Output dos testes (resumido)
- Output dos gates (resumido)
- Resumo do que foi implementado
- Marcar task como `✅ Concluída`

### 3. Atualizar CHANGELOG
Abrir/criar `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`. Adicionar entrada:

```text
- [HH:MM] [TIPO] [escopo]: Descrição concisa
  - Detalhes
  - Arquivos: lista
  - Ref: TASK-X.Y.Z / FEAT-NNN
```

Tipos: `FEAT`, `FIX`, `REFACTOR`, `DOCS`, `TEST`, `CHORE`, `BREAKING`
Escopos: `api`, `gateway`, `app`, `electron`, `landing`, `infra`, `db`, `ci`, `docs`, `repo`

Se o arquivo do dia não existir, criar a partir de `_TEMPLATE.md`.

### 4. Atualizar MEMORY (se aplicável)
Pergunte:
- Decisão técnica relevante? (Decisão)
- Algo inesperado? (Aprendizado)
- Armadilha? (Armadilha)
- Padrão novo? (Insight)

Se SIM para qualquer → criar `.context/DOCS/MEMORY/YYYY-MM-DD-titulo-kebab.md` (template `_TEMPLATE.md`).

### 5. Atualizar project-state.yaml
- `tasks_completed`++
- `tasks_in_progress`--
- `last_validation` = hoje
- Se última task da feature → `features_completed`++

### 6. Verificar Feature Completa
- Todas as tasks `✅`?
- Se sim → marcar feature como `✅ Concluída` no doc
- Adicionar entrada de resumo no CHANGELOG

### 7. Commit Semântico (@GIT_COMMIT)
Conventional Commits, escopo correto, ref à task.

## Output

```text
✅ TASK-X.Y.Z Concluída

CHANGELOG: entrada adicionada em YYYY-MM-DD.md
MEMORY: criado / não necessário
Métricas: [N/M] tasks da feature
Commit: <hash>
Próxima: TASK-X.Y.(Z+1) ou "Feature completa"
```
