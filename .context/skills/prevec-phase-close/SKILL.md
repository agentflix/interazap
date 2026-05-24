---
name: prevec-phase-close
description: >-
  Fecha uma fase de execução: roda testes da fase, commita todas as tasks com
  1 commit por fase, e na última fase executa review com 7 subagents + gates
  finais + Builder fix loop + PR. Substitui /prevec-review-execution e
  /prevec-finalize-execution no fluxo normal.
  Triggers: "fechar fase", "phase-close", "finalizar fase", "terminar fase",
  "última task da fase". Do NOT use sem ter executado todas as tasks da fase
  com /prevec-execute-task.
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-phase-close

Fecha uma fase do workflow PREVC: testa, commita e (na última fase) revisa + entrega.

## Input

```
/prevec-phase-close [feature] [fase]
```

Exemplos:
- `/prevec-phase-close importacao-csv 3`  → fecha Fase 3 (Backend)
- `/prevec-phase-close importacao-csv 5`  → fecha Fase 5 (Integration) — última fase

## Pré-condições

- Todas as tasks `TASK-[fase].Y.Z` estão com status `🔄 Em Progresso`
- Session file existe em `.context/.session/[feature]-session.md`
- Cada task tem BUILDER Log preenchido na seção correspondente do session

## Processo

### 1. Identificar tasks da fase

Ler `.context/.session/[feature]-session.md` e `.context/DOCS/TASKS/[feature]-tasks.md`.

Coletar todas as `TASK-[fase].Y.Z` com status `🔄 Em Progresso`.

Verificar que nenhuma está `⏳ Pendente` — se houver, alertar:
```
⚠️ Task TASK-[fase].Y.Z ainda pendente. Implementar antes de fechar a fase.
```

### 2. Detectar workspace da fase

Analisar a seção A das tasks coletadas para identificar o workspace:

| Workspace detectado | Comando de teste da fase |
|---|---|
| Somente `api/` | `cd api && composer gate:fast` |
| Somente `app/` | `pnpm --filter app test && pnpm --filter app build` |
| Somente `gateway/` | `pnpm --filter gateway test && pnpm --filter gateway build` |
| Múltiplos workspaces | Rodar comandos de cada workspace detectado |
| Somente `.context/` (design/docs) | Nenhum gate — avançar direto para Passo 4 |

### 3. Rodar testes da fase

Rodar apenas os gates do workspace desta fase — não a suite completa de outros workspaces.

```bash
# Exemplo Backend (Fase 3):
cd api && composer gate:fast

# Exemplo Frontend (Fase 4):
pnpm --filter app test && pnpm --filter app build

# Exemplo Integration (Fase 5):
cd api && composer gate:fast
pnpm --filter gateway test && pnpm --filter gateway build
pnpm --filter app test && pnpm --filter app build
```

**SE testes falharem:**

```
❌ Fase [N] — testes falhando:
[output dos testes]

Corrigir antes de continuar:
1. Peça ao BUILDER para corrigir: "Corrija os erros abaixo na Fase [N]"
2. Após correção, rodar novamente: /prevec-phase-close [feature] [fase]
```

**PARAR** — não commitar, não avançar.

**SE testes passarem:** continuar para Passo 4.

### 4. Confirmar tasks da fase (MEMORY + state)

Para cada task da fase:
1. Marcar como ✅ em `.context/DOCS/TASKS/[feature]-tasks.md`:
   - `[ ] **TASK-[fase].Y.Z** 🔄` → `[x] **TASK-[fase].Y.Z** ✅`
   - `**Status:** 🔄 Em Progresso` → `**Status:** ✅ Concluída`
2. Atualizar cabeçalho no session:
   ```
   > Status: ✅ Concluída | Fase PREVC: CONFIRM
   ```
3. Verificar se BUILDER Log tem "Notas para phase-close" com decisão técnica:
   - Se sim → criar `.context/DOCS/MEMORY/[DATA]-[titulo].md`

Atualizar `.context/ARCHITECTURE/project-state.yaml`:
- Incrementar `tasks_completed` pelo número de tasks da fase
- Zerar `tasks_in_progress`
- Atualizar `last_validation`

### 5. Commit da fase (1 commit por fase)

Coletar todos os arquivos modificados nas tasks da fase (seção A de cada BUILDER Log).

```bash
git add [arquivo1] [arquivo2] ... [arquivoN]
```

Determinar tipo do commit:
- Se qualquer task é `feat` → tipo `feat`
- Se todas são `fix` → tipo `fix`
- Se mix de outros → tipo mais relevante

Commit semântico agregado:
```bash
git commit -m "$(cat <<'EOF'
tipo(feature/fase-N): descrição da fase em português

Tasks: TASK-[fase].1.1, TASK-[fase].1.2, TASK-[fase].2.1
Arquivos principais: [lista resumida]

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

### 6. Verificar se é a última fase

```bash
grep -c "⏳ Pendente\|🔄 Em Progresso" .context/DOCS/TASKS/[feature]-tasks.md
```

**SE resultado > 0** (há tasks pendentes de outras fases): fase intermediária.

```
✅ Fase [N] concluída
📋 [M] tasks commitadas | Commit: [hash]
📋 Testes: [workspace] ✅
➡️  Próxima: /prevec-execute-task [feature] TASK-[N+1].1.1
```

**ENCERRAR** — não executar passos 7 e 8.

**SE resultado = 0** (última fase): continuar para Passo 7.

### 7. Review final com 7 subagents (somente última fase)

Executar em **subagent distinto** — não contaminar contexto do phase-close.

Calcular diff total da feature:
```bash
git diff main...HEAD --stat | tail -1
# Extrair DIFF_SIZE total (inserções + deleções)
```

Aplicar tiered review baseado em DIFF_SIZE:
- `< 50` → Tier 1: 3 reviewers (Especialização + Second Pass + Precision)
- `50-200` → Tier 2: 5 reviewers
- `> 200` → Tier 3: 7 reviewers (completo)

Carregar `code-review-confiavel` e abrir N subagents conforme tier.

Cada subagent recebe: session file completo (todas as seções de tasks da feature).

**SE review detectar bloqueantes:** registrar no session e continuar para Passo 7 gates — o gate vai detectar e Builder vai corrigir.

### 8. Gates finais (somente última fase)

```bash
# Suite completa — não gate:fast
cd api && composer gate:all
pnpm --filter gateway build && pnpm --filter gateway test
pnpm --filter app build && pnpm --filter app test
```

**SE gates falharem** (máx. 2 tentativas):

```
❌ Gates falhando:
[output]
→ Delegando para BUILDER corrigir...
```

Delegar para BUILDER:
- Causa óbvia (erro de tipos, import faltando) → `builder-write`
- Causa não-óbvia (teste falhando inesperadamente) → `builder-debug`

Após correção do BUILDER: re-rodar gates.
Se ainda falhar na 2ª tentativa: reportar ao usuário e parar.

**SE gates passarem:** continuar para Passo 9.

### 9. Fechar feature e criar PR (somente última fase)

Marcar feature como ✅ em `.context/DOCS/FEATURES/[feature].md`.

Coletar dados de todos os REVIEWER Logs / BUILDER Logs do session para o PR body.

Deletar session file:
```bash
rm .context/.session/[feature]-session.md
```

Criar PR:
```bash
gh pr create \
  --title "feat([feature]): [nome da feature]" \
  --body "$(cat <<'EOF'
## Resumo
[2-3 bullets do que foi implementado — dos BUILDER Logs]

## Fases implementadas
- Fase [N]: [descrição] — Tasks: TASK-X.1.1, ...
- Fase [N+1]: [descrição] — Tasks: TASK-Y.1.1, ...

## Review
- Tier [N]: [M] revisores executados
- Achados bloqueantes: nenhum
- Gates: api ✅ | gateway ✅ | app ✅

🤖 Gerado com PREVEC
EOF
)"
```

## Output (fase intermediária)

```
✅ Fase [N] — [feature] concluída
📋 Tasks: TASK-[N].1.1, TASK-[N].1.2 ✅
📋 Testes: [workspace] ✅
📋 Commit: [hash] — tipo(feature/fase-N): [descrição]
➡️  Próxima: /prevec-execute-task [feature] TASK-[N+1].1.1
```

## Output (última fase)

```
✅ Feature [feature] — entregue
📋 Review: Tier [N] — [M] reviewers | 0 bloqueantes
📋 Gates: api ✅ | gateway ✅ | app ✅
📋 PR: [URL]
📋 Session: deletado
➡️  Feature completa. Próxima: /prevec-new-plan [nova ideia]
```

## Error Handling

- Tasks pendentes na fase → alertar e não fechar (implementar antes)
- Testes falham → reportar output completo, pedir correção ao BUILDER, re-rodar
- Gates falham 2x → reportar ao usuário, não forçar PR
- `gh` não instalado → fornecer body do PR manualmente e orientar instalação
- Session ausente → checar se tasks estão ✅ em tasks.md; se sim, fase já foi fechada
