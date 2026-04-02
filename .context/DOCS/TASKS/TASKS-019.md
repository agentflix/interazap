# TASKS-019 — Sprint 4: Dead Code Dimensão 4 Frontend

---

# TASK-025 — Remover WelcomeComponent órfão e corrigir RoleFilters export

## Status: done

## Plano origem: PLAN-019-dead-code-dimensao-4-frontend

## PRD relacionado: N/A

## Agente responsável: FRONTEND

## Goal

Remover o componente `WelcomeComponent` confirmaemente órfão (placeholder de Fase 1 sem rota ativa)
e corrigir o leaking de `export` da interface `RoleFilters` em `role.service.ts`, que não tem
consumidores externos confirmados, tornando-a privada ao módulo.

## Constraints

- Seguir Angular 20 com tipagem explícita; sem introduzir `any`
- Não alterar contratos HTTP nem comportamento funcional
- Preservar todos os imports existentes em outros arquivos (nenhum consumidor encontrado)
- `RoleFilters` deve continuar utilizável internamente no service (apenas remover `export`)
- Não alterar arquivos fora do escopo desta task

## Context

- Módulos afetados: `pages/welcome/`, `core/services/role.service.ts`
- Dependências: nenhuma
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md` (FE-DEAD-001, FE-DEAD-002)
    - `.context/DOCS/PLANS/PLAN-019-dead-code-dimensao-4-frontend.md`
    - `AGENTS.md`

## Etapas

- [x] Confirmar via grep que `welcome.ts` e `WelcomeComponent` não são importados em nenhum arquivo
      antes de deletar. ✅ grep retornou 0 matches
- [x] Deletar `app/src/app/pages/welcome/` (componente + spec se existir). ✅ pasta removida
- [x] Confirmar via grep que `RoleFilters` não é importado fora de `role.service.ts`. ✅ apenas linhas 7 e 43 no próprio service
- [x] Remover o keyword `export` da declaração da interface `RoleFilters` em
      `app/src/app/core/services/role.service.ts`. ✅ `export interface` → `interface`
- [x] Executar lint para garantir que nenhuma remoção quebrou imports. ✅ 0 erros, 1 warning pré-existente (checkbox-group.ts)
- [x] Verificar saída do lint/build sem novos erros relacionados ao escopo. ✅ confirmado

## Critérios de conclusão

- [x] Código removido conforme escopo
- [x] Gate lint passa sem novos erros no escopo (0 erros)
- [x] QA review sem issues críticos
- [x] Code review aprovado
- [x] TASK-026 notificada para atualizar o audit

## Evidências de conclusão

- **Data:** 2026-03-29
- **Agente:** FRONTEND
- **WelcomeComponent:** `app/src/app/pages/welcome/` deletada (25 linhas removidas)
- **RoleFilters:** `export interface RoleFilters` → `interface RoleFilters` em `role.service.ts:7`
- **Lint:** `✖ 1 problem (0 errors, 1 warning)` — warning pré-existente em `checkbox-group.ts`, escopo não afetado

---

# TASK-026 — Atualizar AUDIT-FRONTEND-001 com falsos positivos da Dimensão 4

## Status: done

## Plano origem: PLAN-019-dead-code-dimensao-4-frontend

## PRD relacionado: N/A

## Agente responsável: DOC

## Goal

Atualizar `AUDIT-FRONTEND-001.md` para refletir o estado real da Dimensão 4 após execução do
Sprint 4: fechar findings resolvidos, marcar falsos positivos com evidências, e atualizar o
roadmap de correção.

## Constraints

- Não alterar findings das Dimensões 1, 2 ou 3
- Preservar rastreabilidade (manter IDs FE-DEAD-001..007 intact)
- Documentar evidências grep concretas nos falsos positivos

## Context

- Módulos afetados: apenas documentação
- Dependências: TASK-025 concluída (para marcar FE-DEAD-001 e FE-DEAD-002 como resolvidos)
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PLANS/PLAN-019-dead-code-dimensao-4-frontend.md`

## Etapas

- [x] Atualizar seção "Dimensão 4 — Dead Code" do audit com status dos finding após TASK-025
- [x] Marcar FE-DEAD-003 a FE-DEAD-007 como `🚫 FALSO POSITIVO` com evidências
- [x] Marcar FE-DEAD-001 e FE-DEAD-002 como `✅ CORRIGIDO em TASK-025` após execução
- [x] Atualizar métricas finais do audit

## Critérios de conclusão

- [x] Audit atualizado com status correto de todos os findings da Dimensão 4
- [x] Falsos positivos documentados com evidências
- [x] Findings resolvidos marcados como CORRIGIDO

## Evidências de conclusão

- **Data:** 2026-03-29
- **Agente:** DOC
- **Alterações:** Nota de atualização TASKS-019 inserida na Dimensão 4; coluna `Status` adicionada à tabela FE-DEAD-001..007 com evidências grep por falso positivo.
