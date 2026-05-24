# Workflow PREVC — InteraZap

**PREVC:** Pré-Planning → Review → Execution → Validation → Confirm

---

## Fases

### PRÉ-PLANNING
**Agent:** PLANNER (modo BRANDING)
**Quando:** Ideia bruta sem escopo definido
**Output:** PRD em `.context/DOCS/PRDS/NNNN-PRD-<topic>.md`

```
/prevec-new-plan [ideia]
```

---

### PLANNING
**Agent:** PLANNER (modo PM + ARCHITECT + DESIGNER)
**Quando:** PRD aprovado
**Output:**
- Feature doc em `.context/DOCS/FEATURES/[feature].md`
- Tasks em `.context/DOCS/TASKS/[feature]-tasks.md`
- Design (se Frontend) em `.context/DESIGN/[feature]-*.md`

```
/prevec-decompose-plan [prd]
/prevec-decompose-task [feature]
```

---

### REVIEW (pré-execução)
**Agent:** REVIEWER (modo REVIEW)
**Quando:** Feature doc e tasks prontas
**Output:** Feature doc e tasks aprovados ou com lista de ajustes

---

### EXECUTION (por task)
**Agent:** BUILDER
**Quando:** Feature doc e tasks aprovados pelo REVIEWER
**Output:** Código implementado + BUILDER Log no session

```
/prevec-execute-task [feature] TASK-X.Y.Z
```

Repetir para todas as tasks da fase. Sem testes por task.

---

### PHASE-CLOSE (por fase)
**Agent:** —
**Quando:** Todas as tasks da fase estão implementadas
**Output:** Testes da fase ✅ + commit da fase + (última fase: review + gates + PR)

```
/prevec-phase-close [feature] [N]
```

Gates da fase:
- API: `composer gate:fast`
- Gateway: `pnpm --filter gateway test` + `pnpm --filter gateway build`
- App: `pnpm --filter app test` + `pnpm --filter app build`

Se testes falharem → BUILDER corrige → rodar novamente.

**Última fase:** review automático com 7 subagents + `composer gate:all` + Builder fix loop + PR.

---

### VALIDATION + CONFIRM (embutidos em phase-close)

Não são mais comandos separados por task.
- Validação: executada pelo phase-close na última fase (7 subagents)
- Confirmação: fase intermediária = commit da fase; última fase = PR

---

## Checklist de PHASE-CLOSE (obrigatório)

- [ ] Todas as tasks da fase estão 🔄 Em Progresso (nenhuma ⏳ Pendente)
- [ ] Testes da fase passando
- [ ] BUILDER Log preenchido no session para cada task
- [ ] 1 commit por fase com todas as tasks
- [ ] MEMORY atualizado se houve decisão técnica
- [ ] `project-state.yaml` atualizado
- [ ] **Última fase:** review 7 subagents + `composer gate:all` + PR

---

## Regra Inviolável

> Fase não fecha sem testes da fase passando — nunca commitar com gates vermelhos.

O review final (7 subagents) executa em **subagent distinto** na última fase para não contaminar o contexto.

---

## Fluxo Completo

```
/prevec-new-plan [ideia]
  → PLANNER cria PRD
    → /prevec-decompose-plan [prd]
      → PLANNER cria feature doc + tasks T.A.C.E por fase
        → REVIEWER revisa (modo REVIEW) — inalterado
          → Para cada task da fase em ordem:
              /prevec-execute-task [feature] TASK-X.Y.Z
              → BUILDER implementa (sem testes)
            → Ao final de cada fase:
              /prevec-phase-close [feature] [N]
              → Testes da fase → 1 commit
              → Se última fase: review 7 subagents + gates finais + Builder fix + PR
```
