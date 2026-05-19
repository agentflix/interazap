---
name: prevec-execute-task
description: Implementa uma task T.A.C.E específica no workflow PREVEC usando o BUILDER. Cria o session file da task para compartilhar contexto com subagents subsequentes. Triggers: "implementar task", "executar task", "prevec-execute-task", "TASK-X.Y.Z". Do NOT use sem task T.A.C.E definida ou para revisar código (use prevec-review-execution).
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-execute-task

Implementa uma task T.A.C.E auto-suficiente — a task já contém Referência, Imports autorizados e comandos de Evidência.
Cria session file para passar contexto acumulado aos subagents seguintes.

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

### 1. Verificar session existente

```bash
ls .context/.session/[feature]-TASK-X.Y.Z.md 2>/dev/null
```

- **Session existe:** ler o session file — não re-ler arquivos de origem. Ir direto para Passo 3.
- **Session não existe:** continuar para Passo 2.

### 2. Criar session file

Criar `.context/.session/` se não existir:

```bash
mkdir -p .context/.session
```

Ler e serializar no session:

1. `.context/DOCS/TASKS/[feature]-tasks.md` — extrair a task específica completa (T, A, Referência, Imports, C, E)
2. `.context/ARCHITECTURE/context-snapshot.md` — stack e regras invioláveis (substitui project-brain + dependencies)

**A task já contém Referência e Imports autorizados** — não ler arquivos de arquitetura adicionais para derivar isso.

Se `context-snapshot.md` ausente: ler `project-brain.yaml` + `dependencies.yaml` diretamente e avisar para regenerar o snapshot.

Se modo FRONTEND: confirmar que `.context/DESIGN/[feature]-*.md` existe (task já tem link — só verificar).

Criar `.context/.session/[feature]-TASK-X.Y.Z.md` seguindo o template em `prevec-assets/session-model.md`.
Preencher completamente as seções **Metadata** e **Context Snapshot**.
Deixar **BUILDER Log** e **REVIEWER Log** em branco — serão preenchidos pelos passos seguintes.

### 3. Atualizar status

Marcar task como 🔄 Em Progresso no arquivo de tasks.
Atualizar campo `Fase PREVC: EXECUTION` no session file.

### 4. Determinar modo do BUILDER

| Tipo de task | Modo |
|---|---|
| Domain, Service, Controller, Event, API | BACKEND |
| Componente, Página, Service Angular/React | FRONTEND |
| Migration, Schema, Query, Índice | DBA |
| Integração cross-camada, contrato API↔frontend | DEV |
| Bug, comportamento incorreto | DEBUG |

**Se modo FRONTEND:** verificar se `.context/DESIGN/[feature]-*.md` existe.
Se não existir: parar e solicitar PLANNER (modo DESIGNER) antes de prosseguir.

### 5. Implementar

A task é auto-suficiente — implementar usando apenas o que está no session file (task + context-snapshot).

Sequência obrigatória:
1. Ler a **Referência** da task — entender o padrão existente
2. Implementar em **A** seguindo o padrão da Referência
3. Respeitar **Imports autorizados** — nunca importar o que está na lista de proibidos
4. **T:** exatamente o descrito — nada mais, nada menos
5. **C:** garantir que DEPOIS corresponde ao descrito
6. **E:** rodar os comandos exatos listados — capturar output

Se surgir necessidade de pesquisar algo não previsto na task: parar, registrar no BUILDER Log como escopo não coberto, criar nova task para o resto.

Não começar nova task enquanto esta não estiver completa.

### 6. Verificar gates locais

Rodar antes de passar para review:
- Lint: sem erros
- Type check: sem erros
- Testes da task: passando
- Build: sem quebra

Se qualquer gate falhar: corrigir antes de prosseguir.

### 7. Preencher BUILDER Log no session

Atualizar `.context/.session/[feature]-TASK-X.Y.Z.md` — seção **BUILDER Log**:

- Arquivos modificados com descrição de uma linha cada
- Decisões tomadas durante a implementação
- Resultado de cada gate (✅ / ❌)
- Notas para REVIEWER: edge cases, riscos, dívida técnica

Atualizar campo `Fase PREVC: VALIDATION` no session file.

### 8. Handoff

```
Task implementada. Session file atualizado.
Session: .context/.session/[feature]-TASK-X.Y.Z.md
Próximo passo: /prevec-review-execution [feature] TASK-[X.Y.Z]
```

## Output

```
✅ TASK-[X.Y.Z] implementada
📋 Arquivos modificados: [lista]
📋 Gates: lint ✅ | types ✅ | tests ✅ | build ✅
📋 Session: .context/.session/[feature]-TASK-X.Y.Z.md
➡️  Próximo: /prevec-review-execution [feature] TASK-[X.Y.Z]
```

## Error Handling

- Session corrompido: deletar e recriar do zero
- Task anterior não concluída: alertar dependência e não prosseguir
- Arquivo da seção A não existe: criar apenas se a task diz "criar" — nunca inferir
- Gate falhou: corrigir e re-rodar antes de handoff — nunca passar gate quebrado
- Escopo além da task: parar, registrar no session (Notas BUILDER), criar nova task para o resto
