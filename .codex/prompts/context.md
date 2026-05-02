# context — Mapa de camadas

## Objetivo

Mostrar camadas de contexto do projeto.

## Trigger

```
/context
```

## Layers

| Layer | Quando usar | Arquivo |
|-------|-------------|---------|
| 0 | Sempre | AGENTS.md |
| 1 | Entender projeto | project-brain.yaml |
| 2 | Risco arquitetural | modules.yaml |
| 3 | Escopo produto | FEATURES/, PRDS/ |
| 4 | Execução task | TASKS/ |
| 5 | Workflow formal | PREVC.md, TACE.md |
| 6 | Decisão técnica | MEMORY/ |
| 7 | Implementação | Source code |
| 8 | Conhecimento externo | Docs/MCPs |

## Regra

> Contexto não é carregado. Contexto é roteado.