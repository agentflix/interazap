# PLAN-021-angular-path-aliases — Adicionar path aliases no tsconfig.json do Angular

## Objetivo

Adicionar novos path aliases no `tsconfig.json` do Angular para encurtar e padronizar os imports, substituindo paths relativos profundos por aliases semânticos por domínio.

## Módulo relacionado

Frontend (Angular)

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Adicionar aliases para domínios em `app/src/app/pages/`: `@ai/*`, `@auth/*`, `@billing/*`, `@chat/*`, `@crm/*`, `@dashboard/*`, `@platform/*`, `@reports/*`, `@admin/*`, `@public/*`, `@settings/*`
- Adicionar alias `@layout/*` para `app/src/app/layout/*`
- Adicionar alias `@pages/*` para `app/src/app/pages/*`
- Atualizar `tsconfig.json` e `tsconfig.spec.json` com novos paths

### Excluído

- Migração de imports existentes nos arquivos (será feito em task separada)
- Alterações no backend ou gateway
- Criação de novos componentes ou serviços

## Etapas propostas

1. **ETAPA 1**: Atualizar `tsconfig.json` com novos path aliases
2. **ETAPA 2**: Atualizar `tsconfig.spec.json` com os mesmos aliases
3. **ETAPA 3**: Validar que o Angular build continua funcionando (`ng build` ou `pnpm run build`)

## Tasks derivadas

| Task | Descrição | Agente | Status |
|------|-----------|--------|--------|
| TASKS-021 | Implementar path aliases no tsconfig.json | @FRONTEND | todo |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Alias conflitante com módulo existente | Baixa | Alta | Verificar nomes antes de adicionar |
| Build quebrado após mudança | Baixa | Alta | Executar gate de validação |

### Dependências

- Nenhuma dependência externa

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Baixa |
| Camadas afetadas | Frontend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Nenhum (apenas tsconfig) |
