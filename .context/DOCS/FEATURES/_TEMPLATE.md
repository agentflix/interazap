---
id: FEAT-NNN
title: [Nome da Feature]
status: [draft | review | approved | in_progress | completed]
prd: .context/DOCS/PRDS/NNNN-PRD-<topic>.md
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
---

# FEAT-NNN — [Nome da Feature]

## Descrição
[O que esta feature faz e por que existe]

## Escopo
**Inclui:**
- [o que está dentro do escopo]

**Exclui:**
- [o que não está no escopo desta feature]

## Módulos Afetados
- [ ] Backend (api/): [domínios — ex: Chat, CRM]
- [ ] Gateway (gateway/): [domínios — ex: webhooks, chat]
- [ ] Frontend (app/): [páginas — ex: chat, crm]
- [ ] Database: [migrations]

## Critérios de Aceite
- [ ] [critério verificável 1]
- [ ] [critério verificável 2]
- [ ] [critério verificável 3]

## Fases Estimadas
| Fase | Descrição | Tasks |
|---|---|---|
| 1 — Planning | Migrations, schemas | TASK-1.x.x |
| 2 — Design | Wireframes, specs UI | TASK-2.x.x |
| 3 — Backend | Domain, API | TASK-3.x.x |
| 4 — Gateway | NestJS, BullMQ | TASK-4.x.x |
| 5 — Frontend | Angular, Capacitor | TASK-5.x.x |
| 6 — Integration | E2E, contratos | TASK-6.x.x |

## Design
- Wireframes: `.context/DESIGN/[feature]-wireframe.md`
- UX Flow: `.context/DESIGN/[feature]-ux-flow.md`

## Dependências
- [features ou tasks que precisam estar prontas antes]

## Riscos
- [riscos identificados e mitigações]
