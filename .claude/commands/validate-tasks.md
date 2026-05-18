# Validar Tasks (PREVC: Review)

Uso: `/validate-tasks [nome]`

## Processo (@QA + @REVIEWER)

Ler `.context/DOCS/TASKS/[nome]-tasks.md` e verificar cada task:

### Checklist T.A.C.E por Task

- [ ] **T:** Tarefa específica, não vaga ("criar X" não "melhorar Y")
- [ ] **A:** Path de arquivo exato e existente
- [ ] **C:** ANTES e DEPOIS com comportamento mensurável
- [ ] **E:** Evidências verificáveis (sem "funciona corretamente")

### Checklist de Estrutura

- [ ] Numeração hierárquica correta (TASK-X.Y.Z)
- [ ] Dependências entre tasks declaradas
- [ ] Ordem DBA → BACKEND → GATEWAY → FRONTEND respeitada (se cross-workspace)
- [ ] Checkpoints de revisão de fase presentes
- [ ] Gates de qualidade por workspace listados

## Saída Esperada

```
Validação: [APROVADA / REPROVADA — lista de ajustes]
Tasks prontas para EXECUTION: [S/N]
Próximo: /implement-task [nome] TASK-X.Y.Z
```
