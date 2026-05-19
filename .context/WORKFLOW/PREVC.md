# Workflow PREVC — InteraZap

## Visão Geral

PREVC = Pré-Planning → Planning → Review → Execution → Validation → Confirm

```
/prevec-new-plan [ideia]
  → /prevec-decompose-plan [prd]
    → /prevec-decompose-task [feature]
      → /prevec-execute-task [feature] TASK-X.Y.Z
        → /prevec-review-execution [feature] TASK-X.Y.Z
          → /prevec-finalize-execution [feature] TASK-X.Y.Z
```

---

## Fases

### PRÉ-PLANNING — Ideia → PRD

**Agent:** PLANNER (modo BRANDING)
**Skill:** `/prevec-new-plan [ideia]`
**Output:** `.context/DOCS/PRDS/NNNN-PRD-<topic>.md`

1. PLANNER lê skill `brainstorming` (`.context/skills/brainstorming/SKILL.md`)
2. Conduz brainstorming → 2-3 abordagens → aprovação do usuário
3. Salva PRD em `.context/DOCS/PRDS/`

---

### PLANNING — PRD → Feature + Tasks

**Agent:** PLANNER (modos PM + ARCHITECT)
**Skills:** `/prevec-decompose-plan [prd]` → `/prevec-decompose-task [feature]`
**Outputs:** `.context/DOCS/FEATURES/[feature].md` + `.context/DOCS/TASKS/[feature]-tasks.md`

1. PLANNER lê PRD → cria feature doc
2. Consulta `.context/DOCS/MEMORY/` — decisões anteriores
3. Consulta `.context/ARCHITECTURE/modules.yaml` — dependências
4. Decompõe em tasks T.A.C.E hierárquicas (TASK-X.Y.Z)
5. Features com Frontend: PLANNER (modo DESIGNER) cria artefatos em `.context/DESIGN/`

---

### REVIEW (pré-EXECUTION) — Validação de Planejamento

**Agent:** REVIEWER (modo REVIEW)
**Checklist:**
- [ ] Feature doc: todos os campos preenchidos?
- [ ] Tasks: cada task tem T, A, C, E?
- [ ] Seção A específica (sem "vários arquivos")?
- [ ] Seção E verificável (sem "funciona corretamente")?
- [ ] Dependências entre tasks corretas?
- [ ] Artefatos de design em `.context/DESIGN/` para tasks de Frontend?

---

### EXECUTION — Implementação

**Agent:** BUILDER
**Skill:** `/prevec-execute-task [feature] TASK-X.Y.Z`

Ordem por tipo de task:
1. TASK-1.x — Planning (migrations, schemas)
2. TASK-2.x — Design (se houver)
3. TASK-3.x — Backend (Laravel 12)
4. TASK-4.x — Gateway (NestJS 11)
5. TASK-5.x — Frontend (Angular 17)
6. TASK-6.x — Integration (cross-layer)

**BUILDER Inviolable:**
- Ler task T.A.C.E COMPLETA antes de qualquer código
- Modificar APENAS arquivos da seção A
- Rodar gates antes de sinalizar completo

---

### VALIDATION — Code Review

**Agent:** REVIEWER (modo VALIDATION) em **subagent distinto**
**Skill:** `/prevec-review-execution [feature] TASK-X.Y.Z`

1. Carrega `.context/skills/code-review-confiavel/SKILL.md`
2. Executa 7 revisores
3. Roda gates reais:
   ```bash
   # Backend
   composer gate:all
   # Gateway
   pnpm lint && pnpm test
   # Frontend
   pnpm lint && pnpm build && pnpm test
   ```
4. Second pass: reler diff
5. Meta-review: descartar achados especulativos

**Resultado:**
- Bloqueantes → BUILDER corrige → VALIDATION repete
- Sem bloqueantes → CONFIRM

---

### CONFIRM — Fechamento

**Agent:** REVIEWER (modo CONFIRM)
**Skill:** `/prevec-finalize-execution [feature] TASK-X.Y.Z`

Checklist obrigatório:
- [ ] Task marcada como ✅ em `.context/DOCS/TASKS/[feature]-tasks.md`
- [ ] REVIEWER executou `code-review-confiavel` em subagent e aprovou
- [ ] Evidências adicionadas (output dos testes, gates)
- [ ] MEMORY atualizado se houve decisão/aprendizado
- [ ] `project-state.yaml` atualizado (tasks_completed++)
- [ ] `context-snapshot.md` regenerado se `.context/ARCHITECTURE/` foi modificado
- [ ] Se última task da feature → feature marcada como ✅

---

## Regra Inviolável: REVIEWER obrigatório em toda task

> Task sem aprovação explícita do REVIEWER não avança para CONFIRM — nunca.

**Por quê:**
- Detecta alucinações do BUILDER (imports inexistentes, assinaturas erradas)
- Verifica quebra de contrato entre camadas (API ↔ Gateway ↔ Frontend)
- Verifica tenant isolation em dados de usuário
- Garante que o código bate com a task T.A.C.E (grounding)
- Reduz falsos positivos via meta-review dos 7 revisores

O REVIEWER executa em **subagent distinto** para não contaminar o contexto do BUILDER.

---

## Nomenclatura T.A.C.E

```
TASK-X.Y.Z
├── X = Fase (1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação
```

Cada task:
- **T** (Tarefa): o que fazer
- **A** (Arquivo): arquivos exatos a modificar/criar
- **C** (Comportamento): estado antes → estado depois
- **E** (Evidência): critério verificável de conclusão
