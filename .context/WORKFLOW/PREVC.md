# Workflow PREVC — InteraZap

**PREVC:** Pré-Planning → Review → Execution → Validation → Confirm

O ORCHESTRATOR é o ponto de entrada padrão: recebe o pedido, classifica o escopo e delega.
Nenhum agent implementa fora da fase que lhe pertence.

---

## Roteamento inicial (ORCHESTRATOR)

| Escopo do pedido | Rota |
|---|---|
| Ideia nova, feature, mudança de arquitetura | PLANNER via `/prevec-decompose-plan` |
| Feature já decomposta em tasks | BUILDER via `/prevec-execute-task` |
| 1–2 arquivos, um único bounded context, sem arquivo novo | VIBE-CODER |
| Código pronto aguardando revisão | REVIEWER |

> Regra: 3+ arquivos, múltiplos bounded contexts ou criação de arquivos → sempre passa pelo planejamento formal.

---

## Fases

### PLANNING — PLANNER
**Entrada:** ideia bruta ou PRD existente
**Comando:** `/prevec-decompose-plan [ideia|prd]`
**Saída (o que for pedido no início da skill):**
- PRD em `.context/DOCS/PRDS/NNNN-PRD-<topic-kebab>.md`
- Feature doc em `.context/DOCS/FEATURES/[feature].md`
- Tasks T.A.C.E por fase em `.context/DOCS/TASKS/[feature]-tasks.md`
- Design em `.context/DESIGN/[feature]-[tipo].md` quando houver UI

A skill é o ponto de entrada unificado — absorve `/prevec-new-plan` e `/prevec-decompose-task`,
que permanecem disponíveis como atalhos para etapas isoladas.

---

### REVIEW (pré-execução) — REVIEWER → reviewer-doc
**Quando:** feature doc e tasks prontas, antes de qualquer código
**Verifica:** completude do T.A.C.E, consistência com `.context/ARCHITECTURE/`, ordem e dependências entre tasks
**Saída:** aprovado, ou lista de ajustes de volta para o PLANNER

---

### EXECUTION (por task) — BUILDER
**Comando:** `/prevec-execute-task [feature] TASK-X.Y.Z`
**Saída:** código implementado + BUILDER Log no session file da feature

O BUILDER é um router:

| Subagent | Quando |
|---|---|
| `builder-explore` | precisa entender o código antes de escrever — mapeia canônicos e padrões |
| `builder-write` | plano claro, arquivos mapeados — escreve código, migrations, componentes, testes |
| `builder-debug` | `builder-write` falhou, bug multifatorial ou causa raiz não óbvia |

Sem testes por task — os testes rodam no fechamento da fase.

---

### PHASE-CLOSE (por fase) — REVIEWER
**Comando:** `/prevec-phase-close [feature] [N]`
**Saída:** testes da fase ✅ + 1 commit por fase; na última fase também review completo, gates e PR

Gates da fase (só a camada tocada):

| Camada | Comando |
|---|---|
| api | `cd api && composer gate:fast` |
| gateway | `pnpm --filter gateway test && pnpm --filter gateway build` |
| app | `pnpm --filter app test:run && pnpm --filter app build` |

Falhou → volta para o BUILDER → roda de novo. Fase não fecha com gate vermelho.

**Última fase:** `reviewer-code` executa `code-review-confiavel` com 7 subagents +
`cd api && composer gate:all` + loop de correção do BUILDER + abertura do PR.

---

### VALIDATION + CONFIRM
Embutidos no `phase-close`. Não existem mais como comandos por task.
- Validação: 7 subagents na última fase, em subagent distinto para não contaminar contexto
- Confirmação: fase intermediária = commit; última fase = PR

> `/prevec-review-execution` e `/prevec-finalize-execution` continuam disponíveis para
> revisar ou fechar uma task isolada fora do fluxo de fases.

---

## Checklist de PHASE-CLOSE (obrigatório)

- [ ] Nenhuma task da fase ainda ⏳ Pendente
- [ ] BUILDER Log preenchido no session file para cada task
- [ ] Testes da fase passando
- [ ] 1 commit por fase, Conventional Commits em português
- [ ] MEMORY escrito se houve decisão, armadilha ou aprendizado
- [ ] `project-state.yaml` atualizado
- [ ] **Última fase:** review 7 subagents + `composer gate:all` + PR

---

## Regras Invioláveis

1. Fase não fecha sem os testes da fase passando — nunca commitar com gate vermelho.
2. Task implementada não vai para CONFIRM sem aprovação explícita do REVIEWER.
3. O review roda em subagent distinto do BUILDER.
4. Todo agent termina mostrando o próximo comando com argumentos reais.

---

## Fluxo Completo

```
/prevec-decompose-plan [ideia|prd]
  → PLANNER: PRD → feature doc → tasks T.A.C.E por fase
    → REVIEWER (reviewer-doc) valida tasks
      → Para cada task da fase:
          /prevec-execute-task [feature] TASK-X.Y.Z
          → BUILDER: explore → write (→ debug se travar)
        → Ao fim da fase:
          /prevec-phase-close [feature] [N]
          → testes da fase → 1 commit
          → última fase: 7 subagents + composer gate:all + fix loop + PR
```
