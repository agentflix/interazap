---
name: prevec-review-execution
description: Revisa a implementação de uma task no workflow PREVEC usando code-review-confiavel com 7 subagents separados. Lê a seção da task no session file da feature — não re-lê arquivos de origem. Detecta alucinações, quebras de contrato, erros e regressões antes do CONFIRM. Obrigatório após todo prevec-execute-task. Triggers: "revisar task", "review da execução", "prevec-review-execution". Do NOT use para revisar feature docs ou tasks não implementadas (use PLANNER/REVIEWER diretamente).
metadata:
  author: prevec
  version: '2.1.0'
---

# prevec-review-execution

Valida a implementação de uma task com 7 revisores especializados antes do CONFIRM.
Lê a seção da task no session file da feature — zero re-leitura de arquivos de origem.

## Input

```
/prevec-review-execution [feature] [TASK-X.Y.Z]
```

Exemplo: `/prevec-review-execution importacao-csv TASK-3.1.1`

## Por que este passo é obrigatório

- Detecta alucinações: imports inexistentes, assinaturas erradas, dead code
- Verifica quebra de contrato entre camadas (API ↔ frontend, Domain ↔ Infrastructure)
- Garante grounding: código implementado bate com T.A.C.E da task
- Captura regressões antes do commit
- Reduz falsos positivos via meta-review

## Pré-condições

- Task está 🔄 Em Progresso
- Session file existe em `.context/.session/[feature]-session.md`
- Seção `## TASK-X.Y.Z` tem BUILDER Log preenchido e testes isolados passando

## Processo

### 1. CRÍTICO — Executar em subagent distinto

Este review DEVE ser feito em subagent separado para não contaminar o contexto da implementação.

### 2. Carregar contexto do session file

Ler `.context/.session/[feature]-session.md`.

Localizar a seção `## TASK-X.Y.Z` — contém tudo que precisa:
- **Architecture Snapshot** (no topo do arquivo): stack, regras invioláveis, dependências de módulo
- **T.A.C.E da task**: tarefa, arquivos, comportamento, evidências
- **BUILDER Log**: arquivos modificados, decisões, notas para o reviewer

**NÃO re-ler:** tasks.md, feature.md, project-brain.yaml, architecture.md, dependencies.yaml.
O session file já tem o contexto serializado.

### 3. Executar code-review-confiavel com 7 subagents

Ler `.context/skills/code-review-confiavel/SKILL.md` completamente.

Executar o fluxo da skill delegando para **7 subagents separados — um por revisor**, conforme `references/reviewers.md`:

1. **Especialização** — arquitetura e regras do workspace
2. **Grounding** — aderência a AGENTS.md, skills, T.A.C.E do session
3. **Second Pass** — releitura integral, omissões, bugs silenciosos
4. **Precision** — filtrar falsos positivos (confiança ≥ 80%)
5. **Human-in-the-Loop** — o que precisa de decisão humana
6. **Rastreabilidade** — diff bate com seção A e critérios E do session
7. **Meta-review** — revisar o review, remover achados sem prova

**Cada revisor = 1 subagent separado. Não executar inline. Não reduzir para menos de 7.**

Passar para cada subagent: Architecture Snapshot + T.A.C.E da task do session — não os arquivos originais.

### 4. Executar gates completos

Esta é a única fase onde os gates completos rodam — o BUILDER rodou apenas testes isolados.

Rodar todos os gates conforme `.context/WORKFLOW/validation-flow.md` para o workspace alterado:
- Lint completo
- Type check
- Suite de testes completa
- Build

### 5. Preencher REVIEWER Log no session

Atualizar a subseção **REVIEWER Log** na seção `## TASK-X.Y.Z` de `.context/.session/[feature]-session.md`:

- Resultado: aprovado sim/não
- Contagem de achados por severidade
- Achados bloqueantes com arquivo:linha (se houver)
- Dados prontos para CHANGELOG (tipo, escopo, descrição)
- Dados prontos para MEMORY (decisão/aprendizado, se houver)

### 6. Decisão

**Aprovado:** nenhum achado bloqueante → atualizar cabeçalho da seção para `Fase PREVC: CONFIRM` → prosseguir

**Reprovado:** achados bloqueantes → manter `Fase PREVC: EXECUTION` → retornar:
```
❌ Review reprovado — [N] bloqueantes:
1. [arquivo:linha] [severidade]: [problema]. [correção sugerida]
2. ...
➡️  Corrigir e rodar: /prevec-execute-task [feature] TASK-[X.Y.Z]
```

## Output (aprovado)

```
✅ Review aprovado — TASK-[X.Y.Z]
📋 7 subagents executados: Especialização | Grounding | Second Pass | Precision | Human | Rastreabilidade | Meta-review
📋 Gates: lint ✅ | types ✅ | tests ✅ | build ✅
📋 Achados: [N bloqueantes=0 | N médios | N baixos]
📋 Session: .context/.session/[feature]-session.md atualizado
➡️  Próximo: /prevec-finalize-execution [feature] TASK-[X.Y.Z]
```

## Error Handling

- Session ausente: rodar prevec-execute-task novamente para recriar
- Seção TASK-X.Y.Z não encontrada no session: task pode não ter sido iniciada — checar prevec-execute-task
- Subagents indisponíveis: fazer review local seguindo os 7 escopos e informar limitação explicitamente no output
- Gate não pode rodar: registrar motivo e risco residual no session — nunca ignorar silenciosamente
- Achado sem evidência: classificar como pergunta no session, não como bug
