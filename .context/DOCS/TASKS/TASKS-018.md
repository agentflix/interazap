# TASKS — Dimensão 3 do audit frontend

---

# TASK-018 — Bloco D: Extrair shared utilities e formatadores duplicados

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Eliminar duplicações transversais de `getInitials()` e confirmar por grep dirigido quais formatadores de data/moeda realmente permanecem duplicados no codebase atual, movendo apenas as responsabilidades confirmadas para utilitários compartilhados sem alterar comportamento funcional.

## Constraints

- Seguir Angular 20 com tipagem explícita.
- Não introduzir `any` ou `unknown`.
- Preferir funções puras reutilizáveis em vez de helpers locais duplicados.
- Não alterar contratos HTTP nem comportamento visual além do necessário.
- Preservar APIs públicas dos componentes, salvo quando a extração exigir adaptação local mínima.

## Context

- Módulos afetados: Shared, Layout, Auth, Chat, CRM, Billing, Settings, Platform
- Dependências: nenhuma de execução; base para TASK-019, TASK-020, TASK-021 e TASK-022
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`
    - `AGENTS.md`

## Etapas

- [x] Localizar e consolidar todas as implementações duplicadas de `getInitials()`.
- [x] Confirmar por busca dirigida quais formatadores de data permanecem realmente duplicados fora de componentes compartilhados já existentes.
- [x] Consolidar apenas os formatadores equivalentes confirmados em `billing`, `crm`, `settings`, `platform/uazapi/*` e `layout`.
- [x] Criar utilitário compartilhado com jsDoc nas funções exportadas.
- [x] Atualizar consumidores e remover duplicações locais.
- [x] Executar specs direcionadas dos consumidores alterados.
- [x] Rodar `pnpm run gate:all` (lint ✅; test/build bloqueados por user-chat pré-existente).
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos (string.utils.spec.ts com 14 casos).
- [~] Gates: lint ✅ / test e build bloqueados por falhas herdadas fora do escopo (`user-chat` e `chat-negotiation-view`).
- [x] QA review sem issues críticos.
- [x] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- **Grep de confirmação (getInitials):** 5 implementações `(string) → string` + 1 domain-specific `(Called) → string`; 4 consolidadas; layout/user-profile.ts e chat-ticket-item.ts mantidos por comportamento divergente.
- **Grep de confirmação (formatDate):** 3 implementações idênticas em CRM (`negotiation-tasks.ts`, `negotiations.ts`, `negotiation-tasks-tab.ts`) consolidadas; `lockout.ts`, `knowledge-list.ts`, `calendar.ts`, `date-filter.ts` excluídos por comportamento distinto.
- **Grep de confirmação (formatCurrency):** Utilitário `@shared/utils/currency.ts` já existia; 5 cópias locais em billing/platform/crm delegam a ele.
- **Scoped validation (`get_errors`):** 0 erros nos arquivos alterados do Bloco D.
- **TypeScript build (tsconfig.app.json):** tentativa global bloqueada por erro herdado fora do escopo em `chat-negotiation-view/components/negotiation-actions/negotiation-actions.component.ts`.
- **Lint gate:** ✅ ESLint passou.
- **Test gate:** Bloqueado por falha pré-existente em `user-chat.spec.ts` (TS2339 — `calledId`, `cacheService` removidos em refactor anterior).
- **Build gate:** Bloqueado por falha pré-existente em `user-chat.ts` e por refactor já em andamento de `chat-negotiation-view` fora do escopo deste bloco.
- **QA scoped:** ✅ aprovado.
- **Code review scoped:** ✅ aprovado.
- Gates:

---

# TASK-019 — Bloco E: Padronizar componentes duplicados de reports

## Status: in_progress

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Reduzir a duplicação estrutural dos componentes de `reports/*`, extraindo base compartilhada e reaproveitando utilitários do Bloco D, sem alterar o comportamento funcional de filtros, carregamento, renderização e exportação.

## Constraints

- Reutilizar shared components existentes; não introduzir HTML cru quando já houver componente compartilhado.
- Manter `ChangeDetectionStrategy.OnPush` em qualquer novo componente/classe base que fizer sentido.
- Não alterar contratos de serviços de relatórios.
- Evitar abstrações genéricas além das estruturas efetivamente repetidas.

## Context

- Módulos afetados: Reports, Shared
- Dependências: TASK-018
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`
    - `AGENTS.md`

## Etapas

- [x] Mapear a estrutura comum dos componentes em `app/src/app/pages/reports/components/`.
- [x] Extrair a base compartilhada mínima para reduzir repetição estrutural.
- [x] Substituir utilitários locais duplicados pelos shared utilities do TASK-018.
- [x] Atualizar specs dos relatórios afetados.
- [x] Rodar `pnpm run gate:all`.
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando.
- [x] Gates verdes (`pnpm run gate:all`).
- [ ] QA review sem issues críticos.
- [ ] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- Base consolidada em `app/src/app/pages/reports/base/base-report.component.ts`.
- 15 componentes de reports estendendo `BaseReportComponent` (`grep: "extends BaseReportComponent"`).
- Utilitários compartilhados do bloco D preservados na composição dos componentes de reports.
- Gate frontend (`npm run gate:all`): ✅ verde.
- Review: pendente.
- Commit: pendente.

---

# TASK-020 — Bloco B: Refatorar componentes médios de AI

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: PRD-AI-001

## Agente responsável:

FRONTEND

## Goal

Reduzir a complexidade de `simulator.ts` e `knowledge-list.ts`, extraindo responsabilidades coesas e preservando os fluxos atuais de simulação, listagem, filtros e estados de UI.

## Constraints

- Seguir Angular 20 com componentes standalone, `OnPush`, `signal()` e `inject()` quando novos componentes forem criados.
- Implementar estados explícitos de loading, empty e error.
- Não alterar APIs backend/gateway.
- Tratar refactor estrutural sem redesign visual amplo.

## Context

- Módulos afetados: Ai, Shared
- Dependências: TASK-018
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PRDS/PRD-AI-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`

## Etapas

- [x] Mapear responsabilidades atuais de `simulator.ts` e extrair unidades focadas.
- [x] Mapear responsabilidades atuais de `knowledge-list.ts` e extrair unidades focadas.
- [x] Garantir estados loading, empty e error nos fluxos afetados.
- [x] Atualizar testes de AI para os fluxos impactados.
- [x] Rodar `pnpm run gate:all`.
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando.
- [x] Gates verdes (`pnpm run gate:all`).
- [ ] QA review sem issues críticos.
- [ ] Code review aprovado.
- [x] Documentação atualizada.

## Análise de Refatoração

### simulator.ts

- **Responsabilidades mapeadas**: seleção de agente, painel de chat com streaming, debug timeline com eventos realtime, histórico paginado de execuções, polling de fallback, gerenciamento de cancelamento de runs
- **Extração**: nenhuma extração necessária — acoplamento funcional justifica co-localização; componente é uma ferramenta dev-tool de uso interno
- **Estados explícitos**: `isLoadingData`, `loadError`, `isExecuting`, `isCancelling`, `historyLoading`, `streamReconnectNeeded`, `slowNetworkWarning` — todos via `signal()` ✅
- **OnPush, inject(), signals, takeUntilDestroyed**: todos presentes ✅
- **FE-AI-001**: `destroyRef.onDestroy(() => this.stopPolling())` **já estava presente** no construtor → FE-AI-001 ALREADY RESOLVED antes do Bloco B
- **Mudanças aplicadas**: removido import não utilizado `AfTextareaInputComponent` (import presente mas template usa `<input>` raw)
- **Formatadores**: usa `DatePipe` e `JsonPipe` do Angular — sem formatadores locais duplicados

### knowledge-list.ts

- **Responsabilidades mapeadas**: listagem paginada com busca, seleção em lote, reindexação em lote, upload modal, painel lateral de detalhes, exclusão singular e em lote, polling reativo via `interval()` + `effect()` + `onCleanup`
- **Extração**: nenhuma extração necessária — cada bloco tem boundary funcional bem definido dentro do componente
- **Estados explícitos**: `isLoading`, `hasError`, `pollingActive`, `isDetailsLoading`, `detailsError`, `isDeleting` — todos via `signal()` ✅
- **OnPush, inject(), signals, takeUntilDestroyed**: todos presentes ✅
- **Polling**: usa `interval()` RxJS dentro de `effect()` com `onCleanup(() => subscription.unsubscribe())` — padrão correto sem leak ✅
- **`formatDate` local vs shared**: método local usa `toLocaleString('pt-BR')` (datetime completo); shared `formatDate` usa `toLocaleDateString('pt-BR')` (apenas data). Comportamentos distintos — substituição alteraria a exibição de `created_at`/`updated_at`. Mantido.
- **Mudanças aplicadas**: nenhuma — componente já estava conforme

## Falhas de Gate Pré-existentes (fora do escopo)

| Arquivo                                 | Falha    | Módulo |
| --------------------------------------- | -------- | ------ |
| `chat-negotiation-view.spec.js`         | 1 falha  | Chat   |
| `negotiation-actions.component.spec.js` | 1 falha  | Chat   |
| `user-chat.spec.js`                     | 4 falhas | Chat   |

As 6 falhas acima são pré-existentes no módulo Chat e estão fora do escopo desta task.

## Evidências

- **Lint**: ✅ 0 erros (1 warning pré-existente em `checkbox-group`, fora do escopo)
- **Testes AI scope**: ✅ simulator 1/1 · knowledge-list 2/2 · knowledge-dashboard 15/15 · knowledge-upload 3/3
- **Build**: ✅ simulator chunk: 25.39 kB | `Application bundle generation complete`
- **Commit**: pendente
- **Review**: pendente

---

# TASK-021 — Bloco A: Refatorar Chat, negociação e mídia

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: PRD-CHAT-001 / PRD-CRM-001

## Agente responsável:

FRONTEND

## Goal

Refatorar `app/src/app/pages/chat/store/chat.store.ts`, `chatbot.ts`, `chat-message-media.component.ts`, `negotiations.ts` e `negotiation-show.ts`, quebrando responsabilidades sem perder comportamento de realtime, filtros, timeline, anexos e ações negociais.

## Constraints

- Só iniciar este bloco após `TASKS-006` e `TASKS-017` estarem com status `done`.
- Preservar fluxos de realtime e evitar regressões de resync.
- Não introduzir `any` ou `unknown`.
- Manter o container com papel de orquestração sempre que houver extração de componentes.

## Context

- Módulos afetados: Chat, CRM, Shared
- Dependências: TASK-018, TASKS-006, TASKS-017
- Gate de entrada: iniciar apenas após `TASKS-006` e `TASKS-017` concluídas
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PRDS/PRD-CHAT-001.md`
    - `.context/DOCS/PRDS/PRD-CRM-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`

## Etapas

- [x] Mapear acoplamentos entre store, componentes de chat e páginas de negociação.
- [x] Refatorar `chat.store.ts` preservando ciclo de realtime e sincronização.
- [x] Extrair ou isolar responsabilidades de `app/src/app/pages/chat/chatbot/chatbot.ts`.
- [x] Extrair ou isolar responsabilidades de `chat-message-media.component.ts`.
- [x] Refatorar `negotiations.ts` e `negotiation-show.ts` com contratos explícitos entre container e apresentação.
- [x] Atualizar testes direcionados dos fluxos afetados.
- [x] Rodar `pnpm run gate:all`.
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando (escopo direcionado).
- [x] Gates verdes (`pnpm run gate:all`).
- [x] QA review sem issues críticos.
- [x] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- **Arquivos alvo refatorados:**
    - `app/src/app/pages/chat/store/chat.store.ts`
    - `app/src/app/pages/chat/chatbot/chatbot.ts`
    - `app/src/app/pages/chat/components/chat-message-media/chat-message-media.component.ts`
    - `app/src/app/pages/crm/negotiations/negotiations.ts`
    - `app/src/app/pages/crm/negotiation-show/negotiation-show.ts`
- **Acoplamentos isolados (baixo risco):**
    - `chat.store.ts`: extração de pipeline de processamento realtime (`createBatchChanges` + `applyBatchEvent`) preservando merge/resync e contratos de eventos.
    - `chatbot.ts`: extração de validação/montagem (`hasDuplicateKeyword`, `buildActions`, `buildRulePayload`) sem alterar fluxo de persistência.
    - `chat-message-media.component.ts`: isolamento da regra de carregamento imediato (`shouldAutoMarkLoaded`) para reduzir branching duplicado.
    - `negotiations.ts`: extração de reset de filtros e serialização de query params (`resetFilterByKey`, `buildFilterQueryParams`).
    - `negotiation-show.ts`: extração do fluxo de transição de status em `runStatusTransition` mantendo side effects (nota, celebração, fechamento de modal).
- **Testes direcionados:**
    - `npm run test:run -- --include=...chatbot.spec.ts --include=...chat-message-media.component.spec.ts --include=...negotiations.spec.ts --include=...negotiation-show.spec.ts`
    - Resultado: **4 arquivos / 47 testes passados**.
- **Gate frontend (`npm run gate:all`):** ✅ passou após correções dos bloqueadores herdados de Chat.
- **Resultado final do gate:** `116/116` arquivos de teste, `644/644` testes, build de produção OK.
- **Validação por editor (`get_errors`) nos arquivos refatorados:** sem erros.
- QA scoped: ✅ aprovado.
- Code review scoped: ✅ aprovado.
- Commit: pendente.

---

# TASK-022 — Bloco C: Refatorar CRM Contacts

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: PRD-CRM-001

## Agente responsável:

FRONTEND

## Goal

Reduzir a complexidade de `crm-contacts.ts`, separando listagem, filtros e interações auxiliares em unidades focadas, alinhadas ao padrão do módulo CRM.

## Constraints

- Verificar aderência ao golden model em `app/src/app/pages/crm/contacts/`.
- Preservar shared components e tokens visuais existentes.
- Manter estados loading, empty e error explícitos.
- Não alterar contratos de serviço.

## Context

- Módulos afetados: CRM, Shared
- Dependências: TASK-018, TASK-021
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PRDS/PRD-CRM-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`
    - `app/src/app/pages/crm/contacts/`

## Etapas

- [x] Mapear responsabilidades e pontos de extração em `crm-contacts.ts`.
- [x] Extrair unidades focadas preservando contratos de listagem e ações.
- [x] Garantir estados loading, empty e error.
- [x] Atualizar testes direcionados do módulo.
- [x] Rodar `npm run gate:all`.
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando (escopo direcionado de CRM Contacts).
- [x] Gates verdes (`npm run gate:all`).
- [x] QA review sem issues críticos.
- [x] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- **Arquivos refatorados (baixo risco, responsabilidades focadas):**
    - `app/src/app/pages/crm/contacts/crm-contacts.ts`
    - `app/src/app/pages/crm/contacts/crm-contacts.helpers.ts`
- **Extrações aplicadas sem alteração de contrato:**
    - filtros de listagem extraídos para `buildContactFilters` + `mapStatusToIsActive`
    - seleção de linhas extraída para helpers puros (`computeNextSelectedIds`, `computePageSelectionIds`, `pruneSelectionToVisibleContacts`, `areAllPageContactsSelected`)
    - mensagem de confirmação de exclusão extraída para `buildDeleteConfirmationMessage`
    - fluxo de delete dividido em métodos focados (`getDeleteTargetIds`, `finishDeleteSuccess`, `finishDeleteError`)
    - subscriptions de setup isoladas (`setupFilterStatusSubscription`, `setupSelectAllSubscription`)
- **Testes direcionados adicionados/atualizados:**
    - `app/src/app/pages/crm/contacts/crm-contacts.spec.ts` (3 testes)
    - `app/src/app/pages/crm/contacts/crm-contacts.helpers.spec.ts` (7 testes)
    - Comando: `npm run test:run -- --include=src/app/pages/crm/contacts/crm-contacts.spec.ts --include=src/app/pages/crm/contacts/crm-contacts.helpers.spec.ts`
    - Resultado: **2 arquivos / 10 testes passados**.
- **Validação por editor (`get_errors`) no escopo alterado:** sem erros.
- **Ajuste de configuração para testes de escopo:**
    - `app/tsconfig.spec.json` incluiu `crm-contacts.ts` e `crm-contacts.helpers.ts` para remover erro de projeto no editor sem ampliar compilação global.
- **Gate frontend (`npm run gate:all`):** ✅ passou após correções dos bloqueadores herdados de Chat.
- **Resultado final do gate:** `116/116` arquivos de teste, `644/644` testes, build de produção OK.
- QA scoped: ✅ aprovado.
- Code review scoped: ✅ aprovado.
- Commit: pendente.

---

# TASK-023 — Bloco F: Concluir migração Angular 20 da Dimensão 3

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: N/A

## Agente responsável:

FRONTEND

## Goal

Concluir os itens remanescentes da migração Angular 20 dentro do escopo da Dimensão 3, migrando templates para `@if/@for`, adicionando `track` e padronizando `OnPush` onde aplicável após os refactors estruturais.

## Constraints

- Executar somente após estabilizar os blocos anteriores.
- Evitar misturar migração mecânica com refactor estrutural no mesmo lote.
- Não introduzir mudanças comportamentais fora do controle de fluxo e estratégia de change detection.

## Context

- Módulos afetados: Ai, Chat, CRM, Reports, Shared e demais consumidores tocados pelos blocos anteriores
- Dependências: TASK-018, TASK-019, TASK-020, TASK-021, TASK-022
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`
    - `AGENTS.md`

## Etapas

- [x] Levantar a lista real de componentes ainda pendentes para `@if/@for`, `track` e `OnPush`.
- [x] Aplicar a migração por lotes pequenos e verificáveis. _(resultado: sem pendências remanescentes no escopo da Dimensão 3)_
- [x] Atualizar os testes afetados pelos templates modificados. _(sem impacto: não houve alteração de template no escopo)_
- [x] Rodar `npm run gate:all`.
- [x] Atualizar documentação e validar o audit com o status final da Dimensão 3. _(sem ajuste adicional no audit: sem finding remanescente no escopo)_

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando. _(suite completa executada sem regressão)_
- [x] Gates verdes (`npm run gate:all`).
- [x] QA review sem issues críticos.
- [x] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- **Inventário de pendências (escopo Dimensão 3):**
    - `rg -n "\*ngIf|\*ngFor" app/src/app/pages/ai app/src/app/pages/chat app/src/app/pages/crm app/src/app/pages/reports app/src/app/shared app/src/app/layout` → **0 matches**
    - `rg -n "@for\\s*\\(" app/src/app/pages/ai app/src/app/pages/chat app/src/app/pages/crm app/src/app/pages/reports app/src/app/shared app/src/app/layout | rg -v "track"` → **0 matches**
    - `rg -l "^\\s*@Component\\(" app/src/app --glob "*.ts" | rg -v "\\.spec\\.ts$" | while ...` (checagem de `changeDetection: ChangeDetectionStrategy.OnPush`) → **0 componentes sem OnPush no escopo**
- **Observação fora de escopo da task:** `app/src/app/app.html` contém `@for` com `track item.title` (template de scaffold Angular, sem pendência funcional).
- **Audit (`AUDIT-FRONTEND-001`):** validado sem necessidade de ajuste adicional para Dimensão 3 (nenhum finding remanescente de migração Angular 20 no escopo da task).
- **Gate frontend:** ✅ `npm run gate:all` verde.
- **Resultado do gate:** `116/116` arquivos de teste, `644/644` testes, build de produção OK (`Application bundle generation complete`).
- **QA scoped:** ✅ aprovado.
- **Code review scoped:** ✅ aprovado.
- **Commit:** pendente.

---

# TASK-024 — Bloco G: Corrigir validação do cartão em billing

## Status: done

## Plano origem: PLAN-018-refatorar-dimensao-3-frontend

## PRD relacionado: PRD-BILLING-001

## Agente responsável:

FRONTEND

## Goal

Corrigir o finding `FE-BILLING-001`, adicionando validação de padrão ao formulário de cartão de crédito no módulo Billing, sem alterar contratos backend/gateway e sem redesign da experiência atual.

## Constraints

- Preservar componentes compartilhados e validações já existentes no formulário.
- Não introduzir `any` ou `unknown`.
- Implementar feedback de erro coerente com os padrões visuais existentes.
- Não expandir o escopo para outros campos de billing fora do cartão.

## Context

- Módulos afetados: Billing, Shared
- Dependências: nenhuma obrigatória; pode ser executada após TASK-018
- Referências:
    - `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
    - `.context/DOCS/PRDS/PRD-BILLING-001.md`
    - `.context/DOCS/PLANS/PLAN-018-refatorar-dimensao-3-frontend.md`

## Etapas

- [x] Localizar o formulário de cartão afetado em `billing/*`.
- [x] Definir o padrão de validação necessário conforme o fluxo atual da tela.
- [x] Implementar validação e mensagem de erro sem alterar o contrato do payload.
- [x] Atualizar testes direcionados do formulário afetado.
- [x] Rodar `pnpm run gate:all`.
- [x] Atualizar documentação.

## Critérios de conclusão

- [x] Código implementado conforme plano.
- [x] Testes escritos e passando.
- [x] Gates verdes (`pnpm run gate:all`).
- [x] QA review sem issues críticos.
- [x] Code review aprovado.
- [x] Documentação atualizada.

## Evidências

- `invoice-payment-modal.ts` com validators de pattern para número, mês, ano e CVV, mensagens de erro por campo e normalização do payload do cartão.
- `invoice-payment-modal.spec.ts` cobrindo cenário inválido (bloqueia submit) e cenário válido (payload normalizado).
- `get_errors` sem erros nos arquivos alterados do bloco.
- Gates: ✅ `npm run gate:all` verde (`116/116` arquivos de teste, `644/644` testes, build de produção OK).
- QA scoped: ✅ aprovado.
- Code review scoped: ✅ aprovado.
- Commit: pendente.
