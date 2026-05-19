---
name: code-review-confiavel
description: Code review revise mudanças de código, PRs, diffs, tasks implementadas ou entregas antes de mergear, abrindo 7 revisores especializados quando houver autorização para subagents, executando gates/testes do workspace alterado e reportando apenas achados com evidência. Do NOT use for implementação de feature, escrita de documentação sem diff ou revisão superficial sem acesso ao código alterado.
metadata:
    author: Rafael Silva
    version: '1.0'
---

# Code Review Confiável

## Metadados

- Autor: Rafael Silva
- Versão: 1.0

## Objetivo

Produzir reviews em que desenvolvedores confiam: poucos falsos positivos, evidência concreta, gates executados e achados acionáveis.

## Importante

- Mandatorio rodar em um subagent distinto a validacao para nao contaminar a validacao com o contexto do orchestrator

## Fluxo Obrigatório

1. Identifique o escopo alterado com `git status`, `git diff --stat` e diffs relevantes.
2. Carregue `workflow-prevc` e a skill especialista do workspace alterado: `laravel-especialista`, `nestjs-especialista` ou `angular-especialista`.
3. Abra **7 subagents** de revisão quando a invocação autorizar subagents. Use `references/reviewers.md` para escopos e prompts.
4. Rode os gates/testes do workspace alterado conforme `references/gates.md`.
5. Faça second pass local: releia o diff inteiro e liste explicitamente o que foi verificado e não gerou comentário.
6. Faça meta-review: descarte achados sem evidência, duplicados, especulativos ou sem impacto real.
7. Responda com achados por severidade, evidências, comandos executados e risco residual.

## Guardrails

- Precision > recall: se não houver pelo menos 80% de confiança, não reporte como bug.
- Nunca aprove nem rejeite PR automaticamente; o humano decide.
- Não reporte preferência de estilo se não houver regra do projeto, risco ou regressão.
- Não aceite hallucination: todo achado deve apontar arquivo/linha, diff, teste, contrato ou regra.
- Não esconda gate não executado; informe comando, motivo e risco residual.
- Não considere teste pulado como evidência.
- Não faça alterações no código durante review, salvo se o usuário pedir correção.

## Exemplo de Invocação (Example)

Use esta skill quando o pedido for parecido com: "use `code-review-confiavel` para revisar as mudanças atuais com 7 subagents e rodar os gates".

## Falhas e Erros (Error Handling)

- Se não houver diff, informe que não há entrega revisável.
- Se subagents não estiverem disponíveis, faça o review local seguindo os 7 escopos e informe a limitação.
- Se algum gate falhar, reporte a falha como achado e não continue tratando a entrega como validada.
- Se a revisão depender de PRD, Linear ou task ausente, registre como pergunta ou risco residual.

## Referências

Carregue apenas o necessário:

- `references/reviewers.md`: os 7 subagents, escopos e prompts.
- `references/gates.md`: comandos de teste e validação por workspace.
- `references/report-template.md`: formato final obrigatório do review.
