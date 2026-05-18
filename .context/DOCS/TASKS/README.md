# Tasks

Decomposição T.A.C.E de features do InteraZap em tasks implementáveis.

## Convenções

- Um arquivo por feature: `[nome-kebab-case]-tasks.md`
- Template: `_TEMPLATE.md`
- Criado na fase **REVIEW** do PREVC via `/decompose [nome]`
- Atualizado durante EXECUTION e CONFIRM

## Numeração Hierárquica

```
TASK-X.Y.Z
├── X = Fase (1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação
```

## Status de Task

| Status | Significado |
|--------|-------------|
| ⏳ Pendente | Aguardando |
| 🔄 Em Progresso | Em desenvolvimento |
| ✅ Concluída | Passou VALIDATION |
| ❌ Reprovada | Falhou VALIDATION → volta para EXECUTION |

## Consultar

```bash
# Tasks pendentes de uma feature
grep -A2 "⏳ Pendente" .context/DOCS/TASKS/[feature]-tasks.md

# Todas as tasks em progresso
grep -r "Em Progresso" .context/DOCS/TASKS/

# Gate de qualidade de uma fase
grep -r "Gate de Qualidade" .context/DOCS/TASKS/[feature]-tasks.md
```
