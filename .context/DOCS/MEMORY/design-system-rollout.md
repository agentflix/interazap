# Aprendizado — Estratégia de Rollout do Design System

**Data:** 2026-05-17
**Tipo:** Aprendizado
**Ref:** INT-6, INT-7, INT-9, INT-16, INT-17

---

## Contexto

A migração do design system precisava ser feita de forma incremental para não quebrar funcionalidades existentes e permitir validação contínua em cada etapa.

## Estratégia Adotada

O rollout foi executado em 5 fases sequenciais:

### Fase 1: Tokens Globais (INT-6)
- Definição de CSS custom properties em `app/src/styles.css`.
- Base para todas as fases subsequentes.
- Validação: `pnpm --filter app test` + `pnpm --filter app build`.

### Fase 2: Componentes Base (INT-7)
- Migração de botões, formulários, cards e pills para tokens semânticos.
- Componentes reutilizáveis que servem como blocos de construção.
- Validação: testes e build do app.

### Fase 3: App Shell (INT-9)
- Layout, sidenav e topbar com design neutro e técnico.
- Focus states acessíveis e hairlines consistentes.
- Validação: testes e build do app.

### Fase 4: WebChat Público (INT-16)
- Widget e embed com tokens semânticos.
- 88 testes passando como evidência de qualidade.
- Validação: `ng build` + `ng lint` + testes.

### Fase 5: Electron Desktop (INT-17)
- Tray icon, window background e build icons alinhados.
- Validação: Angular build (2.29 MB), TypeScript compile, Electron prebuild.

## Lições Aprendidas

1. **Tokens primeiro:** Definir tokens antes de migrar componentes evita retrabalho e inconsistências.
2. **Validação contínua:** Executar gates em cada fase garante que regressões são detectadas cedo.
3. **Rollout incremental:** Migrar por módulo (shell, webchat, electron) permite paralelização e rollback seguro.
4. **Documentação paralela:** Registrar decisões e aprendizados durante a migração facilita sessões futuras.

## Consequências

- ✅ Migração sem breaking changes.
- ✅ Gates passando em todas as fases.
- ✅ Base sólida para futuras evoluções do design system.
