# PREVEC Session File — Modelo

> Template usado por `prevec-execute-task` ao iniciar uma task.
> Cada subagent LÊ este arquivo em vez de re-ler os arquivos de origem.
> Arquivado por `prevec-finalize-execution` ao confirmar a task.

---

## Como usar

1. `prevec-execute-task` cria o arquivo em `.context/.session/[feature]-TASK-X.Y.Z.md`
2. Preenche a seção **Context Snapshot** lendo os arquivos de origem UMA vez
3. BUILDER lê o session — não re-lê os arquivos de origem — e preenche **BUILDER Log**
4. `prevec-review-execution` lê o session — não re-lê contexto — e preenche **REVIEWER Log**
5. `prevec-finalize-execution` lê o session para CHANGELOG/MEMORY, depois arquiva

---

## Template

```markdown
# Session: [feature] [TASK-X.Y.Z]

## Metadata
- Feature: [feature]
- Task: TASK-X.Y.Z
- Fase PREVC: [EXECUTION / VALIDATION / CONFIRM]
- Iniciada: YYYY-MM-DD HH:MM
- Status: 🔄 Em Execução

---

## Context Snapshot
> Lido de arquivos de origem UMA vez. Subagents subsequentes NÃO re-leem os originais.

### Stack
- Backend: [BACKEND_LANG + BACKEND_FRAMEWORK]
- Frontend: [FRONTEND_LANG + FRONTEND_FRAMEWORK]
- Database: [DATABASE]
- Testes: [TESTING_STACK]
- Arquitetura: [ARCHITECTURE] — camadas: [ARCHITECTURE_LAYERS]

### Regras invioláveis (de project-brain.yaml)
- [regra 1]
- [regra 2]
- [regra N]

### Feature (resumo)
- ID: FEAT-NNN
- Objetivo: [2 linhas do feature.md]
- Bounded Context: [módulo]
- Fases desta feature: [lista das fases marcadas]

### T.A.C.E desta task
**T — Tarefa:** [copiado de tasks.md]
**A — Arquivos autorizados:**
- `path/exato/arquivo1.ext` (criar/modificar)
- `path/exato/arquivo2.ext` (criar/modificar)
**C — Comportamento:**
ANTES: [copiado]
DEPOIS: [copiado]
**E — Evidências esperadas:**
- [ ] [critério 1]
- [ ] [critério 2]

### Dependências de módulo (de dependencies.yaml)
- [módulo A] pode importar: [módulo B, C]
- [módulo A] NÃO pode importar: [módulo D]

---

## BUILDER Log
> Preenchido pelo BUILDER ao concluir a implementação.

### Arquivos modificados
- `path/arquivo1.ext` — [o que mudou em uma linha]
- `path/arquivo2.ext` — [o que mudou em uma linha]

### Decisões tomadas
- [decisão 1 — por que esta abordagem]
- [decisão 2]

### Gates
- Lint: [✅ passou / ❌ falhou — detalhes]
- Type check: [✅ / ❌]
- Testes: [✅ N passou / ❌ N falhou]
- Build: [✅ / ❌]

### Notas para REVIEWER
- [edge cases encontrados]
- [dívida técnica criada — se houver]
- [áreas de risco que merecem atenção especial]
- [o que NÃO foi feito e por quê — se dentro do escopo]

---

## REVIEWER Log
> Preenchido por prevec-review-execution após os 7 revisores.

### Resultado
- Aprovado: [sim / não]
- Bloqueantes: [N]
- Médios: [N]
- Baixos: [N]

### Achados bloqueantes (se houver)
- `arquivo:linha` [severidade]: [problema] — [correção sugerida]

### Para CHANGELOG
- Tipo: [feat / fix / refactor / test / docs / chore]
- Escopo: [módulo ou camada]
- Descrição: [uma linha imperativa em português]
- Arquivos principais: [lista]

### Para MEMORY
- Há decisão/aprendizado relevante: [sim / não]
- Se sim: [decisão tomada | motivo | impacto]

---

## Arquivo
> Preenchido por prevec-finalize-execution.

- Confirmada: YYYY-MM-DD HH:MM
- Commit: [hash]
- Movido para: `.context/.session/.archive/[feature]-TASK-X.Y.Z.md`
```
