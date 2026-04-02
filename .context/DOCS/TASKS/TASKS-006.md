# TASKS-006 — Refatorar Layout Filtros Negociações

## Plano Relacionado

[PLAN-006](./PLANS/PLAN-006-refatorar-layout-filtros-negociacoes.md)

## Tasks

| ID | Descrição | Agente | Status | Dependências |
|----|-----------|--------|--------|--------------|
| TASK-006-FRONT | Refatorar layout de filtros da página Negotiations | FRONTEND | done | - |

## TASK-006-FRONT: Refatorar layout de filtros da página Negotiations

### Descrição

Reorganizar o layout da página de Negociações conforme PLAN-006:

1. **Remover `negotiation-filter-bar`** (em `negotiations.html`):
   - Remover completamente o componente `<app-negotiation-filter-bar>`
   - Remove search input, chips de filtros ativos, e tudo que estava na barra de filtros

2. **Adicionar botão "+ Filtros" no header** (em `negotiations.html`):
   - Dentro do `app-page-title`, ao lado do toggle Lista/Kanban
   - Este botão abre o offcanvas de filtros

3. **Adicionar search input no offcanvas** (em `negotiations.html`):
   - Adicionar campo de busca no topo do `af-drawer`, junto com os outros filtros
   - Usar o `searchControl` existente que já está conectado à lógica de filtros

4. **Adicionar CSV/XLSX no offcanvas** (em `negotiations.html`):
   - No footer do `af-drawer`, adicionar botões de exportar CSV e XLSX
   - Conectar ao método `onExport()` existente (mantém stub)

5. **Limpeza no negotiations.ts**:
   - Remover import de `NegotiationFilterBarComponent`
   - Limpar código morto: `activeFilterChips`, `advancedFiltersCount`, `filterStatusOptions`
   - O `searchControl` permanece pois é usado pelo offcanvas
   - `removeActiveFilter` pode precisar de ajuste para lidar com search removido da UI principal

6. **Destino do `NegotiationFilterBarComponent`**:
   - Verificar se é usado em outro lugar via grep
   - Se não for usado em outro lugar, remover a pasta `components/negotiation-filter-bar/`

### Arquivos a Alterar

- `app/src/app/pages/crm/negotiations/negotiations.html`
- `app/src/app/pages/crm/negotiations/negotiations.ts`
- `app/src/app/pages/crm/negotiations/components/` (remover `negotiation-filter-bar/` se não usado em outro lugar)

### Critérios de Aceitação

- [x] Não existe mais filter bar na barra principal
- [x] Botão "+ Filtros" aparece ao lado do toggle Lista/Kanban no header
- [x] Offcanvas contém campo de busca + todos os filtros disponíveis
- [x] Botões CSV/XLSX aparecem no footer do offcanvas de filtros
- [x] Ao clicar em "+ Filtros", offcanvas abre com todos os filtros
- [x] Layout responsivo funciona corretamente
- [x] Gates passam: `pnpm run gate:all`

### Evidências de conclusão

- `negotiations.html` com botão de filtros no header (`openFilter()`), `af-search-input` no drawer e exportações CSV/XLSX no drawer.
- Ausência de `<app-negotiation-filter-bar>` no template atual.
- `negotiations.ts` mantendo `searchControl` conectado ao drawer e fluxo de filtros.
- `npm run gate:all` no frontend concluído com sucesso (`116/116` arquivos de teste, `644/644` testes, build de produção OK).
