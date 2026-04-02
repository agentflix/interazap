# TASKS-{numero-do-plano} — [Título]

**Entregas:** N | **Tasks:** N

| Entrega | Descrição | Tasks | Status |
|---------|-----------|-------|--------|
| 1 | | TASK-{plan}.1.1 - TASK-{plan}.1.N | todo |
| 2 | | TASK-{plan}.2.1 - TASK-{plan}.2.N | todo |
| N | | TASK-{plan}.N.1 - TASK-{plan}.N.N | todo |

---

## Entrega {n} — {descrição} ✅ testável

**Entrega:** {o que será entregue} | **Agente:** @AGENTE

**Gate:** Todos os critérios devem passar antes de marcar esta entrega como `done`.

### TASK-{plan}.{entrega}.{seq} — {título atômico}

**Status:** todo

**Plano origem:** PLAN-{000}-{nome-em-letra-minuscula}

**PRD relacionado:** PRD-[MODULO]-[NUMERO] (se existir)

**Goal**

<!-- Descreva o objetivo de forma clara e concisa. O que deve ser entregue? -->

**Constraints**

<!-- Liste restrições técnicas, de escopo ou de negócio. -->

- Seguir DDD: Controller → DTO → Action → Resource
- Tenant isolation obrigatório
- Nenhum `any` / `$guarded = []` / auto-increment

**Context**

<!-- Contexto relevante para quem for executar. Links para PRD, outras tasks, módulos afetados. -->

- Módulos afetados:
- Dependências:

**Context References**

<!-- Para cada referência externa, escolha: embed (curta) ou declare required. -->

- Referências: `{path}` _(embedded above | required in context)_

**Code Context** *(only if modifying existing code)*

<details>
<summary>Current → Expected</summary>

```php
// Current code (problem)
```

```php
// Expected code (solution)
```
</details>

**Etapas**

<!-- Checklist de implementação. Cada etapa = verbo + caminho/arquivo. -->

- [ ] 1. {verbo} {caminho/arquivo}
- [ ] 2. {verbo} {caminho/arquivo}
- [ ] 3. Verificar {gate: build, test, lint}

**Critérios de conclusão**

<!-- Cada critério deve mapear para um nome de teste verificável. -->

- [ ] {critério verificável 1}
      -> `test_<expected_behavior>`
- [ ] {critério verificável 2}
      -> `test_<expected_behavior>`

**Evidências**

<!-- Preenchido na fase Confirm. -->

- Gates: `composer gate:all` / `pnpm run gate:all` — resultado
- Review: [REVIEWER link ou aprovação]
- Commit: {sha}

---

### TASK-{plan}.{entrega}.{seq+1} — {título atômico}

**Status:** todo

... (repetir estrutura acima para cada task)

---

## Notas

- `TASK-{plan}.{entrega}.{seq}` — primeiro número = plano origem, não sequencial global
- Cada entrega = uma entrega verificável (build passa, 0 imports relativos, etc.)
- Critérios da entrega = gate para avançar para próxima entrega
- Para audit findings: usar template específico em `.context/WORKFLOW/task-template.md` (seção Audit finding template)
