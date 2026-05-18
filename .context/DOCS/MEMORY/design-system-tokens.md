# Decisão — Tokens do Design System

**Data:** 2026-05-17
**Tipo:** Decisão
**Ref:** INT-6, INT-7, INT-9, INT-16, INT-17

---

## Contexto

O InteraZap precisava de um design system consistente para substituir cores hardcoded e estilos inconsistentes espalhados pela codebase. A migração foi planejada como parte do projeto "Migração Design System InteraZap".

## Decisão

Adotar CSS custom properties como base do design system com os seguintes tokens:

### Cores
- **Primary (Emerald):** `#3ecf8e` (primary-400), `#24b47e` (primary-500)
- **Ink:** `#171717` (neutral-900)
- **Muted:** `#707070` (neutral-500)
- **Canvas:** `#ffffff`, `#fafafa` (surface-50)
- **Canvas Night:** `#1c1c1e` (neutral-900 dark)
- **Hairline:** `#dfdfdf`

### Radius Scale
- xs: 4px, sm: 6px, md: 8px, lg: 12px, xl: 16px, xxl: 24px

### Tipografia
- Fonte: Inter (substituindo Circular)
- Motivo: Inter é open source, disponível via Google Fonts, com boa legibilidade em telas.

### Sombras
- sm, md, lg, xl — sutis e técnicas, sem excessos visuais.

## Alternativas Consideradas

1. **Tailwind CSS tokens:** Rejeitado porque o projeto já usa CSS custom properties e a migração incremental seria mais complexa.
2. **Manter Circular:** Rejeitado por ser fonte paga; Inter oferece qualidade similar sem custo.
3. **SASS variables:** Rejeitado em favor de CSS custom properties para suporte a dark mode e runtime theming.

## Consequências

- ✅ Consistência visual em toda a aplicação.
- ✅ Suporte a dark mode via CSS custom properties.
- ✅ Facilidade de manutenção e evolução do design system.
- ⚠️ Componentes legados ainda podem usar cores hardcoded — migração incremental necessária.

## Evidência

- Tokens definidos em `app/src/styles.css`.
- Componentes base migrados: `.btn-primary`, `.btn-secondary`, `.btn-link`, `.form-input`, `.card`, `.pill`.
- Gates de build e test passando para todas as tasks.
