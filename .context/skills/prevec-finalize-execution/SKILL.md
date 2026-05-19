---
name: prevec-finalize-execution
description: Finaliza uma task aprovada no workflow PREVEC — lê o session file da feature para dados prontos de CHANGELOG/MEMORY, marca task concluída, cria commit e abre PR se feature completa. Arquiva o session file apenas quando TODAS as tasks da feature forem ✅. Triggers: "finalizar task", "confirmar task", "prevec-finalize-execution". Do NOT use antes do prevec-review-execution aprovar ou para tasks reprovadas.
metadata:
  author: prevec
  version: '2.0.0'
---

# prevec-finalize-execution

Confirma task aprovada usando dados do session file — zero re-derivação de contexto.
O session file só é arquivado quando a feature inteira for concluída.

## Input

```
/prevec-finalize-execution [feature] [TASK-X.Y.Z]
```

Exemplo: `/prevec-finalize-execution importacao-csv TASK-3.1.1`

## Pré-condições

- Session file existe em `.context/.session/[feature]-session.md`
- Seção `## TASK-X.Y.Z` tem `Fase PREVC: CONFIRM` e REVIEWER Log com `Resultado: aprovado`

## Processo

### 1. Ler session file

Ler `.context/.session/[feature]-session.md`.
Localizar seção `## TASK-X.Y.Z` — contém tudo que precisa:

- **BUILDER Log:** arquivos modificados (para git add, CHANGELOG)
- **REVIEWER Log → Para CHANGELOG:** tipo, escopo, descrição prontos
- **REVIEWER Log → Para MEMORY:** decisão/aprendizado, se houver

**NÃO re-ler:** tasks.md, feature.md, CHANGELOG existente para derivar contexto.

### 2. Marcar task como concluída

Em `.context/DOCS/TASKS/[feature]-tasks.md`:
- `[ ] **TASK-X.Y.Z** 🔄` → `[x] **TASK-X.Y.Z** ✅`
- `**Status:** 🔄 Em Progresso` → `**Status:** ✅ Concluída`
- Atualizar contador no cabeçalho: `Pendentes: N-1 | Concluídas: N+1`

Atualizar seção no session file:
```
> Status: ✅ Concluída | Fase PREVC: CONFIRM
```

### 3. CHANGELOG (condicional)

```bash
test -d .context/DOCS/CHANGELOG && echo "ativo" || echo "inativo"
```

Se a pasta **não existe**: pular este passo inteiro — não criar arquivo, não registrar nada.

Se existe: usar diretamente **REVIEWER Log → Para CHANGELOG** do session:

```bash
# Abrir/criar .context/DOCS/CHANGELOG/[DATA-HOJE].md
```

```markdown
- [HH:MM] [TIPO] [[escopo]]: [descrição]
  - Arquivos: [lista do BUILDER Log]
  - Ref: TASK-X.Y.Z
```

### 4. MEMORY (condicional)

```bash
test -d .context/DOCS/MEMORY && echo "ativo" || echo "inativo"
```

Se a pasta **não existe**: pular este passo inteiro.

Verificar **REVIEWER Log → Para MEMORY** no session:
- `Há decisão/aprendizado: não` → pular sem registrar entrada vazia
- `Há decisão/aprendizado: sim` → criar entrada:

```markdown
## [TASK-X.Y.Z] — YYYY-MM-DD
**Decisão:** [do session]
**Motivo:** [do session]
**Impacto:** [do session]
```

Salvar em `.context/DOCS/MEMORY/[feature]-decisions.md`.

### 5. Criar commit

Usar dados do **REVIEWER Log → Para CHANGELOG** do session para construir a mensagem:

```
[tipo]([escopo]): [descrição imperativa em português]

TASK-X.Y.Z — [resumo do comportamento implementado]

Arquivos: [lista do BUILDER Log]
```

```bash
git add [arquivos do BUILDER Log — apenas seção A da task]
git commit -m "..."
```

### 6. Preencher Confirmação no session

Atualizar subseção **Confirmação** na seção `## TASK-X.Y.Z`:
```markdown
- Confirmada: YYYY-MM-DD HH:MM
- Commit: [hash]
- Status: ✅ Concluída
```

### 7. Regenerar context-snapshot (condicional)

Verificar se algum arquivo de arquitetura foi modificado nesta task:

```bash
git diff --name-only HEAD | grep ".context/ARCHITECTURE/"
```

Se sim → regenerar `.context/ARCHITECTURE/context-snapshot.md` a partir dos arquivos fonte (`project-brain.yaml`, `architecture.md`, `modules.yaml`, `dependencies.yaml`), preservando o formato exato com as seções: Stack, Regras Invioláveis, Módulos e Dependências, Convenções.

Incluir o snapshot regenerado no commit da task.

### 8. Verificar se feature está completa

```bash
grep -c "⏳ Pendente\|🔄 Em Progresso" .context/DOCS/TASKS/[feature]-tasks.md
```

**Se resultado > 0:** feature tem tasks pendentes → NÃO arquivar session.

```
✅ TASK-X.Y.Z finalizada.
📋 Feature [feature]: [N] tasks restantes.
📋 Session: .context/.session/[feature]-session.md (mantido para próximas tasks)
➡️  Próxima task: /prevec-execute-task [feature] TASK-[próxima]
```

**Se resultado = 0:** todas as tasks concluídas → ir para Passo 9.

### 9. Feature completa — Arquivar session e criar PR

Atualizar status em `.context/DOCS/FEATURES/[feature].md`:
- `Status: 🟡 Em Planning` → `Status: ✅ Concluída`

Atualizar cabeçalho do session file:
```markdown
- Status: ✅ Concluída
```

Deletar o session file:
```bash
rm .context/.session/[feature]-session.md
```

Criar pull request usando dados agregados das seções do session arquivado:

```bash
gh pr create \
  --title "[tipo]([feature]): [nome da feature em uma linha]" \
  --body "$(cat <<'EOF'
## Resumo
[2-3 bullets do que foi implementado]

## Tasks concluídas
[lista das TASK-X.Y.Z com descrição de uma linha cada — dos REVIEWER Logs das seções]

## Critérios de Aceite
[copiar da feature doc — marcar os atendidos com ✅]

## Como testar
[passos para verificar o comportamento]

🤖 Gerado com PREVEC
EOF
)"
```

## Output (task concluída, feature incompleta)

```
✅ TASK-[X.Y.Z] finalizada — [feature]
📋 Commit: [hash curto]
📋 CHANGELOG: [atualizado / não configurado]
📋 MEMORY: [entrada adicionada / sem aprendizado / não configurado]
📋 Session: mantido em .context/.session/[feature]-session.md
📋 Feature: [N] tasks restantes
➡️  Próxima: /prevec-execute-task [feature] TASK-[próxima]
```

## Output (feature completa)

```
✅ Feature [feature] concluída — todas as tasks ✅
📋 Commit: [hash curto]
📋 PR: [URL]
📋 CHANGELOG: [atualizado / não configurado]
📋 MEMORY: [N entradas / não configurado]
📋 Session: deletado
➡️  Feature entregue. Próxima: /prevec-new-plan [nova ideia]
```

## Error Handling

- Session ausente: feature pode já ter sido finalizada e session deletado. Checar se tasks estão ✅ em `.context/DOCS/TASKS/`. Se não, rodar prevec-execute-task novamente
- Seção TASK-X.Y.Z não tem `Resultado: aprovado`: recusar e direcionar para prevec-review-execution
- Commit falha (hook): corrigir o que o hook reportou, nunca usar --no-verify
- gh não instalado: orientar instalação e fornecer body do PR manualmente
- CHANGELOG/MEMORY ausentes mas configurados: criar arquivo antes de inserir
