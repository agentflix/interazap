# Tasks — InteraZap

Tasks executáveis (T.A.C.E hierárquicas: TASK-X.Y.Z) decompostas a partir de feature docs.

## Convenções

- Um arquivo por feature: `[feature]-tasks.md`
- Template: `_TEMPLATE.md`
- IDs: `TASK-X.Y.Z` (Fase.Feature.Etapa)

## Fases

| X | Fase | Agent principal |
|---|------|-----------------|
| 1 | Planning | PM |
| 2 | Design | DESIGNER |
| 3 | Backend (api/) | BACKEND, DBA |
| 4 | Gateway (gateway/) | GATEWAY |
| 5 | Frontend (app/, electron/) | FRONTEND |
| 6 | Integration / E2E | DEV |

## Status de Task

- ⏳ Pendente
- 🔄 Em Progresso
- ✅ Concluída
- ❌ Reprovada

## Fluxo

1. ARCHITECT decompõe em tasks T.A.C.E
2. QA valida qualidade das tasks (`/validate-tasks`)
3. Agent executa cada task (`/implement-task`)
4. QA valida (`/validate`)
5. DOC + GIT_COMMIT fecham (`/confirm-task`)
6. Ao final de cada fase: `/review-phase` + `/validate-phase`
