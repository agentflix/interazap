# Decisão — Acessibilidade no Design System

**Data:** 2026-05-17
**Tipo:** Decisão
**Ref:** INT-6, INT-7, INT-9

---

## Contexto

Durante a migração do design system, decisões de acessibilidade foram tomadas para garantir conformidade com WCAG AA e boa experiência para todos os usuários.

## Decisões

### Focus Visible
- **Decisão:** Todos os elementos interativos devem ter `focus-visible` obrigatório.
- **Implementação:** Outline com cor primary (#3ecf8e) e offset adequado.
- **Motivo:** Garantir navegabilidade por teclado sem poluição visual para mouse users.

### Reduced Motion
- **Decisão:** Respeitar `prefers-reduced-motion` para desabilitar animações.
- **Implementação:** Media query para reduzir ou remover transições e animações.
- **Motivo:** Acessibilidade para usuários com sensibilidade a movimento.

### Contraste
- **Decisão:** Manter contraste WCAG AA para texto e elementos interativos.
- **Implementação:** Ink (#171717) sobre Canvas (#ffffff) = ratio 15.4:1 (AAA). Muted (#707070) sobre Canvas = ratio 4.6:1 (AA).
- **Motivo:** Legibilidade para usuários com baixa visão.

### Tipografia
- **Decisão:** Inter como fonte principal, com fallback para sans-serif.
- **Motivo:** Inter foi projetada para telas, com boa legibilidade em tamanhos pequenos e suporte a múltiplos pesos.

## Alternativas Consideradas

1. **Focus ring customizado com box-shadow:** Rejeitado em favor de outline nativo para compatibilidade com browsers e assistive technologies.
2. **Desabilitar animações completamente:** Rejeitado em favor de respeitar `prefers-reduced-motion` para não prejudicar a UX de usuários sem essa preferência.

## Consequências

- ✅ Conformidade WCAG AA para contraste e foco.
- ✅ Suporte a usuários com sensibilidade a movimento.
- ✅ Navegabilidade por teclado garantida.
- ⚠️ Monitorar feedback de usuários para ajustes futuros.

## Evidência

- Focus states implementados em componentes base (INT-7).
- App shell com focus states acessíveis (INT-9).
- Gates de build e test passando com as mudanças de acessibilidade.
