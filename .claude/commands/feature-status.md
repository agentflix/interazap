# /feature-status — Status da Feature

Uso: `/feature-status [nome]`

## Passos

1. Ler `.context/DOCS/FEATURES/[nome].md` — status, complexidade, escopo
2. Ler `.context/DOCS/TASKS/[nome]-tasks.md` — contar tasks por status
3. Listar:
   - Tasks `⏳ Pendente`
   - Tasks `🔄 Em Progresso`
   - Tasks `✅ Concluída`
   - Tasks `❌ Reprovada`
4. Identificar próxima task (primeira ⏳ na ordem topológica)
5. Mostrar progresso por fase

## Output

```
Feature: [nome]
Status: [Em Planning / Em Review / Em Execução / Concluída]
Progresso geral: X/Y tasks concluídas (Z%)

Por fase:
  Fase 1 (Planning):    ✅ a/a
  Fase 2 (Design):      ✅ b/b
  Fase 3 (Backend):     🔄 c/d
  Fase 4 (Gateway):     ⏳ e/f
  Fase 5 (Frontend):    ⏳ g/h
  Fase 6 (Integration): ⏳ i/j

Próxima task: TASK-X.Y.Z
```
