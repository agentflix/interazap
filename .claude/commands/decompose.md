# /decompose — Decompor Feature em Tasks T.A.C.E

Uso: `/decompose [nome]`

## Quem executa
- @ARCHITECT

## Pré-requisitos
- Feature doc aprovada por @REVIEWER

## Passos

1. Ler feature doc em `.context/DOCS/FEATURES/[nome].md`
2. Identificar fases necessárias (Planning, Design, Backend, Gateway, Frontend, Integration)
3. Para cada fase, listar features (Y) e etapas (Z)
4. Aplicar T.A.C.E em cada task (TASK-X.Y.Z)
5. Definir ordem topológica (DBA → BACKEND → GATEWAY → FRONTEND)
6. Adicionar checkpoint de revisão ao final de cada fase
7. Criar `.context/DOCS/TASKS/[nome]-tasks.md` a partir de `_TEMPLATE.md`
8. Encaminhar para `/validate-tasks [nome]`

## Skill referenciada
`.claude/skills/decompose-feature/decompose-feature.md`

## Output
Arquivo `.context/DOCS/TASKS/[nome]-tasks.md` com tasks hierárquicas T.A.C.E.
