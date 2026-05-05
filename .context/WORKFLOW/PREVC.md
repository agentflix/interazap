# PREVC — Workflow de Desenvolvimento do InteraZap

> Processo oficial obrigatório para toda feature do InteraZap.

## Visão Geral

```text
+-----------+    +--------+    +-----------+    +------------+    +---------+
| PLANNING  |--->| REVIEW |--->| EXECUTION |--->| VALIDATION |--->| CONFIRM |
|           |    |        |    |           |    |            |    |         |
| Feature   |    | Doc OK?|    | Testes    |    | Gates      |    |Changelog|
| doc       |    | Tasks  |    | Codigo    |    | QA/REVIEW  |    | Memory  |
+-----------+    +--------+    +-----------+    +------------+    +---------+
```

---

## Fase 1: PLANNING

**Objetivo:** Criar documentação clara antes de qualquer código.
**Responsável:** PM (com apoio de ARCHITECT e DESIGNER)

**Ações:**

1. Identificar PRD relacionado (se existir em `.context/DOCS/PRDS/`)
2. **Consultar MEMORY** para decisões anteriores sobre o tema
3. Analisar dependências via `.context/ARCHITECTURE/modules.yaml`
4. Identificar bounded context(s) afetado(s)
5. Definir escopo (incluído + fora de escopo)
6. Estimar complexidade (P/M/G)
7. (Se UI) DESIGNER especifica wireframes em `.context/LAYOUT/`

**Output:** Feature doc em `.context/DOCS/FEATURES/[feature].md`

**Comando:** `/new-feature [nome]`

---

## Fase 2: REVIEW

**Objetivo:** Validar feature doc + gerar tasks.
**Responsável:** REVIEWER + ARCHITECT

**Ações:**

1. Verificar feature doc completo (checklist do REVIEWER)
2. Validar contra arquitetura em `.context/ARCHITECTURE/`
3. Aprovar ou solicitar ajustes
4. Se aprovada → ARCHITECT decompõe em Tasks T.A.C.E hierárquicas (FASE.FEATURE.ETAPA)

**Output:** Feature aprovada + Tasks em `.context/DOCS/TASKS/[feature]-tasks.md`

**Comandos:** `/review-feature [nome]` → `/decompose [nome]` → `/validate-tasks [nome]`

---

## Fase 3: EXECUTION

**Objetivo:** Implementar as tasks.
**Responsável:** DEV / BACKEND / GATEWAY / FRONTEND / DBA

**Ações:**

1. Ler task (T.A.C.E) COMPLETAMENTE
2. Implementar respeitando arquitetura DDD e convenções de cada workspace
3. Escrever testes (Pest, Vitest, spec.ts)
4. Atualizar documentação afetada

**Output:** Código + Testes

**Comando:** `/implement-task [feature] [TASK-NNN]`

**Ordem padrão para features cross-workspace:**

```
DBA -> BACKEND -> GATEWAY -> FRONTEND
```

---

## Fase 4: VALIDATION

**Objetivo:** Verificar qualidade.
**Responsável:** QA (apoiado por REVIEWER)

**Ações:**

1. Executar gates de `.context/WORKFLOW/validation-flow.md`:
    - `api/`: `composer gate:all`
    - `gateway/`: `pnpm --filter gateway lint && test && build`
    - `app/`: `pnpm --filter app lint && test && build`
    - `electron/`: `pnpm --filter electron build`
2. Verificar critérios de aceite (seção E do T.A.C.E)
3. Se falhar → volta para EXECUTION

**Output:** Gates passando + Critérios de aceite atendidos

**Comando:** `/validate [feature] [TASK-NNN]`

---

## Fase 5: CONFIRM

**Objetivo:** Registrar e encerrar.
**Responsável:** PM / DOC / GIT_COMMIT

**Ações:**

1. Adicionar evidências na task
2. Marcar task como `Concluída`
3. **Adicionar entrada no CHANGELOG** (`DOCS/CHANGELOG/YYYY-MM-DD.md`)
4. **Registrar decisões/aprendizados em MEMORY** (`DOCS/MEMORY/`)
5. **Atualizar `project-state.yaml`** (métricas)
6. **Commit semântico** (GIT_COMMIT)
7. Se TODAS tasks concluídas → marcar feature como `Concluída`

**Output:** Task done + CHANGELOG + MEMORY + Métricas + Commit

**Comando:** `/confirm-task [feature] [TASK-NNN]`

---

## Checklist de CONFIRM (Obrigatório)

- [ ] Task marcada como ok no arquivo de tasks
- [ ] Evidências adicionadas (output dos testes, gates)
- [ ] Entrada no CHANGELOG do dia
- [ ] MEMORY atualizado (se houve decisão ou aprendizado)
- [ ] `project-state.yaml` atualizado (métricas)
- [ ] Commit semântico criado (não pushar sem ordem)
- [ ] Se última task da feature → feature marcada como concluída

---

## Tasks Hierárquicas (TASK-X.Y.Z)

| Nível | Significado            | Exemplo                                                               |
| ----- | ---------------------- | --------------------------------------------------------------------- |
| X     | Fase                   | 1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration |
| Y     | Feature dentro da fase | 1, 2, 3...                                                            |
| Z     | Etapa de codificação   | 1, 2, 3...                                                            |

Exemplo: `TASK-3.2.1` = Backend (3) → Domain (2) → Criar Entity (1)
