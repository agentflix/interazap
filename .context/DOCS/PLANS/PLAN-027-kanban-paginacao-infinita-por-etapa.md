# PLAN-027-kanban-paginacao-infinita-por-etapa — Paginação Infinita por Etapa no Kanban de Negociações

## Objetivo

Implementar paginação infinita (infinite scroll) por coluna/etapa no Kanban de negociações do CRM, garantindo que a tela suporte alto volume de negociações (milhares por funil) sem travar o frontend nem sobrecarregar o banco de dados com queries que retornam datasets massivos.

## Módulo relacionado

CRM | Shared

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Novo endpoint backend: paginação por etapa (step) com cursor-based pagination
- Refatorar `ListCRMNegotiationsAction.kanban()` para retornar apenas contadores + primeira página por step
- Endpoint dedicado `GET /crm/negotiations-kanban/step/{stepId}` com cursor pagination
- Frontend: infinite scroll por coluna do Kanban (carregar mais ao scrollar)
- Frontend: exibir contador total e valor total por coluna (sem carregar todos os cards)
- Índice composto otimizado para a query paginada por step
- Manter compatibilidade com drag-and-drop entre colunas
- Manter filtros existentes funcionando

### Excluído

- Virtualização de DOM (CDK Virtual Scroll) — pode ser adicionada futuramente se necessário
- Mudança na view de lista (já possui paginação)
- Mudanças no card/design visual das negociações
- Websocket/realtime updates para o Kanban
- Mudanças no endpoint de move/reorder (já funciona corretamente)

## Diagnóstico do Problema Atual

### Backend (`ListCRMNegotiationsAction::kanban()`)

1. **Query sem limite**: `$query->get()` carrega TODAS as negociações do funil de uma vez
2. **Agrupamento em memória**: `$negotiations->groupBy('crm_negotiation_funnel_step_id')` — com 10k negociações, isso consome memória significativa do PHP
3. **Eager loading massivo**: `with(['company', 'contact', 'tags', 'customFieldValues.field', 'user'])` multiplica o payload
4. **Serialização inline**: cada negociação é serializada manualmente com dados redundantes (funnel, step repetidos em cada item)

### Frontend (`negotiations.ts`)

1. **Sem paginação**: `kanbanSteps` signal recebe TODOS os cards de todas as colunas de uma vez
2. **Sem virtualização**: renderiza todos os `<div cdkDrag>` no DOM simultaneamente
3. **Reload completo**: após cada move/create/update, faz `loadKanban()` que recarrega tudo de novo

### Impacto em cenário de campanha

- Campanha gera 5.000+ contatos → 5.000+ negociações no funil
- Uma única query retorna 5.000 registros + 5 eager loads = ~25.000 queries no total
- Payload JSON: ~5MB+ (5.000 × ~1KB por negociação serializada)
- Frontend: 5.000 DOM nodes renderizados → travamento do browser
- PHP: pico de memória > 256MB na serialização

## Solução Proposta: Cursor Pagination por Etapa

### Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│ GET /crm/negotiations-kanban?funnel_id=X                │
│                                                         │
│ Retorna:                                                │
│   - funnel metadata                                     │
│   - steps[] com:                                        │
│     - id, name, color, order                            │
│     - total_count (COUNT)                               │
│     - total_value (SUM)                                 │
│     - negotiations[] (primeiros 20 apenas)              │
│     - has_more: bool                                    │
│     - next_cursor: string|null                          │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ GET /crm/negotiations-kanban/step/{stepId}              │
│   ?funnel_id=X&cursor=abc123&per_page=20&filters...     │
│                                                         │
│ Retorna:                                                │
│   - negotiations[] (próxima página)                     │
│   - has_more: bool                                      │
│   - next_cursor: string|null                            │
└─────────────────────────────────────────────────────────┘
```

### Cursor Strategy

Usar **cursor composto** baseado em `(position, id)` para garantir estabilidade durante drag-and-drop:

```
cursor = base64_encode(json_encode({position: 20, id: "uuid-last-item"}))
```

Query:

```sql
WHERE tenant_id = ?
  AND crm_negotiation_funnel_id = ?
  AND crm_negotiation_funnel_step_id = ?
  AND status = 'open'
  AND (position > ? OR (position = ? AND id > ?))
ORDER BY position ASC, id ASC
LIMIT 21  -- per_page + 1 para saber se has_more
```

### Por que Cursor e não Offset?

| Aspecto                        | Offset (`LIMIT X OFFSET Y`)             | Cursor (`WHERE position > ?`)             |
| ------------------------------ | --------------------------------------- | ----------------------------------------- |
| Performance em tabelas grandes | Degrada com offset alto (faz seq scan)  | Constante O(log n) via índice             |
| Consistência durante reorder   | Itens pulados/duplicados ao mover cards | Estável — cursor aponta para posição fixa |
| Compatibilidade com drag-drop  | Problemático                            | Natural — position é a chave de ordenação |

## Etapas Propostas

### Entrega 1 — Backend: Paginação por Etapa

1. **Criar índice otimizado** para a query paginada por step
2. **Refatorar `ListCRMNegotiationsAction::kanban()`** — retornar aggregates (count, sum) + primeira página por step
3. **Criar `ListCRMNegotiationsByStepAction`** — cursor pagination dedicada por step
4. **Criar `CRMNegotiationKanbanStepRequest`** — validação do novo endpoint
5. **Adicionar rota e método no Controller** — `GET /crm/negotiations-kanban/step/{stepId}`
6. **Testes backend** — Pest tests para paginação, cursor, filtros

### Entrega 2 — Frontend: Infinite Scroll por Coluna

7. **Atualizar `NegotiationService`** — novo método `kanbanStep(stepId, cursor, filters)`
8. **Atualizar interfaces** — `NegotiationKanbanStep` com `total_count`, `total_value`, `has_more`, `next_cursor`
9. **Refatorar `negotiations.ts`** — infinite scroll por coluna, append parcial ao signal
10. **Atualizar template HTML** — scroll listener + loading indicator por coluna
11. **Otimizar reload após move** — recarregar apenas as colunas afetadas, não o kanban inteiro
12. **Testes frontend** — Vitest para lógica de paginação e scroll

## Entregas Derivadas

**Entregas:** 2 | **Tasks:** 6

| Entrega | Descrição                            | Tasks                       | Esforço | Status |
| ------- | ------------------------------------ | --------------------------- | ------- | ------ |
| 1       | Backend: paginação cursor por etapa  | TASK-027.1.1 — TASK-027.1.3 | S       | todo   |
| 2       | Frontend: infinite scroll por coluna | TASK-027.2.1 — TASK-027.2.3 | S       | todo   |

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                         | Ação      | Caminho                                                                                                 |
| ------------------------------- | --------- | ------------------------------------------------------------------------------------------------------- |
| Migration                       | criar     | `api/database/migrations/2026_03_31_000001_add_kanban_cursor_index_crm_negotiations.php`                |
| ListCRMNegotiationsAction       | modificar | `api/src/Domain/CRM/Actions/ListCRMNegotiationsAction.php`                                              |
| ListCRMNegotiationsByStepAction | criar     | `api/src/Domain/CRM/Actions/ListCRMNegotiationsByStepAction.php`                                        |
| CRMNegotiationKanbanStepRequest | criar     | `api/src/Domain/CRM/Http/Requests/CRMNegotiationKanbanStepRequest.php`                                  |
| CrmNegotiationController        | modificar | `api/src/Domain/CRM/Http/Controllers/CrmNegotiationController.php`                                      |
| CRMNegotiationFilterService     | modificar | `api/src/Domain/CRM/Services/CRMNegotiationFilterService.php` (extrair apply para reusar em step query) |
| crm.php (routes)                | modificar | `api/src/Domain/CRM/Routes/crm.php`                                                                     |
| Test                            | criar     | `api/tests/Feature/CRMNegotiationKanbanPaginationTest.php`                                              |

### Frontend (Angular)

| Arquivo                | Ação      | Caminho                                                   |
| ---------------------- | --------- | --------------------------------------------------------- |
| negotiation.service.ts | modificar | `app/src/app/core/services/negotiation.service.ts`        |
| negotiations.ts        | modificar | `app/src/app/pages/crm/negotiations/negotiations.ts`      |
| negotiations.html      | modificar | `app/src/app/pages/crm/negotiations/negotiations.html`    |
| Test                   | criar     | `app/src/app/pages/crm/negotiations/negotiations.spec.ts` |

## Tarefas Derivadas para Execução

| Task         | Descrição                                            | Agente    | Paralelo com |
| ------------ | ---------------------------------------------------- | --------- | ------------ |
| TASK-027.1.1 | Migration + índice + Action paginada por step        | @BACKEND  | -            |
| TASK-027.1.2 | Refatorar kanban() para aggregates + primeira página | @BACKEND  | TASK-027.1.1 |
| TASK-027.1.3 | Rota, request, controller + testes Pest              | @BACKEND  | após 027.1.1 |
| TASK-027.2.1 | Service + interfaces + modelo de cursor              | @FRONTEND | após 027.1.3 |
| TASK-027.2.2 | Refatorar negotiations.ts com infinite scroll        | @FRONTEND | após 027.2.1 |
| TASK-027.2.3 | Template HTML + loading per-column + testes          | @FRONTEND | após 027.2.2 |

## Riscos e Dependências

### Riscos

| Risco                                                      | Probabilidade | Impacto | Mitigação                                                                           |
| ---------------------------------------------------------- | ------------- | ------- | ----------------------------------------------------------------------------------- |
| Cursor invalidado após reorder massivo                     | Média         | Médio   | Fallback: re-fetch da coluna se cursor retornar 0 resultados                        |
| Drag-drop entre colunas com itens não carregados           | Baixa         | Baixo   | Position do item movido é definida pelo backend; reload parcial da coluna destino   |
| Filtro de busca (ILIKE) em volume alto sem full-text index | Média         | Alto    | Adicionar pg_trgm index ou limitar busca a título (sem subquery em company/contact) |
| CDK DragDrop com listas parcialmente carregadas            | Média         | Médio   | O item é movido otimisticamente; backend recalcula posições; reload pós-move        |

### Dependências

- Índices existentes: `idx_crm_negotiations_tenant_funnel_status` já cobre `(tenant_id, crm_negotiation_funnel_id, status)`
- Precisa de novo índice composto incluindo `crm_negotiation_funnel_step_id` + `position` para cursor pagination eficiente

## Estimativa

| Item                          | Valor                                                   |
| ----------------------------- | ------------------------------------------------------- |
| Complexidade                  | Média                                                   |
| Camadas afetadas              | Backend / Frontend                                      |
| Migrações necessárias         | Sim (1 — índice composto)                               |
| Impacto em módulos existentes | Baixo (endpoint kanban refatorado, mas retrocompatível) |

## Validação e Gates

- [ ] Backend: `composer gate:all` em api/
- [ ] Frontend: `pnpm run gate:all` em app/
- [ ] Teste de carga: simular 5.000 negociações em um funil e verificar tempo de resposta < 200ms
- [ ] Verificar que drag-drop funciona normalmente entre colunas paginadas
