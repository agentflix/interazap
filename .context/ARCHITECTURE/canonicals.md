# Arquivos Canônicos de Referência

> Lido por **builder-explore** ao mapear padrões.
> Novo padrão canônico surgiu? Adicionar aqui — não duplicar em AGENTS.md.

## Backend

| Padrão | Arquivo canônico |
|---|---|
| Action (business logic) | `api/src/Domain/Platform/Actions/PlatformTenantActions.php` |
| FormRequest (validação) | `api/src/Domain/Auth/Http/Requests/AuthUserStoreRequest.php` |
| Resource (transformer JSON) | `api/src/Domain/Platform/Http/Resources/PlatformTenantResource.php` |
| DTO | `api/src/Domain/Auth/DTOs/AuthUserDTO.php` |
| Controller REST | `api/src/Domain/Auth/Http/Controllers/AuthUserController.php` |

## Frontend

| Padrão | Arquivo canônico |
|---|---|
| Page com modal + lista | `app/src/app/pages/platform/users/platform-users.ts` |
| Form component (input/output) | `app/src/app/pages/platform/users/components/platform-user-form/platform-user-form.ts` |
| Service HTTP | `app/src/app/core/services/platform-user.service.ts` |
| Route guard | `app/src/app/core/guards/auth.guard.ts` |
| Model interface | `app/src/app/core/models/platform-user.model.ts` |
