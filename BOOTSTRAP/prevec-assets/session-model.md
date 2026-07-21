# PREVEC Session File — Modelo

> Um único arquivo por feature. Criado quando 2+ agents são invocados para uma task.
> Agents leem este arquivo para se interar do status atual sem re-ler arquivos de origem.
> Tasks são seções acumuladas — append a cada nova task iniciada.
> Arquivado por `prevec-finalize-execution` quando a feature for concluída (todas as tasks ✅).

---

## Como usar

1. `prevec-execute-task` cria `.context/.session/[feature]-session.md` na primeira task da feature
2. Preenche **Architecture Snapshot** lendo arquivos de origem UMA vez — nunca repetido
3. Para cada task: append nova seção `## TASK-X.Y.Z` com T.A.C.E e espaço para logs
4. BUILDER preenche **BUILDER Log** na seção da task
5. `prevec-review-execution` lê o arquivo, localiza a seção da task, preenche **REVIEWER Log**
6. `prevec-finalize-execution` preenche **Confirmação** na seção da task
7. Quando todas as tasks ✅: `prevec-finalize-execution` arquiva o arquivo inteiro

---

## Template

```markdown
# Session: [feature]

> Criado: YYYY-MM-DD HH:MM
> Agents: leia este arquivo para status atual — não re-ler arquivos de origem.

## Metadados
- Feature: [feature]
- Feature doc: .context/DOCS/FEATURES/[feature].md
- Tasks: .context/DOCS/TASKS/[feature]-tasks.md
- Status: 🔄 Em Progresso

---

## Architecture Snapshot
> Copiado de context-snapshot.md UMA vez. Subagents NÃO re-leem os originais.

### Stack
- Backend: [BACKEND_LANG + BACKEND_FRAMEWORK]
- Frontend: [FRONTEND_LANG + FRONTEND_FRAMEWORK]
- Database: [DATABASE]
- Testes: [TESTING_STACK]
- Arquitetura: [ARCHITECTURE] — camadas: [ARCHITECTURE_LAYERS]

### Regras invioláveis
- [regra 1]
- [regra N]

### Dependências de módulo
- [módulo A] pode importar: [módulo B, C]
- [módulo A] NÃO pode importar: [módulo D]

---

## TASK-X.Y.Z — [título da task]

> Status: 🔄 Em Progresso | Fase PREVC: EXECUTION
> Iniciada: YYYY-MM-DD HH:MM

### T.A.C.E
**T — Tarefa:** [copiado de tasks.md]
**A — Arquivos autorizados:**
- `path/exato/arquivo1.ext` (criar/modificar)
**Referência:** `path/arquivo-referencia.ext`
**Imports autorizados:** [lista] — proibido: [lista]
**C — Comportamento:**
ANTES: [estado atual]
DEPOIS: [estado esperado]
**E — Evidências esperadas:**
- [ ] [comando exato e resultado esperado]

### BUILDER Log
> Preenchido por prevec-execute-task ao concluir implementação.

**Arquivos modificados:**
- `path/arquivo1.ext` — [o que mudou em uma linha]

**Decisões tomadas:**
- [decisão — por que esta abordagem]

**Testes isolados:**
- [comando usado]: [✅ N passou / ❌ N falhou]

**Notas para REVIEWER:**
- [edge cases, riscos, dívida técnica criada]

### REVIEWER Log
> Preenchido por prevec-review-execution. Fase PREVC: VALIDATION

**Resultado:** [aprovado / reprovado]
**Bloqueantes:** [N] | **Médios:** [N] | **Baixos:** [N]

**Achados bloqueantes:**
- `arquivo:linha` [severidade]: [problema] — [correção sugerida]

**Para CHANGELOG:**
- Tipo: [feat / fix / refactor / test / docs / chore]
- Escopo: [módulo ou camada]
- Descrição: [uma linha imperativa em português]
- Arquivos: [lista do BUILDER Log]

**Para MEMORY:**
- Há decisão/aprendizado: [sim / não]
- Se sim: [decisão | motivo | impacto]

### Confirmação
> Preenchido por prevec-finalize-execution. Fase PREVC: CONFIRM

- Confirmada: YYYY-MM-DD HH:MM
- Commit: [hash]
- Status: ✅ Concluída

---

## TASK-X.Y.W — [título da próxima task]

> [seção appendada quando a próxima task for iniciada]
```
