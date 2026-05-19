---
name: prevec-finalize-execution
description: Finaliza uma task aprovada no workflow PREVEC — lê o session file para dados prontos de CHANGELOG/MEMORY, marca task concluída, cria commit e abre PR se feature completa. Arquiva o session file ao final. Triggers: "finalizar task", "confirmar task", "prevec-finalize-execution". Do NOT use antes do prevec-review-execution aprovar ou para tasks reprovadas.
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-finalize-execution

Confirma task aprovada usando dados do session file — zero re-derivação de contexto.
Arquiva o session ao final do ciclo.

## Input

```
/prevec-finalize-execution [feature] [TASK-X.Y.Z]
```

Exemplo: `/prevec-finalize-execution importacao-csv TASK-3.1.1`

## Pré-condições

- Session file existe em `.context/.session/[feature]-TASK-X.Y.Z.md`
- Session tem `Fase PREVC: CONFIRM` e REVIEWER Log com `Aprovado: sim`

## Processo

### 1. Ler session file

Ler `.context/.session/[feature]-TASK-X.Y.Z.md` — contém tudo que precisa:

- **BUILDER Log:** arquivos modificados (para git add, CHANGELOG)
- **REVIEWER Log → Para CHANGELOG:** tipo, escopo, descrição prontos
- **REVIEWER Log → Para MEMORY:** decisão/aprendizado, se houver

**NÃO re-ler:** tasks.md, feature.md, CHANGELOG existente para derivar contexto.

### 2. Marcar task como concluída

Em `.context/DOCS/TASKS/[feature]-tasks.md`:
- `[ ] **TASK-X.Y.Z** ⏳` → `[x] **TASK-X.Y.Z** ✅`
- `**Status:** ⏳ Pendente` → `**Status:** ✅ Concluída`
- Atualizar contador no cabeçalho: `Pendentes: N-1 | Concluídas: N+1`

### 3. CHANGELOG (condicional)

Se o projeto usa CHANGELOG (configurado no bootstrap):

Usar diretamente **REVIEWER Log → Para CHANGELOG** do session:

```bash
# Abrir/criar .context/DOCS/CHANGELOG/[DATA-HOJE].md
```

```markdown
- [HH:MM] [TIPO] [[escopo]]: [descrição]
  - Arquivos: [lista do BUILDER Log]
  - Ref: TASK-X.Y.Z
```

Se não usa CHANGELOG: pular.

### 4. MEMORY (condicional)

Se o projeto usa MEMORY (configurado no bootstrap):

Verificar **REVIEWER Log → Para MEMORY** no session:
- `Há decisão/aprendizado relevante: não` → pular sem registrar entrada vazia
- `Há decisão/aprendizado relevante: sim` → criar entrada:

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

### 6. Regenerar context-snapshot (condicional)

Verificar se algum arquivo de arquitetura foi modificado nesta task:

```bash
git diff --name-only HEAD | grep ".context/ARCHITECTURE/"
```

Se sim → regenerar `.context/ARCHITECTURE/context-snapshot.md` a partir dos arquivos fonte (`project-brain.yaml`, `architecture.md`, `modules.yaml`, `dependencies.yaml`), preservando o formato exato com as seções: Stack, Regras Invioláveis, Módulos e Dependências, Convenções.

Incluir o snapshot regenerado no commit da task.

### 7. Arquivar session file

```bash
mkdir -p .context/.session/.archive
mv .context/.session/[feature]-TASK-X.Y.Z.md .context/.session/.archive/[feature]-TASK-X.Y.Z.md
```

Atualizar campo **Arquivo** no session antes de mover:
- Confirmada: [data/hora]
- Commit: [hash curto]
- Movido para: `.context/.session/.archive/[feature]-TASK-X.Y.Z.md`

### 8. Verificar se feature está completa

```bash
grep -c "⏳ Pendente\|🔄 Em Progresso" .context/DOCS/TASKS/[feature]-tasks.md
```

**Se resultado > 0:** feature tem tasks pendentes.

```
✅ TASK-X.Y.Z finalizada.
📋 Feature [feature]: [N] tasks restantes.
➡️  Próxima task: /prevec-execute-task [feature] TASK-[próxima]
```

**Se resultado = 0:** todas as tasks concluídas → ir para Passo 8.

### 9. Feature completa — PR

Atualizar status em `.context/DOCS/FEATURES/[feature].md`:
- `Status: 🟡 Em Planning` → `Status: ✅ Concluída`

Criar pull request usando dados agregados dos sessions arquivados:

```bash
gh pr create \
  --title "[tipo]([feature]): [nome da feature em uma linha]" \
  --body "$(cat <<'EOF'
## Resumo
[2-3 bullets do que foi implementado]

## Tasks concluídas
[lista das TASK-X.Y.Z com descrição de uma linha cada — do REVIEWER Log dos sessions]

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
📋 Session: arquivado em .context/.session/.archive/
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
📋 Sessions: todos arquivados em .context/.session/.archive/
➡️  Feature entregue. Próxima: /prevec-new-plan [nova ideia]
```

## Error Handling

- Session ausente: checar `.context/.session/.archive/` — pode ter sido arquivado antes. Se não achar, rodar prevec-execute-task novamente
- Session sem `Aprovado: sim`: recusar e direcionar para prevec-review-execution
- Commit falha (hook): corrigir o que o hook reportou, nunca usar --no-verify
- gh não instalado: orientar instalação e fornecer body do PR manualmente
- CHANGELOG/MEMORY ausentes mas configurados: criar arquivo antes de inserir
