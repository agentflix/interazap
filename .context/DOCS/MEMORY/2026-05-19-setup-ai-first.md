---
tipo: Decisão
data: 2026-05-19
autor: Rafael Silva
contexto: Bootstrap AI-First PREVEC V7
tags: [setup, ai-first, prevc, workflow]
---

# Setup AI-First com PREVEC V7

## Situação
Projeto InteraZap existente sem documentação AI-First estruturada.
Necessidade de workflow consistente para planejamento e execução de features.

## Decisão / Aprendizado
Adotar PREVEC V7 com 4 agents consolidados (ORCHESTRATOR, PLANNER, BUILDER, REVIEWER)
e workflow completo: new-plan → decompose-plan → decompose-task → execute-task → review-execution → finalize-execution.

Ferramentas ativas: .claude/ + .opencode/ + .codex/ (todas apontando para .context/ via symlinks).
MEMORY ativo. CHANGELOG desativado.

## Alternativas Consideradas
- **Agents individuais por especialidade** — descartada: muita fragmentação de contexto
- **Sem workflow estruturado** — descartada: inconsistência na qualidade de implementação

## Consequências
- **Positivas:** contexto centralizado em .context/, skills compartilhadas entre ferramentas, workflow rastreável
- **Negativas / Trade-offs:** overhead de documentação por feature — compensado pela redução de erros

## Referências
- Bootstrap: `builder/prompt-prevec-cli.md`
- Agents: `.context/agents/`
- Skills: `.context/skills/`
