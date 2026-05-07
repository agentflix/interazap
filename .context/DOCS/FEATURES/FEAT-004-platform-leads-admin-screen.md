# Feature: Tela Administrativa de Leads da Plataforma

## Metadados
| Campo | Valor |
|-------|-------|
| **ID** | FEAT-004 |
| **Data** | 2026-05-07 |
| **Status** | Concluída |
| **Complexidade** | Média |
| **Bounded Context** | Platform |
| **Workspaces** | api, app |

## Contexto
A tabela `platform_leads` já existia e recebia dados pelo endpoint público `POST /api/public/leads`, mas não havia uma tela administrativa no grupo **Plataforma** para visualização operacional desses leads.

## Objetivo
Disponibilizar listagem paginada de leads da plataforma no painel administrativo, seguindo o padrão visual das telas existentes (referência: `platform/tenants`).

## Escopo
- Backend: endpoint autenticado `GET /api/platform/leads` com filtros.
- Frontend: nova tela `platform/leads` com busca, filtros, tabela e paginação.
- Navegação: inclusão no menu lateral em "Plataforma".

## Fora de Escopo
- Edição/manual update de status do lead.
- Conversão automática para CRM.
- Exportação CSV.

## Critérios de Aceite
- [x] CA-1: Usuário administrativo consegue listar leads em `/platform/leads`.
- [x] CA-2: Endpoint suporta filtros `search`, `status` e `source`.
- [x] CA-3: Usuário sem permissão recebe `403`.
- [x] CA-4: Tela segue padrão de layout de `tenants` (`AfCrudPage` + `AfDataTable`).
- [x] CA-5: Item de menu "Leads da Plataforma" disponível no grupo "Plataforma".

## Riscos e Mitigações
| Risco | Impacto | Mitigação |
|------|---------|-----------|
| Confusão de nomenclatura (`plataforms_lead`) | Médio | Padronização explícita para `platform_leads` |
| Acesso indevido ao endpoint admin | Alto | Policy + testes de autorização |

## Arquivos Principais
- `api/src/Domain/Platform/Http/Controllers/PlatformLeadAdminController.php`
- `api/src/Domain/Platform/Actions/PlatformLeadAdminActions.php`
- `api/src/Domain/Platform/Policies/PlatformLeadPolicy.php`
- `app/src/app/pages/platform/leads/platform-leads.ts`
- `app/src/app/pages/platform/leads/platform-leads.html`
- `app/src/app/core/services/platform-lead.service.ts`

## Evidências
- `php artisan test tests/Feature/Platform/PlatformLeadAdminControllerTest.php` ✅
- `pnpm build` (workspace `app`) ✅
