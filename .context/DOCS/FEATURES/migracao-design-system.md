# Migração Design System InteraZap

**Feature ID:** INT-MIGRATION-DS
**Linear Epic:** Migração Design System InteraZap (Project ID: 560a0d78-ae98-4650-9cc0-666fa48bf948)
**Status:** ✅ Concluída
**Data de conclusão:** 2026-05-17
**Responsável:** Rafael Silva

---

## Descrição

Migração completa do design system do InteraZap para um sistema baseado em tokens CSS semânticos, com paleta emerald (#3ecf8e), tipografia Inter, scale de radius consistente e sombras técnicas. A migração abrangeu tokens globais, componentes compartilhados, app shell, webchat público e Electron desktop.

## Escopo e Objetivos

- **Tokens globais:** cores semânticas (primary, ink, canvas, hairline), scale de radius, tipografia Inter, sombras.
- **Componentes base:** botões, formulários, cards, pills com tokens consistentes.
- **App Shell:** layout, sidenav, topbar com design neutro e técnico.
- **WebChat público:** widget e embed com tokens semânticos.
- **Electron:** alinhamento desktop com tray icon e window background.

## Tasks

| Issue | Título | Status | Resumo |
|-------|--------|--------|--------|
| [INT-6](https://linear.app/interazap/issue/INT-6) | Design System — migrar tokens globais | ✅ Done | Tokens CSS em `app/src/styles.css`: primary #3ecf8e, ink #171717, canvas #ffffff, hairline #dfdfdf, radius scale, fonte Inter |
| [INT-7](https://linear.app/interazap/issue/INT-7) | Shared UI — migrar componentes base | ✅ Done | Classes `.btn-primary`, `.btn-secondary`, `.btn-link`, `.form-input`, `.card`, `.pill` com tokens semânticos |
| [INT-9](https://linear.app/interazap/issue/INT-9) | App Shell — migrar layout, sidenav, topbar | ✅ Done | Shell neutro, técnico, hairlines, focus states acessíveis |
| [INT-16](https://linear.app/interazap/issue/INT-16) | WebChat Público — migrar widget, embed | ✅ Done | Páginas webchat migradas para tokens do design system, cores semânticas, radius adequado |
| [INT-17](https://linear.app/interazap/issue/INT-17) | Electron — alinhar desktop | ✅ Done | Tray icon emerald #3ecf8e, window bg #fafafa, build icons alinhados |
| [INT-18](https://linear.app/interazap/issue/INT-18) | Docs & Validation — registrar PREVC, changelog, memory e gates | ✅ Done | Documentação PREVC, changelog, memory entries e project-state atualizados |

## Resumo de Mudanças por Task

### INT-6 — Tokens Globais
- **Arquivos:** `app/src/styles.css`
- **Mudanças:** Definição de CSS custom properties para cores, radius, sombras, tipografia e espaçamento.
- **Tokens definidos:**
  - `--primary-400: #3ecf8e`, `--primary-500: #24b47e`
  - `--ink: #171717`, `--muted: #707070`
  - `--canvas: #ffffff`, `--surface-50: #fafafa`
  - `--canvas-night: #1c1c1e`
  - `--hairline: #dfdfdf`
  - Radius: `--radius-xs: 4px`, `--radius-sm: 6px`, `--radius-md: 8px`, `--radius-lg: 12px`, `--radius-xl: 16px`, `--radius-xxl: 24px`
  - Font: `--font-sans: 'Inter', sans-serif`

### INT-7 — Componentes Base
- **Arquivos:** `app/src/styles.css`
- **Mudanças:** Classes utilitárias semânticas para botões, formulários, cards e pills.
- **Componentes:** `.btn-primary`, `.btn-secondary`, `.btn-link`, `.form-input`, `.card`, `.pill`

### INT-9 — App Shell
- **Arquivos:** Componentes de layout, sidenav e topbar em `app/src/`
- **Mudanças:** Aplicação de tokens ao shell da aplicação, focus states visíveis, hairlines consistentes.

### INT-16 — WebChat Público
- **Arquivos:** Páginas do webchat em `app/src/`
- **Mudanças:** 88 testes passando, migração de cores hardcoded para tokens semânticos, radius consistente.

### INT-17 — Electron Desktop
- **Arquivos:** Configuração Electron, tray icons, window styles
- **Mudanças:** Tray icon atualizado para emerald #3ecf8e, background da janela #fafafa, ícones de build alinhados.

### INT-18 — Docs & Validation
- **Arquivos:** `.context/DOCS/FEATURES/`, `.context/DOCS/CHANGELOG/`, `.context/DOCS/MEMORY/`, `.context/ARCHITECTURE/project-state.yaml`
- **Mudanças:** Feature doc, changelog, memory entries e project-state.yaml criados/atualizados.

## Gate Evidence

| Task | Gate | Resultado |
|------|------|-----------|
| INT-6 | `pnpm --filter app test` | ✅ Pass |
| INT-6 | `pnpm --filter app build` | ✅ Pass |
| INT-7 | `pnpm --filter app test` | ✅ Pass |
| INT-7 | `pnpm --filter app build` | ✅ Pass |
| INT-9 | `pnpm --filter app test` | ✅ Pass |
| INT-9 | `pnpm --filter app build` | ✅ Pass |
| INT-16 | Webchat tests (88 specs) | ✅ 88 pass |
| INT-16 | `ng build` | ✅ Pass |
| INT-16 | `ng lint` | ✅ Pass |
| INT-17 | Angular build | ✅ Pass (2.29 MB) |
| INT-17 | TypeScript compile | ✅ Pass |
| INT-17 | Electron prebuild | ✅ Pass |

## Breaking Changes

Nenhuma breaking change identificada. A migração foi feita de forma incremental, mantendo compatibilidade com o código existente.

## Decisões Técnicas

1. **Emerald como cor primária:** #3ecf8e escolhido para transmitir frescor e confiabilidade.
2. **Inter como fonte:** Substituição do Circular por Inter (Google Fonts, open source).
3. **Rollout incremental:** Tokens → Componentes → Shell → Módulos → Electron.
4. **Acessibilidade:** Focus-visible obrigatório, suporte a reduced-motion, contraste WCAG AA.

## Próximos Passos

- Monitorar adoção dos tokens em novos componentes.
- Avaliar migração de componentes restantes que ainda usam cores hardcoded.
- Documentar guidelines de uso do design system para novos desenvolvedores.
