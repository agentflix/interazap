# /confirm-phase — Confirm de Fase (PREVC: Confirm)

Uso: `/confirm-phase [N]`

## Quem executa
- @DOC

## Passos

1. Verificar todas as tasks da Fase N como `✅ Concluída`
2. Adicionar resumo da fase no CHANGELOG do dia
3. Atualizar `.context/ARCHITECTURE/project-state.yaml` (módulos afetados)
4. Marcar checkpoint de fase como confirmado

## Output
Fase N marcada como concluída em todas as tasks + CHANGELOG.
