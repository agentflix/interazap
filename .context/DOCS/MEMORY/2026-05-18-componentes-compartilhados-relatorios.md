# Decisão — Componentes compartilhados para relatórios

**Data:** 2026-05-18
**Contexto:** INT-23 — Reduzir duplicação nos componentes de relatórios

## Problema

15 componentes de relatórios duplicavam massivamente:
- Page title + botão filtros (idêntico)
- Filter drawer com search, dates, granularity, export buttons (idêntico, só muda dataTest)
- Loading state com skeletons (padrão repetido)
- Error state com card + retry (idêntico, só muda mensagem)
- Empty state com af-empty-state (idêntico)
- Tabelas com classes Tailwind repetidas

Já existiam `AfReportFiltersComponent` (barra horizontal) e `AfReportExportComponent` no shared, mas **não eram usados** — cada relatório replicava tudo manualmente.

## Decisão

Criar 4 componentes compartilhados de estado para relatórios:
1. `af-report-loading` — Skeletons por layout (kpi+chart, kpi+table, table, chart)
2. `af-report-error` — Card com erro + botão retry (output)
3. `af-report-empty` — Wrapper de af-empty-state dentro de af-card
4. `af-report-skeleton-grid` — Grid responsivo de skeleton cards para KPIs

## Alternativas consideradas

1. **Usar ng-content com templates** — Mais flexível mas mais complexo para consumidores
2. **Criar um único componente "report-shell"** — Muito opinativo, dificultaria especializações
3. **Não fazer nada e manter duplicação** — Custo de manutenção crescente

## Lições aprendidas

- Componentes de estado (loading/error/empty) são candidatos ideais a shared components quando o padrão se repete em 3+ lugares
- Usar `computed()` com `Array.from({ length: n })` para iterar números em `@for` do Angular
- Prefixo `af-` para shared components, `app-` para components de página
- Refatoração incremental (4 de 15 relatórios) reduz risco de regressão

## Armadilhas

- `@for` com número não é iterável no Angular — requer array
- Property binding `[count]="4"` passa number, `count="4"` passa string
- Manter imports não utilizados após refatoração — remover sempre
