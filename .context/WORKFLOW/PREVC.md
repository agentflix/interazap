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

### EXECUTION
**Agent:** BUILDER
**Quando:** Feature doc e tasks aprovados pelo REVIEWER
**Output:** Código implementado + testes isolados passando

```
/prevec-execute-task [feature] TASK-X.Y.Z
```

Session em `.context/.session/[feature]-TASK-X.Y.Z.md` criado pelo BUILDER ao iniciar.

---

### VALIDATION
**Agent:** REVIEWER (modo VALIDATION) — **em subagent distinto**
**Quando:** BUILDER sinaliza task concluída
**Output:** Achados por severidade + gates executados + risco residual

```
/prevec-review-execution [feature] TASK-X.Y.Z
```

Gates obrigatórios:
- API: `php artisan test`
- Gateway: `pnpm --filter gateway test` + `pnpm --filter gateway build`
- App: `pnpm --filter app test` + `pnpm --filter app build`

Achados bloqueantes → volta para BUILDER.

---

### CONFIRM
**Agent:** REVIEWER (modo CONFIRM)
**Quando:** Validation sem bloqueantes
**Output:** Task ✅ + MEMORY (se decisão técnica) + commit semântico

```
/prevec-finalize-execution [feature] TASK-X.Y.Z
```

---

## Checklist de CONFIRM (obrigatório)

- [ ] Task marcada como ✅ no arquivo de tasks
- [ ] **REVIEWER executou `code-review-confiavel` em subagent distinto e aprovou**
- [ ] Evidências adicionadas (output dos testes, gates)
- [ ] MEMORY atualizado se houve decisão/aprendizado
- [ ] `project-state.yaml` atualizado (métricas)
- [ ] Se última task da feature → feature marcada como ✅

---

## Regra Inviolável

> Task sem aprovação explícita do REVIEWER não avança para CONFIRM — nunca.

O REVIEWER executa em **subagent distinto** para não contaminar o contexto do BUILDER com o review.

---

## Fluxo Completo

```
/prevec-new-plan [ideia]
  → PLANNER cria PRD
    → /prevec-decompose-plan [prd]
      → PLANNER cria feature doc
        → /prevec-decompose-task [feature]
          → PLANNER cria tasks T.A.C.E
            → REVIEWER revisa (modo REVIEW)
              → /prevec-execute-task [feature] TASK-X.Y.Z
                → BUILDER implementa
                  → /prevec-review-execution [feature] TASK-X.Y.Z
                    → REVIEWER valida (subagent)
                      → /prevec-finalize-execution [feature] TASK-X.Y.Z
                        → REVIEWER confirma + commit
```
