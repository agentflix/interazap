# TASKS-027 — Kanban Paginação Infinita por Etapa

## Plano: PLAN-027-kanban-paginacao-infinita-por-etapa

## Resumo

Implementar cursor-based pagination por coluna/etapa no Kanban de negociações para suportar alto volume (5.000+ deals por funil) sem degradar performance.

---

## Entrega 1 — Backend: Paginação Cursor por Etapa

### TASK-027.1.1 — Migration + Action paginada por step

**Agente:** @BACKEND | **Esforço:** XS | **Status:** done

**Objetivo:** Criar índice otimizado e a Action de paginação por step.

**Arquivos:**

- **Criar:** `api/database/migrations/2026_03_31_000001_add_kanban_cursor_index_crm_negotiations.php`
    ```sql
    CREATE INDEX idx_crm_negotiations_step_cursor
    ON crm_negotiations (tenant_id, crm_negotiation_funnel_id, crm_negotiation_funnel_step_id, status, position ASC, id ASC)
    WHERE deleted_at IS NULL;
    ```
- **Criar:** `api/src/Domain/CRM/Actions/ListCRMNegotiationsByStepAction.php`
    - Recebe: `tenantId`, `funnelId`, `stepId`, `cursor` (nullable), `perPage` (default 20), `filters`
    - Query com cursor composto `(position, id)`:
        ```php
        $query->where('crm_negotiation_funnel_step_id', $stepId)
              ->when($cursor, fn($q) => $q->where(fn($sub) =>
                  $sub->where('position', '>', $cursor['position'])
                      ->orWhere(fn($inner) => $inner->where('position', $cursor['position'])->where('id', '>', $cursor['id']))
              ))
              ->orderBy('position')
              ->orderBy('id')
              ->limit($perPage + 1);
        ```
    - Retorna: `['negotiations' => [...], 'has_more' => bool, 'next_cursor' => string|null]`

**Critérios de aceite:**

- [ ] Índice criado e testado via EXPLAIN ANALYZE
- [ ] Cursor decode/encode via base64 JSON
- [ ] Filtros reaproveitam `CRMNegotiationFilterService::apply()`
- [ ] `has_more` calculado via `count > perPage`, retorna apenas `perPage` itens

---

### TASK-027.1.2 — Refatorar kanban() para aggregates + primeira página

**Agente:** @BACKEND | **Esforço:** S | **Status:** done | **Paralelo com:** TASK-027.1.1

**Objetivo:** O endpoint principal do kanban (`ListCRMNegotiationsAction::kanban()`) deve retornar aggregates (count, sum) + apenas os primeiros N cards por step, em vez de carregar tudo.

**Arquivos:**

- **Modificar:** `api/src/Domain/CRM/Actions/ListCRMNegotiationsAction.php`
    - Substituir `$query->get()` + `groupBy` por:
        1. Query de aggregates por step: `SELECT crm_negotiation_funnel_step_id, COUNT(*) as total_count, SUM(amount) as total_value FROM crm_negotiations WHERE ... GROUP BY crm_negotiation_funnel_step_id`
        2. Para cada step: buscar primeiros 20 itens via `ListCRMNegotiationsByStepAction`
    - Cada step retorna: `total_count`, `total_value`, `has_more`, `next_cursor`, `negotiations[]`

**Critérios de aceite:**

- [ ] Endpoint kanban retorna mesma estrutura + campos novos (`total_count`, `total_value`, `has_more`, `next_cursor`)
- [ ] N+1 queries eliminado — usa 1 query aggregate + N queries (1 por step) com LIMIT
- [ ] Retro-compatível — campos existentes mantidos

---

### TASK-027.1.3 — Rota, Request, Controller + Testes

**Agente:** @BACKEND | **Esforço:** S | **Status:** done | **Após:** TASK-027.1.1

**Objetivo:** Expor novo endpoint e escrever testes.

**Arquivos:**

- **Criar:** `api/src/Domain/CRM/Http/Requests/CRMNegotiationKanbanStepRequest.php`
    - Valida: `funnel_id` (required uuid), `cursor` (nullable string), `per_page` (int, max:50, default:20), filtros opcionais
- **Modificar:** `api/src/Domain/CRM/Http/Controllers/CrmNegotiationController.php`
    - Novo método: `kanbanStep(CRMNegotiationKanbanStepRequest $request, string $stepId)`
- **Modificar:** `api/src/Domain/CRM/Routes/crm.php`
    - Nova rota: `GET /crm/negotiations-kanban/step/{stepId}`
- **Criar:** `api/tests/Feature/CRMNegotiationKanbanPaginationTest.php`
    - Cenários: 0 items, < perPage, > perPage, cursor navigation, filtros, tenant isolation

**Critérios de aceite:**

- [ ] Endpoint retorna 200 com paginação correta
- [ ] Cursor permite navegar todas as páginas sem duplicatas
- [ ] Filtros aplicados corretamente
- [ ] Tenant isolation verificada no teste
- [ ] `per_page` limitado a 50 no request
- [ ] `composer gate:all` green

---

## Entrega 2 — Frontend: Infinite Scroll por Coluna

### TASK-027.2.1 — Service + Interfaces

**Agente:** @FRONTEND | **Esforço:** XS | **Status:** done | **Após:** TASK-027.1.3

**Objetivo:** Adicionar suporte ao novo endpoint e atualizar interfaces.

**Arquivos:**

- **Modificar:** `app/src/app/core/services/negotiation.service.ts`
    - Novo método: `kanbanStep(stepId, funnelId, cursor?, filters?): Observable<KanbanStepPage>`
    - Atualizar interface `NegotiationKanbanStep`: adicionar `total_count`, `total_value`, `has_more`, `next_cursor`
    - Nova interface `KanbanStepPage`: `{ negotiations: Negotiation[], has_more: boolean, next_cursor: string | null }`

**Critérios de aceite:**

- [ ] Interfaces tipadas corretamente (sem `any`)
- [ ] Método respeita padrão do service existente

---

### TASK-027.2.2 — Refatorar negotiations.ts com infinite scroll

**Agente:** @FRONTEND | **Esforço:** S | **Status:** done | **Após:** TASK-027.2.1

**Skills obrigatórios:** `design/SKILL.md`, `frontend-flow/SKILL.md`, `angular-architect/SKILL.md`, `coding-guidelines/SKILL.md`

**Objetivo:** Implementar carregamento incremental por coluna.

**Arquivos:**

- **Modificar:** `app/src/app/pages/crm/negotiations/negotiations.ts`
    - Novo signal: `kanbanStepLoading = signal<Record<string, boolean>>({})` — loading per column
    - Novo método: `loadMoreStep(step: NegotiationKanbanStep)` — carrega próxima página e append ao signal
    - Refatorar `loadKanban()` — inicializa com dados do endpoint principal (que já vem com primeira página)
    - Refatorar reload pós-move: recarregar apenas source + destination steps, não o kanban inteiro
    - Usar `takeUntilDestroyed` em todas subscriptions

**Critérios de aceite:**

- [ ] `loadMoreStep()` appends items corretamente ao signal sem duplicar
- [ ] Loading indicator exibido por coluna durante fetch
- [ ] Após move, apenas 2 colunas afetadas são recarregadas
- [ ] `OnPush` mantido
- [ ] Sem memory leak

---

### TASK-027.2.3 — Template HTML + Loading + Testes

**Agente:** @FRONTEND | **Esforço:** S | **Status:** done | **Após:** TASK-027.2.2

**Objetivo:** Adicionar UI de infinite scroll e testes.

**Arquivos:**

- **Modificar:** `app/src/app/pages/crm/negotiations/negotiations.html`
    - Dentro de cada coluna, após o `@for` de negotiations:
        ```html
        @if (step.has_more) {
        <div class="flex justify-center py-3">
            <button
                (click)="loadMoreStep(step)"
                [disabled]="isStepLoading(step.id)"
                class="text-sm text-primary-500 hover:text-primary-600"
            >
                @if (isStepLoading(step.id)) {
                <span class="animate-spin">⟳</span> Carregando... } @else { Carregar mais ({{ step.total_count -
                (step.negotiations?.length || 0) }} restantes) }
            </button>
        </div>
        }
        ```
    - Atualizar header da coluna: exibir `step.total_count` e `step.total_value` formatados
- **Criar (opcional):** `app/src/app/pages/crm/negotiations/negotiations.spec.ts`

**Critérios de aceite:**

- [ ] Botão "Carregar mais" visível apenas quando `has_more === true`
- [ ] Loading state por coluna funcional
- [ ] Contadores exibidos no header da coluna
- [ ] `pnpm run gate:all` green
- [ ] Visual consistente com design system

---

## Resumo de Execução

| Fase | Tasks                       | Agente    | Dependência |
| ---- | --------------------------- | --------- | ----------- |
| 1    | TASK-027.1.1 + TASK-027.1.2 | @BACKEND  | Paralelo    |
| 2    | TASK-027.1.3                | @BACKEND  | Após fase 1 |
| 3    | TASK-027.2.1                | @FRONTEND | Após fase 2 |
| 4    | TASK-027.2.2 → TASK-027.2.3 | @FRONTEND | Sequencial  |
