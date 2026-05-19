# Decompor Feature em Tasks (PREVC: Review → Tasks)

Uso: `/decompose [nome]`

## Processo (@ARCHITECT)

1. Ler feature doc em `.context/DOCS/FEATURES/[nome].md`
2. Identificar workspaces afetados: api, gateway, app, electron, landing
3. Criar arquivo: `.context/DOCS/TASKS/[nome]-tasks.md` usando `_TEMPLATE.md`
4. Decompor usando T.A.C.E hierárquico:

### Estrutura de Tasks

```
TASK-X.Y.Z
X = Fase (1=Planning, 2=Design, 3=Backend/api, 4=Gateway, 5=Frontend/app, 6=Integration)
Y = Feature dentro da fase
Z = Etapa de codificação
```

5. Para cada task:
   - **T:** O que fazer (específico)
   - **A:** Arquivo exato com path completo
   - **C:** Comportamento — ANTES e DEPOIS
   - **E:** Evidências verificáveis (critérios de aceite mensuráveis)
6. Incluir revisão de fase com checkpoints @REVIEWER + @QA no final de cada fase

## Saída Esperada

```
Tasks criadas: .context/DOCS/TASKS/[nome]-tasks.md
Total de tasks: [N] em [N] fases
Próximo: /validate-tasks [nome]
```
