# PLAN-022-migrate-angular-imports — Migrar imports do Angular para path aliases

## Objetivo

Migrar todos os imports relativos do Angular para usar os novos path aliases configurados no PLAN-021. Substituir paths como `../../../core/services/x` por `@core/services/x`, `../../crm/services/x` por `@crm/services/x`, etc.

## Módulo relacionado

Frontend (Angular)

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Migrar todos os imports relativos para usar os aliases configurados
- Substituir `../../../core/*` → `@core/*`
- Substituir `../../../shared/*` → `@shared/*`
- Substituir `../../../layout/*` → `@layout/*`
- Substituir `../../crm/*` → `@crm/*`, `../../chat/*` → `@chat/*`, etc. (cross-page)
- Substituir `../../ai/*` → `@ai/*`, `../../billing/*` → `@billing/*`, etc.

### Excluído

- Novos aliases (já configurados no PLAN-021)
- Backend ou Gateway

## Arquivos a modificar

### Layout (@layout/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/auth/create-password/create-password.ts` | `'../../../layout/auth-layout/auth-page-wrapper.component'` | `'@layout/auth-layout/auth-page-wrapper.component'` |
| `pages/auth/login/login.ts` | `'../../../layout/auth-layout/auth-page-wrapper.component'` | `'@layout/auth-layout/auth-page-wrapper.component'` |
| `pages/auth/reset-password/reset-password.ts` | `'../../../layout/auth-layout/auth-page-wrapper.component'` | `'@layout/auth-layout/auth-page-wrapper.component'` |

### Chat (@chat/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/chat/store/*.ts` | `'../../../core/services/chat.service'` | `'@core/services/chat.service'` |
| `pages/chat/components/*/*.ts` | `'../../core/services/chat.service'` | `'@core/services/chat.service'` |
| `pages/chat/campaigns/*.ts` | `'../../../core/services/chat-campaign.service'` | `'@core/services/chat-campaign.service'` |
| `pages/chat/chatbot/*.ts` | `'../../core/services/chat.service'` | `'@core/services/chat.service'` |

### CRM (@crm/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/crm/contacts/*.ts` | `'../../../core/services/crm-contact.service'` | `'@core/services/crm-contact.service'` |
| `pages/crm/negotiations/*.ts` | `'../../../core/services/crm-negotiation.service'` | `'@core/services/crm-negotiation.service'` |
| `pages/crm/negotiation-show/*.ts` | `'../../../core/services/crm-negotiation.service'` | `'@core/services/crm-negotiation.service'` |
| `pages/crm/contacts/components/*` | `'../../../../core/services/*'` | `'@core/services/*'` |
| `pages/crm/negotiations/components/*` | `'../../../../core/services/*'` | `'@core/services/*'` |

### AI (@ai/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/ai/pages/prompts/prompt-editor/*.ts` | `'../../../services/ai-prompt.service'` | `'@ai/services/ai-prompt.service'` |
| `pages/ai/pages/prompts/*` | `'../../../core/services/ai-governance.service'` | `'@core/services/ai-governance.service'` |

### Billing (@billing/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/billing/invoices/components/*` | `'../../../../../core/services/toast.service'` | `'@core/services/toast.service'` |
| `pages/billing/invoices/components/*` | `'../../../../core/services/billing-invoice.service'` | `'@core/services/billing-invoice.service'` |

### Platform (@platform/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/platform/ai-governance/prompt-masters/components/*` | `'../../../../../../core/services/ai-governance.service'` | `'@core/services/ai-governance.service'` |
| `pages/platform/plans/*` | `'../../../../core/services/platform-plan.service'` | `'@core/services/platform-plan.service'` |

### Dashboard (@dashboard/*)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/dashboard/*` | `'../../core/services/dashboard.service'` | `'@core/services/dashboard.service'` |

### Cross-page imports (@pages/*, @crm/*, @chat/*, etc.)

| Arquivo | Old Import | New Import |
|---------|------------|------------|
| `pages/public/proposal-view/proposal-view.ts` | `'../../crm/services/crm-proposal.service'` | `'@crm/services/crm-proposal.service'` |
| `pages/chat/campaigns/*` | `'../../crm/companies/services/*'` | `'@crm/companies/services/*'` |

## Tasks derivadas (execução paralela por domínio)

| Task | Descrição | Agente | Domínio |
|------|-----------|--------|---------|
| TASKS-022a | Migrar @layout/*, @auth/* | @FRONTEND | Auth/Layout |
| TASKS-022b | Migrar @chat/* | @FRONTEND | Chat |
| TASKS-022c | Migrar @crm/* | @FRONTEND | CRM |
| TASKS-022d | Migrar @ai/* | @FRONTEND | AI |
| TASKS-022e | Migrar @billing/* | @FRONTEND | Billing |
| TASKS-022f | Migrar @platform/* | @FRONTEND | Platform |
| TASKS-022g | Migrar @dashboard/*, @reports/*, @admin/*, @public/*, @settings/* | @FRONTEND | Outros |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Import errado após substituição | Alta | Alta | Executar build e lint após cada domínio |
| Alias ainda não existe | Baixa | Alta | Verificar tsconfig.json antes |
| Conflito de nomes de arquivo | Baixa | Média | TypeScript vai报告错误如果冲突 |

### Dependências

- PLAN-021 (path aliases configurados) - ✅ COMPLETO

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Média |
| Camadas afetadas | Frontend |
| Arquivos para migrar | ~250 |
| Aliases usados | 14 |

## Validação e Gates

- [ ] `pnpm run build` executa com sucesso
- [ ] `pnpm run lint` executa com sucesso (0 errors)
- [ ] Nenhum import relativo para core/shared/layout/pages/* остался
