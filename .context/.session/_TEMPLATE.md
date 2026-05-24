# PREVEC Session File — Modelo

> Um único arquivo por feature. Criado quando 2+ agents são invocados para uma task.
> Agents leem este arquivo UMA VEZ completo — não fazer múltiplas leituras parciais.
> Uma leitura = um cache write. Releituras = cache hits.
> Tasks são seções acumuladas — com paginação automática após 2 tasks concluídas.
> Arquivado por `prevec-finalize-execution` quando a feature for concluída (todas as tasks ✅).

---

## Como usar

1. `prevec-execute-task` cria `.context/.session/[feature]-session.md` na primeira task da feature
2. **Bloco 1 (Architecture Snapshot) é o primeiro bloco** — conteúdo estável para cache hit
3. Para cada task: verificação defensiva de tamanho → paginação se necessário → append nova seção
4. BUILDER preenche **BUILDER Log** na seção da task
5. `prevec-review-execution` lê o arquivo UMA VEZ, localiza a seção da task, preenche **REVIEWER Log**
6. `prevec-finalize-execution` preenche **Confirmação** e executa paginação se tasks_concluidas > 2
7. Quando todas as tasks ✅: `prevec-finalize-execution` arquiva o arquivo inteiro

### Paginação automática

Quando tasks_concluidas > 2:
- Tasks além das 2 mais recentes → `.context/.session/archive/[feature]-archive.md`
- Seção removida substituída por resumo de 3 linhas no Bloco 4

Verificação defensiva em `prevec-execute-task` (antes de qualquer append):
```bash
wc -c .context/.session/[feature]-session.md
```
SE > 15.000 bytes → PARAR e alertar: "⚠️ Session inflado. Execute /session-archive [feature] antes de continuar."

---

## Template

```markdown
# Session: [feature]

> Criado: YYYY-MM-DD HH:MM
> Leia este arquivo UMA VEZ completo — não re-ler parcialmente.

---

## Architecture Snapshot
> [BLOCO 1 — IMUTÁVEL] Copiado de context-snapshot.md UMA vez. Primeiro bloco para cache hit.
> Subagents NÃO re-leem os originais.

[colar conteúdo completo de .context/ARCHITECTURE/context-snapshot.md]

---

## Metadados
> [BLOCO 2 — ESTÁVEL] Muda por feature, não por task.

- Feature: [feature]
- Feature doc: .context/DOCS/FEATURES/[feature].md
- Tasks: .context/DOCS/TASKS/[feature]-tasks.md
- Status: 🔄 Em Progresso

---

## Tasks Recentes Concluídas
> [BLOCO 4 — RESUMO] Últimas 2 tasks concluídas. Tasks mais antigas no archive.
> Formato: título + resultado + commit. Tasks ativas NÃO aparecem aqui.

<!-- Preenchido automaticamente pela paginação — vazio no início da feature -->

---

## TASK-X.Y.Z — [título da task]
> [BLOCO 3 — ATIVO] Task em execução atual.

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
> [BLOCO 5 — LOGS] Preenchido por prevec-execute-task ao concluir implementação.

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
**Tier de review:** [Tier N — DIFF_SIZE=X linhas, N reviewers]
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

---

## Formato de resumo (Bloco 4 — tasks paginadas)

```markdown
### [TASK-X.Y.Z] — ✅ [título] (commit: abc1234)
> [tipo(escopo): descrição do CHANGELOG] | Gates: api ✅ gateway ✅ app ✅
```

---

## Archive

Tasks paginadas vão para `.context/.session/archive/[feature]-archive.md`.
Nunca lido automaticamente — apenas via `/session-archive show [feature]`.
