# TASKS-021 — Adicionar path aliases no tsconfig.json do Angular

## Descrição

Adicionar novos path aliases no `tsconfig.json` do Angular para encurtar e padronizar os imports, substituindo paths relativos profundos por aliases semânticos por domínio.

## Tipo

Refatoração (Frontend)

## Módulo

Frontend (Angular)

## Critérios de aceite

- [ ] `tsconfig.json` atualizado com aliases: `@layout/*`, `@pages/*`, `@ai/*`, `@auth/*`, `@billing/*`, `@chat/*`, `@crm/*`, `@dashboard/*`, `@platform/*`, `@reports/*`, `@admin/*`, `@public/*`, `@settings/*`
- [ ] `tsconfig.spec.json` atualizado com os mesmos aliases
- [ ] `pnpm run build` executa com sucesso
- [ ] `pnpm run lint` executa com sucesso

## Arquivos a modificar

| Arquivo | Ação |
|---------|------|
| `app/tsconfig.json` | Modificar |
| `app/tsconfig.spec.json` | Modificar |

## Novos aliases a adicionar

```json
{
  "paths": {
    "@app/*": ["src/app/*"],
    "@core/*": ["src/app/core/*"],
    "@env/*": ["src/environments/*"],
    "@shared/*": ["src/app/shared/*"],
    "@layout/*": ["src/app/layout/*"],
    "@pages/*": ["src/app/pages/*"],
    "@ai/*": ["src/app/pages/ai/*"],
    "@auth/*": ["src/app/pages/auth/*"],
    "@billing/*": ["src/app/pages/billing/*"],
    "@chat/*": ["src/app/pages/chat/*"],
    "@crm/*": ["src/app/pages/crm/*"],
    "@dashboard/*": ["src/app/pages/dashboard/*"],
    "@platform/*": ["src/app/pages/platform/*"],
    "@reports/*": ["src/app/pages/reports/*"],
    "@admin/*": ["src/app/pages/admin/*"],
    "@public/*": ["src/app/pages/public/*"],
    "@settings/*": ["src/app/pages/settings/*"]
  }
}
```

## Status

todo
