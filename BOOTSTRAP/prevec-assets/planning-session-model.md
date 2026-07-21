# PREVEC Planning Session — Modelo

> Criado por `prevec-new-plan`. Reusado por `prevec-decompose-plan` e `prevec-decompose-task`.
> Elimina re-leitura de arquitetura em cada skill da fase de planejamento.
> Arquivado por `prevec-decompose-task` ao gerar o arquivo de tasks.

---

## Como usar

1. `prevec-new-plan` cria `.context/.session/planning-[feature].md`
2. Copia `context-snapshot.md` para dentro do session (1 leitura de arquitetura)
3. `prevec-decompose-plan` lê o session — não relê architecture files — e preenche PRD Summary + Feature Draft
4. `prevec-decompose-task` lê o session — não relê architecture files — gera tasks e arquiva

---

## Template

```markdown
# Planning Session: [feature]

## Metadata
- Feature: [nome-kebab]
- Status: [BRAINSTORMING / PRD / FEATURE_DOC / TASKS / ARQUIVADO]
- Iniciada: YYYY-MM-DD HH:MM

---

## Architecture Snapshot
> Copiado de .context/ARCHITECTURE/context-snapshot.md na criação. Não atualizar aqui.

[colar conteúdo completo do context-snapshot.md]

---

## PRD
> Preenchido por prevec-new-plan ao criar o PRD.

- Path: .context/DOCS/PRDS/[NNNN]-PRD-[nome].md
- Objetivo: [3 linhas do PRD]
- Critérios de Aceite:
  - [ ] [critério 1]
  - [ ] [critério N]
- Complexidade estimada: [P / M / G]
- Fora de Escopo: [lista rápida]

---

## Feature Doc Draft
> Preenchido por prevec-decompose-plan ao criar a feature doc.

- Path: .context/DOCS/FEATURES/[nome].md
- Bounded Context: [módulo]
- Dependências de features: [lista ou "nenhuma"]
- Fases estimadas: [Fase 3: Backend | Fase 4: Frontend | etc.]
- Decisões tomadas durante planning:
  - [decisão 1 — motivo]
  - [decisão N]

---

## Arquivo
> Preenchido por prevec-decompose-task ao arquivar.

- Tasks criadas: .context/DOCS/TASKS/[feature]-tasks.md
- Total de tasks: [N]
- Arquivado: YYYY-MM-DD HH:MM
- Movido para: .context/.session/.archive/planning-[feature].md
```
