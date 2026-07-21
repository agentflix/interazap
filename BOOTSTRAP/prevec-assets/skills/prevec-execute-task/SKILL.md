---
name: prevec-execute-task
description: Implementa uma task T.A.C.E específica no workflow PREVEC usando o BUILDER. Cria ou atualiza o session file da feature para compartilhar contexto com subagents. Triggers: "implementar task", "executar task", "prevec-execute-task", "TASK-X.Y.Z". Do NOT use sem task T.A.C.E definida ou para revisar código (use prevec-review-execution).
metadata:
  author: prevec
  version: '2.0.0'
---

# prevec-execute-task

Implementa uma task T.A.C.E auto-suficiente — a task já contém Referência, Imports autorizados e comandos de Evidência.
Usa um único session file por feature para acumular contexto entre agents.

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

Criar apenas quando houver handoff para outro agent (BUILDER → REVIEWER é sempre o caso no PREVEC).

```bash
mkdir -p .context/.session
```

Ler e serializar no session:
1. `.context/ARCHITECTURE/context-snapshot.md` — stack e regras invioláveis (lido UMA vez para toda a feature)

Se `context-snapshot.md` ausente: ler `project-brain.yaml` + `dependencies.yaml` diretamente e avisar para regenerar o snapshot.

Criar `.context/.session/[feature]-session.md` seguindo o template em `prevec-assets/session-model.md`.
Preencher seções **Metadados** e **Architecture Snapshot**.

### 3. Append seção da task no session

Adicionar ao final de `.context/.session/[feature]-session.md` a seção `## TASK-X.Y.Z`:

Ler a task completa de `.context/DOCS/TASKS/[feature]-tasks.md` (apenas a task específica).
Preencher a subseção **T.A.C.E** da seção.
Deixar **BUILDER Log** e **REVIEWER Log** em branco — serão preenchidos nos passos seguintes.

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
6. **E:** rodar os comandos exatos listados — capturar output

Se surgir necessidade de pesquisar algo não previsto na task: parar, registrar no BUILDER Log como escopo não coberto, criar nova task para o resto.

### 7. Rodar testes isolados

Rodar apenas os testes dos arquivos modificados nesta task — não a suite completa.

```bash
# Exemplos — adaptar para a stack real:
# PHP:  php artisan test --filter NomeDaClasseTest
# Jest: npx jest path/do/arquivo.test.ts
# Pytest: pytest tests/path/to/test_file.py
```

Gates completos (lint, build, suite inteira) são responsabilidade do REVIEWER — não rodar aqui.

Se os testes isolados falharem: corrigir antes de prosseguir.

### 8. Preencher BUILDER Log no session

Atualizar a subseção **BUILDER Log** na seção `## TASK-X.Y.Z` do session:

- Arquivos modificados com descrição de uma linha cada
- Decisões tomadas durante a implementação
- Testes isolados: comando usado + resultado
- Notas para REVIEWER: edge cases, riscos, dívida técnica

Atualizar cabeçalho da seção:
```
> Status: 🔄 Em Progresso | Fase PREVC: VALIDATION
```

### 9. Handoff

```
Task implementada. Session atualizado.
Session: .context/.session/[feature]-session.md (seção TASK-X.Y.Z)
Próximo passo: /prevec-review-execution [feature] TASK-[X.Y.Z]
```

## Output

```
✅ TASK-[X.Y.Z] implementada
📋 Arquivos modificados: [lista]
📋 Gates: lint ✅ | types ✅ | tests ✅ | build ✅
📋 Session: .context/.session/[feature]-session.md
➡️  Próximo: /prevec-review-execution [feature] TASK-[X.Y.Z]
```

## Error Handling

- Session corrompido: deletar e recriar do zero
- Task anterior não concluída: alertar dependência e não prosseguir
- Arquivo da seção A não existe: criar apenas se a task diz "criar" — nunca inferir
- Gate falhou: corrigir e re-rodar antes de handoff — nunca passar gate quebrado
- Escopo além da task: parar, registrar no session (Notas BUILDER), criar nova task para o resto
