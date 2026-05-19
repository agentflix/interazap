# Tasks

Tasks decompostas em formato T.A.C.E por feature.

## Hierarquia

```
TASK-X.Y.Z
├── X = Fase (1=Planning, 2=Design, 3=Backend/Gateway, 4=Frontend, 5=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação
```

## Convenção de Nome

```
[feature-kebab-case]-tasks.md
```

Exemplos:
- `chat-externo-webchat-tasks.md`
- `scheduling-via-whatsapp-tasks.md`

## Template

Ver `_TEMPLATE.md` nesta pasta.

## T.A.C.E

| Campo | Obrigatório | Regra |
|---|---|---|
| **T** — Tarefa | Sim | Uma frase imperativa |
| **A** — Arquivo | Sim | Path exato — sem "vários arquivos" |
| **C** — Comportamento | Sim | Antes→depois verificável |
| **E** — Evidência | Sim | Comando executável — sem "funciona corretamente" |
