---
name: workflow-prevc
description: Workflow oficial PREVC do InteraZap. Use when Codex precisar planejar, revisar, decompor, implementar, validar ou confirmar tasks/features seguindo PLANNING, REVIEW, EXECUTION, VALIDATION e CONFIRM, com gates obrigatórios, evidências, CHANGELOG, MEMORY e atualização de contexto do projeto. Do NOT use for regras específicas de Laravel, Angular ou NestJS quando a task não envolver o fluxo PREVC; nesses casos use a skill especialista do workspace.
metadata:
  author: Rafael Silva
  version: "1.0"
---

# Workflow PREVC

## Metadados

- Autor: Rafael Silva
- Versão: 1.0

## Uso

Use esta skill para conduzir qualquer feature, task, correção ou validação no InteraZap.

O fluxo obrigatório é:

```text
PLANNING -> REVIEW -> EXECUTION -> VALIDATION -> CONFIRM
```

## Regras Essenciais

- Não implemente sem entender a fase atual do PREVC.
- Antes de codificar, leia a task T.A.C.E ou a feature doc correspondente, quando existirem.
- Toda alteração de código deve ter teste no workspace afetado.
- Toda validação deve executar os gates do workspace alterado; se não for possível, registre o motivo.
- Tasks reprovadas voltam para EXECUTION com motivo registrado.
- A fase CONFIRM exige evidências, atualização de changelog/memória quando aplicável e fechamento explícito da task.
- Em features cross-workspace, a ordem padrão é `DBA -> BACKEND -> GATEWAY -> FRONTEND`.

## Exemplo de Uso

- Para implementar uma task: leia `references/prevc.md`, identifique a fase EXECUTION, carregue a skill especialista do workspace e valide com `references/validation-flow.md`.
- Para fechar uma task: use a fase CONFIRM, registre evidências, atualize contexto aplicável e só então considere a task concluída.

## Falhas e Reprovação

- Se um gate falhar, registre a falha e volte para EXECUTION.
- Se uma evidência não puder ser produzida, explique o motivo de forma explícita.
- Se a estrutura esperada em `.context/` não existir, use o caminho equivalente disponível e informe a divergência.

## Referências

Carregue apenas o arquivo necessário conforme a etapa:

- `references/prevc.md`: fases do PREVC, responsáveis, outputs, comandos, ordem cross-workspace e checklist de CONFIRM.
- `references/validation-flow.md`: gates por workspace, critérios gerais, critérios por tipo de mudança e fluxo de reprovação.
