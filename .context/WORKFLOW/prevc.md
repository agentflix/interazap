# PREVC — Workflow de Desenvolvimento

> Processo oficial obrigatório para toda feature do InteraZap.

## Visão Geral

```
┌───────────┐    ┌────────┐    ┌───────────┐    ┌────────────┐    ┌─────────┐
│ PLANNING  │───►│ REVIEW │───►│ EXECUTION │───►│ VALIDATION │───►│ CONFIRM │
│           │    │        │    │           │    │            │    │         │
│ Feature   │    │ Doc    │    │ Testes    │    │ QA         │    │Changelog│
│ doc       │    │ OK?    │    │ Código    │    │ Gates      │    │ Memory  │
│           │    │ Tasks  │    │           │    │            │    │ Close   │
└───────────┘    └────────┘    └───────────┘    └────────────┘    └─────────┘
```

---

## Fase 1: PLANNING

**Objetivo:** Criar documentação clara antes de qualquer código.
**Responsável:** PM ou ARCHITECT

**Ações:**
1. Identificar PRD relacionado (se existir em `DOCS/PRDS/`)
2. **Consultar MEMORY** para decisões anteriores sobre o tema
3. Analisar dependências via `.context/ARCHITECTURE/modules.yaml`
4. Definir escopo (incluído + fora de escopo)
5. Estimar complexidade (P/M/G)

**Output:** Feature doc em `DOCS/FEATURES/[feature].md`

**Comando:** `/new-feature [nome]`

---

## Fase 2: REVIEW

**Objetivo:** Validar feature doc + gerar tasks.
**Responsável:** REVIEWER ou ARCHITECT

**Ações:**
1. Verificar feature doc completo
2. Validar contra arquitetura em `.context/ARCHITECTURE/`
3. Aprovar ou solicitar ajustes
4. Se aprovada → decompor em Tasks (T.A.C.E)

**Output:** Feature aprovada + Tasks em `DOCS/TASKS/[feature]-tasks.md`

**Comandos:** `/review-feature [nome]` → `/decompose [nome]`

---

## Fase 3: EXECUTION

**Objetivo:** Implementar as tasks.
**Responsável:** DEV, BACKEND, FRONTEND, DBA

**Ações:**
1. Ler task (T.A.C.E) COMPLETAMENTE
2. Implementar respeitando arquitetura e convenções
3. Escrever testes
4. Atualizar documentação afetada

**Output:** Código + Testes

**Comando:** `/implement-task [feature] [TASK-NNN]`

---

## Fase 4: VALIDATION

**Objetivo:** Verificar qualidade.
**Responsável:** QA ou REVIEWER

**Ações:**
1. Executar gates de `.context/WORKFLOW/validation-flow.md`
2. Verificar critérios de aceite (seção E do T.A.C.E)
3. Se falhar → volta para EXECUTION

**Output:** Gates ✅ + Critérios ✅

**Comando:** `/validate [feature] [TASK-NNN]`

---

## Fase 5: CONFIRM

**Objetivo:** Registrar e encerrar.
**Responsável:** PM ou DOC

**Ações:**
1. Adicionar evidências na task
2. Marcar task como `✅ Concluída`
3. **📜 Adicionar entrada no CHANGELOG** (`DOCS/CHANGELOG/YYYY-MM-DD.md`)
4. **🧠 Registrar decisões/aprendizados em MEMORY** (`DOCS/MEMORY/`)
5. **📊 Atualizar `project-state.yaml`** (métricas)
6. Se TODAS tasks concluídas → marcar feature como `✅ Concluída`

**Output:** Task done + CHANGELOG + MEMORY + Métricas

**Comando:** `/confirm-task [feature] [TASK-NNN]`

---

## Checklist de CONFIRM (Obrigatório)

- [ ] Task marcada como ✅ no arquivo de tasks
- [ ] Evidências adicionadas (output dos testes, gates)
- [ ] Entrada no CHANGELOG do dia
- [ ] MEMORY atualizado (se houve decisão ou aprendizado)
- [ ] `project-state.yaml` atualizado (métricas)
- [ ] Se última task da feature → feature marcada como ✅
