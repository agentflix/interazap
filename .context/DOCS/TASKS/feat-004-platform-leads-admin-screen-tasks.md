# Tasks — FEAT-004 Platform Leads Admin Screen

> Decomposição T.A.C.E hierárquica (TASK-X.Y.Z) para FEAT-004.
> Feature doc: `../FEATURES/FEAT-004-platform-leads-admin-screen.md`

## Fase 3 — Backend

- [x] **TASK-3.1.1** ✅: Criar endpoint administrativo de listagem de leads
  **T — Tarefa:** Implementar controller/action para `GET /api/platform/leads` com paginação e filtros.
  **A — Arquivo:**
  - `api/src/Domain/Platform/Http/Controllers/PlatformLeadAdminController.php`
  - `api/src/Domain/Platform/Actions/PlatformLeadAdminActions.php`
  - `api/src/Domain/Platform/Http/Resources/PlatformLeadAdminResource.php`
  - `api/src/Domain/Platform/Routes/platform.php`
  **C — Comportamento:** Antes existia apenas captura pública; depois passou a existir listagem admin autenticada.
  **E — Evidência:** Feature test `PlatformLeadAdminControllerTest` validando listagem e filtros.

- [x] **TASK-3.1.2** ✅: Garantir autorização por policy
  **T — Tarefa:** Criar policy para controlar acesso admin aos leads da plataforma.
  **A — Arquivo:** `api/src/Domain/Platform/Policies/PlatformLeadPolicy.php`
  **C — Comportamento:** Usuário sem privilégios passa a receber `403`.
  **E — Evidência:** Teste de acesso negado no feature test.

- [x] **TASK-3.1.3** ✅: Adicionar testes backend
  **T — Tarefa:** Cobrir listagem, autorização e filtros.
  **A — Arquivo:** `api/tests/Feature/Platform/PlatformLeadAdminControllerTest.php`
  **C — Comportamento:** Sem cobertura → cobertura funcional do endpoint admin.
  **E — Evidência:** `php artisan test tests/Feature/Platform/PlatformLeadAdminControllerTest.php`.

## Fase 5 — Frontend

- [x] **TASK-5.1.1** ✅: Criar service Angular para leads
  **T — Tarefa:** Implementar service com filtros e tipagens.
  **A — Arquivo:** `app/src/app/core/services/platform-lead.service.ts`
  **C — Comportamento:** Sem client de API → com client dedicado.
  **E — Evidência:** Consumo pela página `platform/leads` e build do app.

- [x] **TASK-5.1.2** ✅: Criar tela `platform/leads` no padrão tenant
  **T — Tarefa:** Implementar tela com `AfCrudPage`, filtros, tabela e paginação.
  **A — Arquivo:**
  - `app/src/app/pages/platform/leads/platform-leads.ts`
  - `app/src/app/pages/platform/leads/platform-leads.html`
  **C — Comportamento:** Tela inexistente → listagem funcional com UX consistente.
  **E — Evidência:** `pnpm build` do app concluído sem erros.

- [x] **TASK-5.1.3** ✅: Integrar rota e menu
  **T — Tarefa:** Expor nova tela na navegação principal.
  **A — Arquivo:**
  - `app/src/app/app.routes.ts`
  - `app/src/app/layout/components/sidenav/menu-config.ts`
  **C — Comportamento:** Sem rota/menu → rota protegida e item no grupo Plataforma.
  **E — Evidência:** Build do app e inspeção do menu configurado.

## Fase 6 — Confirm

- [x] **TASK-6.1.1** ✅: Registrar artefatos PREVC
  **T — Tarefa:** Atualizar changelog, memory e project-state.
  **A — Arquivo:**
  - `.context/DOCS/CHANGELOG/2026-05-07.md`
  - `.context/DOCS/MEMORY/2026-05-07-platform-leads-admin-screen.md`
  - `.context/ARCHITECTURE/project-state.yaml`
  **C — Comportamento:** Mudança sem trilha documental → rastreabilidade completa.
  **E — Evidência:** Arquivos preenchidos e versionados.
