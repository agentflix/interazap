# TASKS — Lista de Tarefas InteraZap

---

# TASK-005 — Migrar Schema reports_mode

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @DBA

## Goal

Criar migração para adicionar coluna `reports_mode` (ENUM BASIC, ADVANCED, FULL) na tabela `platform_plans`.

## Constraints

- Seguir convenção de migrations do projeto
- Adicionar sem quebrar dados existentes

## Context

- Módulos afetados: Platform
- Dependências: Nenhuma
- Referências: `api/database/migrations/2026_01_01_000000_create_platform_tables.php`

## Etapas

- [ ] Criar migração `add_reports_mode_to_platform_plans`
- [ ] Adicionar coluna `reports_mode` com valores 'BASIC', 'ADVANCED', 'FULL'
- [ ] Definir DEFAULT como 'BASIC'
- [ ] Criar índice para performance
- [ ] Verificar migração rollback

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(billing): add reports_mode column to platform_plans`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-006 — Atualizar PlatformPlanSeeder

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @BACKEND

## Goal

Substituir TODOS os 6 planos antigos por 3 novos: Starter (R$97), Professional (R$297), Business (R$897). Desativar todos os planos existentes (Medium, Master, Enterprise do PlatformPlanSeeder + Starter antigo, Growth, Pro do PlatformPlanExtraSeeder) e criar apenas os 3 novos com a estrutura definida em brainstorm.

## Constraints

- Seguir DDD: readonly DTOs, explicit $fillable
- UUID primary keys, sem auto-increment
- DB novo — apenas criar os 3 novos planos

## Context

- Módulos afetados: Platform, Billing
- Dependências: TASK-005 (migração), TASK-007 (enum)
- Referências: `api/database/seeders/PlatformPlanSeeder.php`, `api/database/seeders/PlatformPlanExtraSeeder.php`
- DB será recriado do zero — criar apenas os 3 novos planos

## Etapas

- [ ] Criar plano Starter: R$97, 5 users, 1 WhatsApp, 1GB, 50 negotiations, chatbot, BASIC
- [ ] Criar plano Professional: R$297, 20 users, 5 WhatsApps, 5GB, 500 negotiations, AI, ADVANCED
- [ ] Criar plano Business: R$897, 100 users, 25 WhatsApps, 10GB, ilimitado, AI, FULL
- [ ] Garantir que tenant padrão (AGENTFLX / Super Admin InteraZap) fique no plano Business (upsert invoice)
- [ ] Escrever testes

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(billing): recreate subscription plans with new tiers`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-007 — Criar Enum PlatformReportsMode

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @BACKEND

## Goal

Criar enum `PlatformReportsMode` com valores BASIC, ADVANCED, FULL para tipagem forte no campo `reports_mode`.

## Constraints

- PHP 8.3: backed enum com cases

## Context

- Módulos afetados: Platform
- Dependências: TASK-005
- Referências: `api/src/Domain/Platform/Enums/`

## Etapas

- [ ] Criar arquivo `api/src/Domain/Platform/Enums/PlatformReportsMode.php`
- [ ] Definir cases: BASIC, ADVANCED, FULL
- [ ] Criar cast no PlatformPlan model
- [ ] Escrever testes unitários

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(platform): add PlatformReportsMode enum`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-008 — Atualizar PlatformPlanEnforcementService

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @BACKEND

## Goal

Adicionar métodos `canViewReport()`, `getReportsMode()` e `isAdmin()` no PlatformPlanEnforcementService para consultar limites de relatórios por plano.

## Constraints

- Não quebrar métodos existentes
- Seguir padrão do service (getCurrentPlan via invoice)

## Context

- Módulos afetados: Platform, Reports
- Dependências: TASK-005, TASK-007
- Referências: `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php`

## Etapas

- [ ] Adicionar método `getReportsMode(string $tenantId): PlatformReportsMode`
- [ ] Adicionar método `canViewReport(string $tenantId, string $permission): bool`
- [ ] Adicionar método `isAdmin(AuthUser $user): bool` — usar `user->hasRole('admin')` do Spatie
- [ ] Implementar lógica: BASIC = só reports.chat.volume, ADVANCED = reports.chat._ + reports.crm._ + reports.ai.autopilot + reports.ai.sentiment, FULL = todos + export
- [ ] Admin override: `reports.ai.usage_cost` e `reports.billing.revenue` liberadas para qualquer plano
- [ ] Escrever testes unitários

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(platform): add report permissions methods to PlanEnforcementService`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-009 — Atualizar ReportsPolicy com Filtro RBAC

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @BACKEND

## Goal

Atualizar o ReportsPolicy para usar `PlatformPlanEnforcementService->canViewReport()` em vez de apenas `user->can()` puro, aplicando filtro por plano.

## Constraints

- Manter retrocompatibilidade com permissões existentes
- Admin sempre tem acesso

## Context

- Módulos afetados: Reports, Platform
- Dependências: TASK-008
- Referências: `api/src/Domain/Reports/Policies/ReportsPolicy.php`

## Etapas

- [ ] Injete `PlatformPlanEnforcementService` no ReportsPolicy
- [ ] Atualizar viewCrm, viewChat, viewAi, viewBilling para usar canViewReport
- [ ] Admin override: checar role admin antes de aplicar filtro
- [ ] Atualizar método export para usar canViewReport('reports.export')
- [ ] Escrever testes de policy

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(reports): integrate plan-based report access control`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-010 — Atualizar RolePermissionSeeder

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @BACKEND

## Goal

Atualizar o RolePermissionSeeder para incluir todas as permissões `reports.*` e garantir mapeamento correto por plano (Starter = BASIC, Professional = ADVANCED, Business = FULL).

## Constraints

- Seguir formato existente do seeder (Spatie permissions)

## Context

- Módulos afetados: Auth, Platform
- Dependências: TASK-006, TASK-007
- Referências: `api/database/seeders/RolePermissionSeeder.php`

## Etapas

- [ ] Mapear permissões reports.\* por modo:
    - BASIC: reports.chat.volume
    - ADVANCED: reports.chat.volume, reports.chat.agent_performance, reports.crm.funnel, reports.crm.salesperson_performance, reports.crm.loss_reason, reports.crm.contact_crm, reports.ai.autopilot_performance, reports.ai.sentiment
    - FULL: todos os reports.\* + reports.export
- [ ] Admin (qualquer plano): reports.ai.usage_cost, reports.billing.revenue
- [ ] Garantir que permissões admin supersedem plano

## Critérios de conclusão

- [ ] Código implementado
- [ ] Testes escritos e passando
- [ ] Gates verdes
- [ ] Commit: `feat(auth): add report permissions to RolePermissionSeeder`

## Evidências

- Gates:
- Review:
- Commit:

---

# TASK-011 — Validar Seeders e Gates

## Status: todo

## Plano origem: PLAN-005

## Agente responsável: @QA

## Goal

Validar que todos os seeders executam corretamente e gates passam após as mudanças.

## Constraints

- Todos os gates devem estar verdes antes de fechar a task

## Context

- Módulos afetados: Todos
- Dependências: TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010

## Etapas

- [ ] Executar `composer gate:all` no backend
- [ ] Executar `php artisan db:seed --class=PlatformPlanSeeder` e validar 3 planos criados
- [ ] Executar `php artisan db:seed --class=RolePermissionSeeder` e validar permissões
- [ ] Verificar que `PlatformPlanEnforcementServiceTest` passa
- [ ] Verificar que `ReportsPolicyTest` passa
- [ ] Rodar suite completa de testes

## Critérios de conclusão

- [ ] Seeders executam sem erro
- [ ] Todos os testes passam
- [ ] Gates verdes
- [ ] Commit: `chore: validate plan seeder integration`

## Evidências

- Gates:
- Review:
- Commit:
