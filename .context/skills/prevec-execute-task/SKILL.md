---
name: prevec-execute-task
description: >-
  Implementa uma task T.A.C.E específica no workflow PREVEC usando o BUILDER.
  Cria ou atualiza o session file da feature para compartilhar contexto com
  subagents. Triggers: "implementar task", "executar task",
  "prevec-execute-task", "TASK-X.Y.Z". Do NOT use sem task T.A.C.E definida ou
  para revisar código (use prevec-phase-close).
metadata:
  author: prevec
  version: '3.0.0'
---

# prevec-execute-task

Implementa uma task T.A.C.E auto-suficiente — sem rodar testes.
Testes rodam no `/prevec-phase-close` ao final de cada fase.

## Input

```
/prevec-execute-task [feature] [TASK-X.Y.Z]
```

Exemplo: `/prevec-execute-task importacao-csv TASK-3.1.1`

## Pré-condições

- Task existe em `.context/DOCS/TASKS/[feature]-tasks.md`
- Task está com status ⏳ Pendente
- Tasks dependentes anteriores estão ✅ Concluídas

## Processo

### 1. Verificar session da feature

```bash
ls .context/.session/[feature]-session.md 2>/dev/null
```

**Session existe:** ler o arquivo — Architecture Snapshot já está lá. Ir para Passo 3.
**Session não existe:** continuar para Passo 2.

### 2. Criar session da feature

```bash
mkdir -p .context/.session
```

Ler e serializar no session:
1. `.context/ARCHITECTURE/context-snapshot.md` — stack e regras invioláveis (lido UMA vez para toda a feature)

Se `context-snapshot.md` ausente: ler `project-brain.yaml` + `dependencies.yaml` diretamente e avisar para regenerar o snapshot.

Criar `.context/.session/[feature]-session.md` seguindo o template em `.context/.session/_TEMPLATE.md`.
Estrutura: Architecture Snapshot primeiro (Bloco 1) → Metadados (Bloco 2).

### 3. Verificação defensiva + paginação do session

**3a. Verificação de saúde:**
```bash
wc -c .context/.session/[feature]-session.md
```

SE tamanho > 15.000 bytes:
- **PARAR** — não iniciar a task
- Alertar: `⚠️ Session inflado ([N] bytes). Execute /session-archive [feature] antes de continuar.`
- Motivo: paginação automática pode ter falhado silenciosamente em phase-close anterior
- Não prosseguir mesmo que tasks_concluidas ≤ 2

**3b. Paginação automática (somente se tamanho ≤ 15.000 bytes):**

```bash
grep -c "Status: ✅ Concluída" .context/.session/[feature]-session.md
```

SE tasks_concluidas > 2:
1. Identificar as seções `## TASK-X.Y.Z` com `Status: ✅ Concluída` (mais antigas primeiro)
2. Para cada task além das 2 mais recentes concluídas:
   - Fazer append do conteúdo em `.context/.session/archive/[feature]-archive.md`
   - Substituir no session pelo resumo:
     ```
     ### [TASK-X.Y.Z] — ✅ [título] (commit: [hash])
     > [tipo(escopo): descrição] | Gates: api ✅ gateway ✅ app ✅
     ```

**3c. Append da nova seção:**

Adicionar ao final de `.context/.session/[feature]-session.md` a seção `## TASK-X.Y.Z`:

Ler a task completa de `.context/DOCS/TASKS/[feature]-tasks.md` (apenas a task específica).
Preencher a subseção **T.A.C.E** da seção.
Deixar **BUILDER Log** em branco — será preenchido no Passo 7.

Atualizar cabeçalho da task no session:
```
> Status: 🔄 Em Progresso | Fase PREVC: EXECUTION
```

**A task já contém Referência e Imports autorizados** — não ler arquivos de arquitetura adicionais.

### 4. Marcar task em progresso

Em `.context/DOCS/TASKS/[feature]-tasks.md`:
- `[ ] **TASK-X.Y.Z** ⏳` → `[ ] **TASK-X.Y.Z** 🔄`
- `**Status:** ⏳ Pendente` → `**Status:** 🔄 Em Progresso`

### 5. Determinar modo do BUILDER

| Tipo de task | Modo |
|---|---|
| Domain, Service, Controller, Event, API | BACKEND |
| Componente, Página, Service Angular/React | FRONTEND |
| Migration, Schema, Query, Índice | DBA |
| Integração cross-camada, contrato API↔frontend | DEV |
| Bug, comportamento incorreto | DEBUG |

**Se modo FRONTEND:** verificar se `.context/DESIGN/[feature]-*.md` existe.
Se não existir: parar e solicitar PLANNER (modo DESIGNER) antes de prosseguir.

### 6. Implementar

A task é auto-suficiente — implementar usando apenas o que está na seção da task no session.

Sequência obrigatória:
1. Ler a **Referência** da task — entender o padrão existente
2. Implementar em **A** seguindo o padrão da Referência
3. Respeitar **Imports autorizados** — nunca importar o que está na lista de proibidos
4. **T:** exatamente o descrito — nada mais, nada menos
5. **C:** garantir que DEPOIS corresponde ao descrito
6. **E:** verificar critérios listados — capturar output se necessário

Se surgir necessidade de pesquisar algo não previsto na task: parar, registrar no BUILDER Log como escopo não coberto, criar nova task para o resto.

### 7. Preencher BUILDER Log no session

Atualizar a subseção **BUILDER Log** na seção `## TASK-X.Y.Z` do session:

- Arquivos modificados com descrição de uma linha cada
- Decisões tomadas durante a implementação
- Notas para phase-close: edge cases, riscos, dívida técnica criada
- NÃO incluir resultado de testes — testes rodam no `/prevec-phase-close`

Atualizar cabeçalho da seção:
```
> Status: 🔄 Em Progresso | Fase PREVC: AGUARDANDO PHASE-CLOSE
```

### 8. Handoff

```
Task implementada. Session atualizado.
Session: .context/.session/[feature]-session.md (seção TASK-X.Y.Z)
```

## Output

```
✅ TASK-[X.Y.Z] implementada
📋 Arquivos modificados: [lista]
📋 Session: .context/.session/[feature]-session.md
➡️  Mesma fase? /prevec-execute-task [feature] TASK-[X.Y.próxima]
➡️  Última task da fase [X]? /prevec-phase-close [feature] [X]
```

## Error Handling

- Session corrompido: deletar e recriar do zero
- Task anterior não concluída: alertar dependência e não prosseguir
- Arquivo da seção A não existe: criar apenas se a task diz "criar" — nunca inferir
- Escopo além da task: parar, registrar no session (Notas BUILDER), criar nova task para o resto
- NÃO rodar testes ou gates aqui — responsabilidade do `/prevec-phase-close`
