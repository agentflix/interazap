# /context — Mostrar camadas de contexto

Mostra o mapa de camadas de contexto do projeto.

## Quando usar

- Dúvida sobre qual contexto carregar
- Novato no projeto
- Quer entender estrutura de documentação

## Saída

```
Layer 0: AGENTS.md (sempre)
Layer 1: project-brain.yaml (entender projeto)
Layer 2: modules.yaml (risco arquitetural)
Layer 3: FEATURES/ PRDS/ (escopo produto)
Layer 4: TASKS/ (execução task)
Layer 5: PREVC.md TACE.md (workflow)
Layer 6: MEMORY/ (decisões técnicas)
Layer 7: Source Code (implementação)
Layer 8: External Docs (APIs, frameworks)
```

## Resumo rápido

| Layer | Quando | Arquivo |
|-------|--------|---------|
| 0 | Sempre | AGENTS.md |
| 1 | Entender projeto | project-brain.yaml |
| 6 | Decisão técnica | MEMORY/ |
| 7 | Código | Source code |