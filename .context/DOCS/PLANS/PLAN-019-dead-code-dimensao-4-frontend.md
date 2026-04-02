# PLAN-019 — Dead Code Dimensão 4 Frontend (Sprint 4 Audit)

## Objetivo

Executar os findings da Dimensão 4 (Dead Code — LOW) do `AUDIT-FRONTEND-001`, removendo código
morto confirmado e documentando os falsos positivos identificados pela análise dirigida. O escopo
real é significativamente menor do que o estimado no audit original após evidências grep.

## Módulo relacionado

**Frontend** — Angular 20 / TypeScript 5.9 (`app/src/`)

## PRD relacionado: N/A

## Escopo

### Incluído

- Remover o componente órfão `WelcomeComponent` (`pages/welcome/welcome.ts`) confirmado como
  placeholder de Fase 1 sem rota ativa.
- Remover o `export` público desnecessário da interface `RoleFilters` em
  `core/services/role.service.ts` sem consumidores externos confirmados.
- Documentar e fechar os falsos positivos no `AUDIT-FRONTEND-001` com evidências.
- Verificar e documentar se há outros `export default` em componentes que nunca aparecem em
  `app.routes.ts` ou são importados em outros arquivos.

### Excluído

- `currency.pipe.ts` — ativo (4 consumidores confirmados via grep)
- `mask.directive.ts` — ativo (importado em `masked-input.ts`)
- `styles.css` — classes `.card`, `.card-body`, `.form-input`, `.form-input-sm` com uso confirmado
- `shared/models/*.ts` — todos os modelos estão sendo importados em serviços e componentes
- `TwoFactorStatusResponse` / `TwoFactorSetupResponse` — importados em `two-factor.ts`
- `FileFilter` em `file-system.service.ts` — tipo de parâmetro público válido
- Routes — nenhuma rota órfã confirmada (`app.routes.ts` + sub-routes todos válidos)
- Mudanças de contrato backend, gateway ou banco
- Refactors além dos findings documentados

## Análise de Evidências (grep direto)

| Finding        | Status Confirmado        | Evidência                                                                                   |
| -------------- | ------------------------ | ------------------------------------------------------------------------------------------- |
| FE-DEAD-001    | ⚠️ PARCIAL (1 real)      | 43 de 44 `export default` linkados em `app.routes.ts`; 1 órfão: `WelcomeComponent`         |
| FE-DEAD-002    | ⚠️ PARCIAL (1 real)      | `RoleFilters` sem consumidor externo; `TwoFactorXxx` usados em `two-factor.ts` (falso pos.) |
| FE-DEAD-003    | 🚫 FALSO POSITIVO        | `currency.pipe.ts` importado em 4 arquivos via `@shared/pipes/currency.pipe`                |
| FE-DEAD-004    | 🚫 FALSO POSITIVO        | `mask.directive.ts` importado em `masked-input.ts`                                          |
| FE-DEAD-005    | 🚫 FALSO POSITIVO        | Todos os modelos confirmados em uso (incluindo `tenant-details.model.ts`)                   |
| FE-DEAD-006    | 🚫 FALSO POSITIVO        | `.card` (13 ocorrências), `.form-input` (18 ocorrências) confirmados em uso                 |
| FE-DEAD-007    | 🚫 FALSO POSITIVO        | Nenhuma rota órfã; todas validadas em app.routes.ts                                         |

## Etapas propostas

1. Remover pasta `app/src/app/pages/welcome/` completa (componente + spec se existir).
2. Remover `export` de `RoleFilters` em `role.service.ts` (torná-la `interface RoleFilters`
   privada ao arquivo—ou mover para dentro do método como type inline, dado que é simples).
3. Atualizar `AUDIT-FRONTEND-001.md` documentando os falsos positivos de FE-DEAD-003 a
   FE-DEAD-007 e o status de FE-DEAD-001 e FE-DEAD-002 após correção.
4. Executar `pnpm run gate:all` para confirmar que nenhuma remoção quebrou imports.
5. QA + REVIEW.

## Tasks derivadas

| Task     | Descrição                                                           | Agente   | Status |
| -------- | ------------------------------------------------------------------- | -------- | ------ |
| TASK-025 | Remover WelcomeComponent órfão e corrigir RoleFilters export        | FRONTEND | todo   |
| TASK-026 | Atualizar AUDIT-FRONTEND-001 com falsos positivos da Dimensão 4     | DOC      | todo   |

## Riscos e dependências

### Riscos

| Risco                                                 | Probabilidade | Mitigação                                                               |
| ----------------------------------------------------- | ------------- | ----------------------------------------------------------------------- |
| `WelcomeComponent` referenciado em teste não detectado | Baixo         | Checar `welcome.spec.ts` antes de deletar; grep confirma não há imports |
| `RoleFilters` importado em arquivo não indexado       | Muito baixo   | grep em todo `app/src` confirmou apenas uso interno                     |

### Dependências

- Nenhuma dependência de outras tasks em progresso
- Pode ser executado em paralelo com demais tasks da Dimensão 3

## Estimativa

**Complexidade real:** LOW (muito abaixo do estimado no audit original)
- Remoção de 1 componente orphan (~25 linhas)
- Remoção de 1 keyword `export` (~1 linha)
- Atualização de documentação do audit

**Esforço estimado:** 2–3 horas (vs. 4 dias originais do Sprint 4)

## Notas

O Sprint 4 original estimava 30 LOW items em 4 dias. A análise confirma que 25+ dos findings
são falsos positivos frente ao codebase atual. O escopo real reduz a ~2 correções cirúrgicas
+ documentação, refletindo evolução do codebase desde o audit inicial.
