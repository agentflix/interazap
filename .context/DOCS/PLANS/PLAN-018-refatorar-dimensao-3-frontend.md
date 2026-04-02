# PLAN-018-refatorar-dimensao-3-frontend — Executar a Dimensão 3 do audit frontend

## Objetivo

Executar os findings da Dimensão 3 do `AUDIT-FRONTEND-001`, reduzindo componentes god-class, removendo duplicação transversal, padronizando blocos repetidos e concluindo a migração Angular 20 sem alterar contratos backend/gateway e com validação incremental por bloco.

## Módulo relacionado

**Frontend** — Angular 20 / TypeScript 5.9 (`app/src/`)

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Extrair utilities compartilhadas para eliminar duplicações de `getInitials()`, `formatDate()` e formatadores equivalentes.
- Padronizar os componentes duplicados de `reports/*` em uma base reutilizável.
- Refatorar os componentes médios da área de AI (`simulator.ts` e `knowledge-list.ts`).
- Refatorar os componentes médios das áreas de Chat e CRM (`app/src/app/pages/chat/store/chat.store.ts`, `chatbot.ts`, `chat-message-media.component.ts`, `negotiations.ts`, `negotiation-show.ts`, `crm-contacts.ts`).
- Corrigir o gap de validação do formulário de cartão em billing (`FE-BILLING-001`) com escopo explícito e sem redesign da tela.
- Executar a migração Angular 20 restante da Dimensão 3 (`@if/@for`, `track`, `OnPush`) após estabilizar os refactors de domínio.
- Produzir tasks derivadas por bloco com gates, QA e REVIEWER independentes.
- Atualizar a documentação do audit conforme cada bloco for sendo saneado.

### Excluído

- Findings CRITICAL já tratados em planos separados, incluindo `PLAN-016-refatorar-uazapi-instances`.
- Findings da Dimensão 2 já cobertos por `PLAN-017-corrigir-memory-leaks-dimensao-2-frontend`.
- Mudanças de contrato backend, gateway ou banco.
- Redesign visual amplo das telas afetadas.
- Refactors por preferência fora dos findings documentados no audit.

## Etapas propostas

1. Executar o bloco Shared, eliminando duplicações transversais antes de tocar módulos dependentes.
2. Refatorar `reports/*` para consumir a base compartilhada e reduzir repetição estrutural.
3. Refatorar o bloco AI com foco em decomposição segura de responsabilidades.
4. Refatorar o bloco Chat/Negociações somente após `TASKS-006` e `TASKS-017` estarem com status `done`; em seguida, executar o bloco CRM.
5. Corrigir o gap de validação de billing listado na Dimensão 3 em task dedicada, sem misturar a mudança com os refactors estruturais.
6. Aplicar a migração Angular 20 remanescente somente após estabilizar os componentes extraídos.
7. Validar cada bloco com testes direcionados, `pnpm run gate:all`, QA e REVIEWER antes de avançar.
8. Atualizar o `AUDIT-FRONTEND-001` e os artefatos de contexto ao final de cada entrega.

## Tasks derivadas

| Task     | Descrição                                                    | Agente   | Status      |
| -------- | ------------------------------------------------------------ | -------- | ----------- |
| TASK-018 | Bloco D — Extrair shared utilities e formatadores duplicados | FRONTEND | done        |
| TASK-019 | Bloco E — Padronizar componentes duplicados de reports       | FRONTEND | in_progress |
| TASK-020 | Bloco B — Refatorar componentes médios de AI                 | FRONTEND | done        |
| TASK-021 | Bloco A — Refatorar Chat, negociação e mídia                 | FRONTEND | done        |
| TASK-022 | Bloco C — Refatorar CRM Contacts                             | FRONTEND | done        |
| TASK-023 | Bloco F — Concluir migração Angular 20 da Dimensão 3         | FRONTEND | done        |
| TASK-024 | Bloco G — Corrigir validação do cartão em billing            | FRONTEND | done        |

## Riscos e dependências

### Riscos

| Risco                                                                                | Probabilidade | Impacto | Mitigação                                                                                      |
| ------------------------------------------------------------------------------------ | ------------- | ------- | ---------------------------------------------------------------------------------------------- |
| Refactor transversal de utilities introduzir regressão silenciosa em múltiplas telas | Média         | Alta    | Começar por funções puras, cobrir com specs direcionadas e validar consumer por consumer       |
| Refatoração de Chat conflitar com trabalho em andamento de memory leaks e filtros    | Alta          | Alta    | Sequenciar após blocos Shared/Reports/AI e respeitar dependências de `TASKS-006` e `TASKS-017` |
| Migração Angular 20 gerar ruído excessivo e mascarar regressões reais                | Média         | Média   | Executar por último, após estabilizar a estrutura dos componentes                              |
| Escopo agregado da Dimensão 3 comprometer rastreabilidade de QA/review               | Média         | Alta    | Validar e registrar evidências por bloco, não como diff único                                  |

### Dependências

- `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
- `.context/DOCS/TASKS/TASKS-006.md`
- `.context/DOCS/TASKS/TASKS-017.md`
- `AGENTS.md`
- Shared components existentes em `app/src/app/shared/components/`
- `TASK-021` só pode iniciar após `TASKS-006` e `TASKS-017` concluídas
- Módulos afetados: Ai, Chat, CRM, Reports, Shared, Layout

## Estimativa

| Item                          | Valor    |
| ----------------------------- | -------- |
| Complexidade                  | Alta     |
| Camadas afetadas              | Frontend |
| Migrações necessárias         | Não      |
| Impacto em módulos existentes | Sim      |
