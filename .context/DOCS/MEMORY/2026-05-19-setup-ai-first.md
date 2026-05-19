# Setup AI-First PREVEC V7

**Tipo:** Decisão
**Data:** 2026-05-19
**Autor:** PREVEC Bootstrap (Rafael Silva)
**Tags:** ai-first, prevec, setup, arquitetura, agents

## Situação

Projeto InteraZap precisava de estrutura AI-First para escalar o desenvolvimento com múltiplos agents (ORCHESTRATOR, PLANNER, BUILDER, REVIEWER) e workflow PREVC estruturado.
Setup anterior foi deletado e recriado do zero com PREVEC V7.

## Decisão / Aprendizado

Adotado PREVEC V7 como workflow de desenvolvimento:
- `.context/` como fonte única de agents, skills e documentação
- Symlinks em `.claude/`, `.codex/`, `.opencode/` apontando para `.context/`
- 4 agents consolidados (ORCHESTRATOR, PLANNER, BUILDER, REVIEWER)
- 7 skills PREVEC + code-review-confiavel + brainstorming

Stack detectada: Laravel 12 (api) + NestJS 11 (gateway) + Angular 20 (app).
Sem Capacitor (web only). CHANGELOG desativado. MEMORY ativo.

## Alternativas Consideradas

| Alternativa | Por que descartada |
|---|---|
| Manter arquivos separados por ferramenta | Divergência entre .claude/ e .codex/ — manutenção dupla |
| Usar CHANGELOG | Rafael optou por não usar nesta iteração |

## Consequências

- **Positivas:** Agentes compartilham a mesma fonte de contexto em qualquer ferramenta
- **Negativas / Trade-offs:** Symlinks podem ser ignorados por algumas ferramentas em Windows
- **Ação necessária:** Revisar AGENTS.md e agents após qualquer mudança arquitetural relevante
