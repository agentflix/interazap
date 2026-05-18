# Status da Feature (PREVC: Qualquer fase)

Uso: `/feature-status [nome]`

## Processo

1. Ler `.context/DOCS/FEATURES/[nome].md` — status geral
2. Ler `.context/DOCS/TASKS/[nome]-tasks.md` — contagem de tasks por status
3. Verificar CHANGELOG recente: `grep -r "[nome]" .context/DOCS/CHANGELOG/`

## Saída Esperada

```
Feature: [nome]
Status: [Planning / Review / Execution / Validation / Confirm / Concluída]
Complexidade: [P/M/G]
Bounded Context: [lista]

Tasks:
  Total: [N]
  ✅ Concluídas: [N]
  🔄 Em Progresso: [N]
  ⏳ Pendentes: [N]
  ❌ Reprovadas: [N]

Fase atual: [fase]
Próxima task: TASK-NNN [título]

Última entrada CHANGELOG: [data + tipo]
```
