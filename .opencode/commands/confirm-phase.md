# Confirmar Fase (PREVC: Confirm de Fase)

Uso: `/confirm-phase [fase]`

Exemplo: `/confirm-phase 3` (fecha Fase 3 — Backend)

## Pré-condição

- `/review-phase [fase]` ✅ APROVADO
- `/validate-phase [fase]` ✅ PASSOU

## Processo (@DOC + @PM)

1. Confirmar todas as tasks TASK-[fase].*.* individualmente (via `/confirm-task`)
2. Gerar entrada de resumo de fase no CHANGELOG:

```text
- [HH:MM] CHORE [[fase]-fase]: Fase [N] ([nome]) concluída
  - Tasks concluídas: TASK-X.1.* a TASK-X.N.*
  - Workspaces afetados: [lista]
  - Refs: [FEAT/FIX-NNN]
```

3. Registrar em MEMORY se houve decisões ou aprendizados da fase
4. Atualizar `project-state.yaml`
5. Criar commit semântico de fechamento de fase

## Saída Esperada

```
Fase [N] confirmada:
  Tasks fechadas: [N]
  CHANGELOG: entrada de fase adicionada
  MEMORY: [N] registros
  Commit: [mensagem]
  Próxima fase: [N+1]
```
