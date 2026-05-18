# Changelog — 2026-05-17 — Migração Design System InteraZap

**Feature:** Migração Design System InteraZap
**Project:** https://linear.app/interazap/project/migracao-design-system-interazap
**Data:** 2026-05-17

---

## Entradas por Task

- [19:30] [FEAT] [design-system]: migrar tokens globais para CSS custom properties
  - Definição de cores semânticas (primary, ink, canvas, hairline), radius scale, tipografia Inter e sombras técnicas em `app/src/styles.css`
  - Arquivos: `app/src/styles.css`
  - Ref: INT-6
  - Gates: `pnpm --filter app test` ✅, `pnpm --filter app build` ✅

- [19:45] [FEAT] [shared-ui]: migrar componentes base para tokens semânticos
  - Classes `.btn-primary`, `.btn-secondary`, `.btn-link`, `.form-input`, `.card`, `.pill` com tokens consistentes
  - Arquivos: `app/src/styles.css`
  - Ref: INT-7
  - Gates: `pnpm --filter app test` ✅, `pnpm --filter app build` ✅

- [20:00] [FEAT] [app-shell]: migrar layout, sidenav e topbar para design system
  - Shell neutro e técnico com hairlines consistentes e focus states acessíveis
  - Arquivos: componentes de layout, sidenav e topbar em `app/src/`
  - Ref: INT-9
  - Gates: `pnpm --filter app test` ✅, `pnpm --filter app build` ✅

- [20:15] [FEAT] [webchat]: migrar widget e embed público para tokens do design system
  - 88 testes passando, cores semânticas, radius consistente nas páginas do webchat
  - Arquivos: páginas do webchat em `app/src/`
  - Ref: INT-16
  - Gates: 88 webchat tests ✅, `ng build` ✅, `ng lint` ✅

- [20:30] [FEAT] [electron]: alinhar desktop com design system
  - Tray icon emerald #3ecf8e, window background #fafafa, build icons alinhados
  - Arquivos: configuração Electron, tray icons, window styles
  - Ref: INT-17
  - Gates: Angular build ✅ (2.29 MB), TypeScript compile ✅, Electron prebuild ✅

- [20:45] [DOCS] [migration]: registrar PREVC, changelog, memory e gates da migração
  - Feature doc, changelog, memory entries e project-state.yaml criados/atualizados
  - Arquivos: `.context/DOCS/FEATURES/migracao-design-system.md`, `.context/DOCS/CHANGELOG/2026-05-17-migracao-design-system.md`, `.context/DOCS/MEMORY/`, `.context/ARCHITECTURE/project-state.yaml`
  - Ref: INT-18

## Breaking Changes

Nenhuma breaking change. Migração incremental com compatibilidade mantida.

## Métricas

- **Tasks concluídas:** 6 (INT-6, INT-7, INT-9, INT-16, INT-17, INT-18)
- **Gates executados:** 12 (todos passando)
- **Arquivos afetados:** `app/src/styles.css`, componentes de shell, webchat, Electron, documentação `.context/`
