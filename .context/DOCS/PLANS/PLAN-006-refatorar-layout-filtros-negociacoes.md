# PLAN-006 — Refatorar Layout Filtros Negociações

## Objetivo

Simplificar o layout da página de Negociações removendo a barra de busca e filtros duplicados da tela, deixando apenas o botão "+ Filtros" no header. Toda a filtragem será feita exclusivamente via offcanvas.

## Módulo relacionado

CRM | Platform

## PRD relacionado (se existir)

N/A — Refatoração de UI

## Escopo

### Incluído

- Remover `negotiation-filter-bar` completamente da página (não é mais necessário)
- Mover botão "+ Filtros" para o header da página (ao lado do toggle Lista/Kanban)
- Mover botões CSV/XLSX para dentro do offcanvas de filtros (footer)
- Adicionar campo de busca (search input) dentro do offcanvas de filtros
- Remover search input e todos os chips de filtro da view principal
- Ajustar spacings e layout do header para acomodar o novo botão
- Limpar código morto: `activeFilterChips`, `advancedFiltersCount`, `filterStatusOptions` e lógica de chips relacionada (ou reutilizar se aplicável ao offcanvas)
- Remover ou depreciar componente `NegotiationFilterBarComponent` se não for usado em outro lugar

### Excluído

- Alteração de lógica de filtros (mantém comportamento atual)
- Alteração nos demais campos do offcanvas de filtros (funil, etapa, responsável, etc.)
- Implementação real de export (mantém stub `console.warn`)
- Alterações em outros módulos ou páginas

## Etapas propostas

1. **Frontend (negotiations.html)**: Remover `<app-negotiation-filter-bar>` completamente
2. **Frontend (negotiations.html)**: Adicionar botão "+ Filtros" no header (ao lado do toggle Lista/Kanban)
3. **Frontend (negotiations.html)**: Adicionar campo de busca (search input) dentro do `af-drawer` de filtros (no topo, junto com os outros campos)
4. **Frontend (negotiations.html)**: Adicionar botões CSV/XLSX no footer do af-drawer
5. **Frontend (negotiations.ts)**: Remover import de `NegotiationFilterBarComponent` se não for mais usado
6. **Frontend (negotiations.ts)**: Limpar código morto: `activeFilterChips`, `advancedFiltersCount`, `filterStatusOptions`, `removeActiveFilter` (ou adaptar para usar com os novos chips se necessário)
7. **Frontend (negotiations.ts)**: Verificar se `onExport()` conecta corretamente aos botões no drawer (mantém stub)
8. **Frontend**: Verificar se `NegotiationFilterBarComponent` é usado em outro lugar; se não, remover ou marcar como deprecated
9. **Validação**: Verificar se não há quebras de layout e gates passam

## Tasks derivadas

| Task | Descrição | Agente | Status |
|------|-----------|--------|--------|
| TASK-006-FRONT | Refatorar layout de filtros da página Negotiations | FRONTEND | todo |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Botão "+ Filtros" mover para header pode afetar responsividade | Baixa | Baixo | Testar em diferentes breakpoints |
| Remoção de filtros visíveis pode confundir usuário | Baixa | Médio | Garantir que offcanvas tenha todos os filtros necessários |

### Dependências

- Nenhuma dependência externa

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Baixa |
| Camadas afetadas | Frontend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Não |
