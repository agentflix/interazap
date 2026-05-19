# Tasks — InteraZap

Tasks decompostas pelo PLANNER via `/prevec-decompose-task`.

## Convenção de nome

```
FEAT-NNN-[feature]-tasks.md
```

## Estrutura T.A.C.E

```
TASK-X.Y.Z
├── X = Fase (1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação
```

Cada task tem:
- **T** (Tarefa): o que fazer
- **A** (Arquivo): arquivos exatos — nunca "vários arquivos"
- **C** (Comportamento): antes → depois
- **E** (Evidência): critério verificável — nunca "funciona corretamente"

## Template

Ver `_TEMPLATE.md` nesta pasta.
