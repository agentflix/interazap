# MEMORY — Decisões e Aprendizados de InteraZap

Registro persistente de decisões técnicas, aprendizados e armadilhas.
Consulte antes de tomar decisões que possam ter sido enfrentadas antes.

## Tipos

| Tipo | Quando usar |
|---|---|
| **Decisão** | Escolha arquitetural ou técnica com trade-offs |
| **Aprendizado** | Descoberta que muda como abordamos um problema |
| **Armadilha** | Erro ou caminho errado a evitar |
| **Insight** | Observação útil sem ação imediata |

## Convenção de nome

```
YYYY-MM-DD-titulo-kebab.md
```

Exemplos:
- `2026-05-19-setup-ai-first.md`
- `2026-05-20-pgvector-indexing-strategy.md`
- `2026-05-21-tenant-scope-bypass-armadilha.md`

## Quando criar

- Decisão técnica com alternativas descartadas
- Bug que demorou >1h para debugar
- Padrão novo adotado no projeto
- Comportamento inesperado do framework
- Trade-off aceito conscientemente

## Quando consultar

- Antes de decidir arquitetura de novo módulo
- Antes de implementar feature em domínio conhecido
- Quando encontrar comportamento estranho (pode já estar documentado)
