# PLAN-005 — Recriar Planos de Assinatura com Filtro RBAC

## Objetivo

Recriar os 3 planos de assinatura (Starter, Professional, Business) com nomes, limites e preços alinhados ao mercado, e implementar filtro de relatórios via permissões RBAC conforme mapeamento definido em brainstorm.

## Módulo relacionado

Platform | Billing | Reports

## PRD relacionado

N/A (feature独立)

## Escopo

### Incluído

- Substituir TODOS os 6 planos antigos por 3 novos: Starter, Professional, Business
- Tenant padrão Super Admin InteraZap deve ficar no plano Business
- Adição de campo `reports_mode` na tabela `platform_plans` (BASIC, ADVANCED, FULL)
- Atualização do `PlatformPlanEnforcementService` com método `canViewReport()`
- Atualização do `ReportsPolicy` para consultar modo do plano via `PlatformPlanEnforcementService`
- Atualização do `RolePermissionSeeder` com permissões `reports.*` por plano
- Mapeamento de permissões de relatórios:

| Plano                  | Relatórios                                                                                                   |
| ---------------------- | ------------------------------------------------------------------------------------------------------------ |
| Starter                | `reports.chat.volume`                                                                                        |
| Professional           | `reports.chat.*` (exceto admin), `reports.crm.*`, `reports.ai.autopilot_performance`, `reports.ai.sentiment` |
| Business               | `reports.*` (todos) + `reports.export`                                                                       |
| Admin (qualquer plano) | `reports.ai.usage_cost`, `reports.billing.revenue`                                                           |

- Admin override: `isAdmin = user->hasRole('admin')` (Spatie) — libera `reports.ai.usage_cost` e `reports.billing.revenue` independente do plano

- Suporte via WhatsApp em todos os planos (configuração no plano, não é um filtro)

### Excluído

- Alteração de permissões de chatbot vs AI — já definido anteriormente
- Criação de nova UI de planos (apenas backend/seeder)
- DB será recriado do zero — não há tenants existentes para migrar

## Etapas propostas

1. **Backend — Migrar schema `platform_plans`**: adicionar coluna `reports_mode` (enum: BASIC, ADVANCED, FULL)
2. **Backend — Atualizar `PlatformPlanSeeder`**: recriar planos Starter, Professional, Business com novos nomes, preços e limites
3. **Backend — Criar enum `PlatformReportsMode`**: com valores BASIC, ADVANCED, FULL
4. **Backend — Atualizar `PlatformPlanEnforcementService`**: adicionar método `canViewReport(tenant, permission)` e `getReportsMode(tenant)`
5. **Backend — Atualizar `ReportsPolicy`**: integrar com `PlatformPlanEnforcementService` para filtrar por modo do plano + admin override
6. **Backend — Atualizar `RolePermissionSeeder`**: mapear permissões `reports.*` por plano conforme tabela
7. **Backend — Verificar seeders**: rodar seeders e validar dados

## Tasks derivadas

| Task     | Descrição                                                  | Agente  | Status |
| -------- | ---------------------------------------------------------- | ------- | ------ |
| TASK-005 | Migrar schema com coluna reports_mode                      | DBA     | todo   |
| TASK-006 | Atualizar PlatformPlanSeeder com 3 planos                  | BACKEND | todo   |
| TASK-007 | Criar enum PlatformReportsMode                             | BACKEND | todo   |
| TASK-008 | Atualizar PlatformPlanEnforcementService com canViewReport | BACKEND | todo   |
| TASK-009 | Atualizar ReportsPolicy com filtro RBAC por plano          | BACKEND | todo   |
| TASK-010 | Atualizar RolePermissionSeeder com permissões reports      | BACKEND | todo   |
| TASK-011 | Validar seeders e gates                                    | QA      | todo   |

## Riscos e dependências

### Riscos

| Risco                                    | Probabilidade | Impacto | Mitigação                                         |
| ---------------------------------------- | ------------- | ------- | ------------------------------------------------- |
| Breaking change em permissões existentes | Baixa         | Médio   | Verificar role_permission_seeder antes de aplicar |

### Dependências

- DB será recriado do zero — criar apenas os 3 novos planos

## Estimativa

| Item                          | Valor                            |
| ----------------------------- | -------------------------------- |
| Complexidade                  | Média                            |
| Camadas afetadas              | Backend                          |
| Migrações necessárias         | Sim (1 migração)                 |
| Impacto em módulos existentes | Sim (Platform, Billing, Reports) |
