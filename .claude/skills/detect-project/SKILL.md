# Skill: Detect Project

> Como inspecionar o projeto InteraZap rapidamente.

## Inspeção rápida

```bash
# Workspaces
cat pnpm-workspace.yaml

# Stack global
cat package.json
cat api/composer.json
cat gateway/package.json
cat app/package.json
cat electron/package.json

# Bounded contexts
ls api/src/Domain/

# Estrutura frontend
ls app/src/app/

# Migrations recentes
ls -lt api/database/migrations/ | head

# Estado das métricas
cat .context/ARCHITECTURE/project-state.yaml
```

## Mapa rápido

| Workspace | Stack | Path |
|-----------|-------|------|
| api | Laravel 12 / PHP 8.3 / DDD | `api/` |
| gateway | NestJS 11 / TS 5.7 | `gateway/` |
| app | Angular 19 / Ionic / Capacitor | `app/` |
| electron | Electron 33 / Angular 20 | `electron/` |
| landing | site marketing | `landing/` |
| infra | Ansible / nginx | `infra/` |
| observability | Métricas | `observability/` |

## Bounded contexts (api/src/Domain)

`Ai`, `Auth`, `Billing`, `Chat`, `Configuration`, `CRM`, `Dashboard`, `Gateway`, `Platform`, `Reports`, `Shared`

## Antes de começar uma task

1. Identificar workspace e bounded context
2. Ler MEMORY relacionado
3. Ver `.context/ARCHITECTURE/modules.yaml`
4. Ver `.context/DOCS/FEATURES/` (se feature já documentada)
