# 📝 Tasks

Tasks decompostas usando framework T.A.C.E.

## Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? |
| **C** | Comportamento | COMO funciona (antes→depois)? |
| **E** | Evidência | COMO SABER que está pronto? |

## Estrutura Hierárquica

```
TASK-X.Y.Z
├── X = Fase PREVC (3=Backend, 4=Frontend, 5=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação

Exemplo: TASK-3.2.1
├── 3 = Backend (Fase)
├── 2 = Domain (Feature)
└── 1 = Criar Entity (Etapa)
```

## Status de Task

| Status | Significado |
|--------|-------------|
| ⏳ Pendente | Aguardando implementação |
| 🔄 Em Progresso | Em desenvolvimento |
| ✅ Concluída | Passou validation |
| ❌ Reprovada | Falhou validation |

## Como usar

```bash
# Implementar task
/implement-task [feature] [TASK-NNN]

# Validar task
/validate [feature] [TASK-NNN]

# Confirmar task
/confirm-task [feature] [TASK-NNN]
```

## Template

Use `_TEMPLATE.md` como base para novas decomposições.
