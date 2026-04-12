# Feature Status

Uso: `/feature-status [nome]`

## Processo

1. **Ler feature doc** em `.context/DOCS/FEATURES/`
2. **Ler tasks** em `.context/DOCS/TASKS/[feature]-tasks.md`
3. **Calcular progresso:**
   - Total de tasks
   - Tasks concluídas
   - Tasks em progresso
   - Tasks pendentes
4. **Verificar estado** de cada fase

## Output

```
📋 Feature: [nome]
ID: FEAT-NNN
Status: 🟡 Em Execução

Progresso: [X/N] tasks concluídas

FASES:
┌───────────┬────────┬──────────┐
│ Fase      │ Status │ Tasks    │
├───────────┼────────┼──────────┤
│ 3-Backend │ 🔄     │ 2/4      │
│ 4-Frontend│ ⏳     │ 0/2      │
│ 5-Intgrtn │ ⏳     │ 0/2      │
└───────────┴────────┴──────────┘

Próximas tasks:
- TASK-3.3.1: [Título] (🔄 Em progresso)
- TASK-3.4.1: [Título] (⏳ Pendente)
```
