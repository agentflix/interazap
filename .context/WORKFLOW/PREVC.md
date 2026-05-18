# PREVC — Workflow de Desenvolvimento do InteraZap

Processo oficial obrigatório para toda feature do InteraZap.

## Visão Geral

```text
+-----------+    +--------+    +-----------+    +------------+    +---------+
| PLANNING  |--->| REVIEW |--->| EXECUTION |--->| VALIDATION |--->| CONFIRM |
|           |    |        |    |           |    |            |    |         |
| Doc       |    | Doc OK?|    | Testes    |    | Gates      |    |Changelog|
| funcional |    | Tasks  |    | Código    |    | QA/REVIEW  |    | Memory  |
+-----------+    +--------+    +-----------+    +------------+    +---------+
```

## Fase 1: PLANNING

**Objetivo:** criar documentação clara antes de qualquer código.
**Responsável:** PM, com apoio de ARCHITECT e DESIGNER.

**Ações:**

1. Identificar PRD relacionado, se existir em `.context/DOCS/PRDS/`.
2. Consultar MEMORY para decisões anteriores sobre o tema.
3. Analisar dependências via `.context/ARCHITECTURE/modules.yaml`.
4. Identificar bounded contexts afetados.
5. Definir escopo incluído e fora de escopo.
6. Estimar complexidade: P, M ou G.
7. Se houver UI, DESIGNER especifica wireframes em `.context/LAYOUT/`.

**Output:** documentação funcional em `.context/DOCS/FEATURES/[nome].md`.

**Comando:** `/new-feature [nome]`.

## Fase 2: REVIEW

**Objetivo:** validar documentação funcional e gerar tasks.
**Responsável:** REVIEWER + ARCHITECT.

**Ações:**

1. Verificar documentação funcional completa usando o checklist do REVIEWER.
2. Validar contra arquitetura em `.context/ARCHITECTURE/`.
3. Aprovar ou solicitar ajustes.
4. Se aprovada, ARCHITECT decompõe em tasks T.A.C.E hierárquicas.

**Output:** feature aprovada + tasks em `.context/DOCS/TASKS/[feature]-tasks.md`.

**Comandos:** `/review-feature [nome]` -> `/decompose [nome]` -> `/validate-tasks [nome]`.

## Fase 3: EXECUTION

**Objetivo:** implementar as tasks.
**Responsável:** DEV / BACKEND / GATEWAY / FRONTEND / DBA.

**Ações:**

1. Ler a task T.A.C.E completamente.
2. Implementar respeitando a arquitetura DDD e as convenções de cada workspace.
3. Escrever testes: Pest, Vitest ou `spec.ts`.
4. Atualizar documentação afetada.

**Output:** código + testes.

**Comando:** `/implement-task [feature] [TASK-NNN]`.

**Ordem padrão para features cross-workspace:**

```text
DBA -> BACKEND -> GATEWAY -> FRONTEND
```

## Fase 4: VALIDATION

**Objetivo:** verificar qualidade.
**Responsável:** QA, com apoio de REVIEWER.

**Ações:**

1. Executar os gates de `.context/WORKFLOW/validation-flow.md`.
2. Verificar critérios de aceite da seção E do T.A.C.E.
3. Se falhar, voltar para EXECUTION.

**Output:** gates passando + critérios de aceite atendidos.

**Comando:** `/validate [feature] [TASK-NNN]`.

## Fase 5: CONFIRM

**Objetivo:** registrar e encerrar.
**Responsável:** PM / DOC / GIT_COMMIT.

**Ações:**

1. Adicionar evidências na task.
2. Marcar task como `✅ Concluída`.
3. Adicionar entrada no CHANGELOG: `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`.
4. Registrar decisões e aprendizados em `.context/DOCS/MEMORY/`.
5. Atualizar `.context/ARCHITECTURE/project-state.yaml` com métricas.
6. Criar commit semântico, sem push automático.
7. Se todas as tasks foram concluídas, marcar a feature como `✅ Concluída`.

**Output:** task done + CHANGELOG + MEMORY + métricas + commit.

**Comando:** `/confirm-task [feature] [TASK-NNN]`.

## Checklist de CONFIRM

- [ ] Task marcada como ✅ Concluída no arquivo de tasks.
- [ ] Evidências adicionadas: testes, gates e validações relevantes.
- [ ] Entrada no CHANGELOG do dia.
- [ ] MEMORY atualizado, se houve decisão ou aprendizado.
- [ ] `project-state.yaml` atualizado, quando aplicável.
- [ ] Commit semântico criado, sem push automático.
- [ ] Se for a última task da feature, feature marcada como ✅ Concluída.

## Tasks Hierárquicas

| Nível | Significado | Exemplo |
|-------|-------------|---------|
| X | Fase | 1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration |
| Y | Feature dentro da fase | 1, 2, 3... |
| Z | Etapa de codificação | 1, 2, 3... |

Exemplo: `TASK-3.2.1` = Backend (3) → Domain (2) → Criar Entity (1).

## Agents por Fase

| Fase | Agents |
|------|--------|
| Planning | PM, ARCHITECT, DESIGNER |
| Review | REVIEWER, ARCHITECT |
| Execution | BACKEND, GATEWAY, FRONTEND, DBA, DEV |
| Validation | QA, REVIEWER |
| Confirm | PM, DOC, GIT_COMMIT |
