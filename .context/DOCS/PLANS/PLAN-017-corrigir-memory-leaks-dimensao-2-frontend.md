# PLAN-017-corrigir-memory-leaks-dimensao-2-frontend — Corrigir memory leaks da Dimensão 2 do frontend

## Objetivo

Corrigir os findings restantes da Dimensão 2 do audit frontend, focando em subscriptions RxJS sem `takeUntilDestroyed()`, cleanup incompleto de `Subject`s e falsos positivos já saneados, sem alterar comportamento funcional das telas afetadas.

## Módulo relacionado

**Frontend** — Angular 20 / TypeScript 5.9 (`app/src/`)

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Correção de cleanup reativo em arquivos da Dimensão 2 ainda pendentes no frontend.
- Padronização de subscriptions para `takeUntilDestroyed()` quando aplicável.
- Completar `Subject`s e `Map<string, Subject<...>>` durante cleanup de services.
- Ajustes de testes unitários existentes afetados pelas correções.
- Atualização do relatório de audit para marcar falsos positivos e itens já saneados durante a execução.

### Excluído

- Refatoração de god components ou itens de outras dimensões do audit.
- Mudanças de API, gateway ou backend.
- Criação de novos componentes visuais.
- Reescrita ampla de stores/services já funcionalmente corretos apenas por preferência estilística.

## Etapas propostas

1. Consolidar a lista final de arquivos realmente pendentes, separando falsos positivos e itens já corrigidos.
2. Corrigir primeiro os services/singletons de maior efeito colateral global: `realtime.service.ts` e `chat-recorder.service.ts`.
3. Corrigir páginas e componentes com subscriptions sem gerenciamento: `campaigns.ts`, `dashboard.ts`, `knowledge-upload.ts` e `deal-edit-modal.component.ts`.
4. Revisar os casos parcialmente corrigidos (`chat.store.ts` e `crm-section.ts`) e só migrar de cleanup manual para `takeUntilDestroyed()` se a mudança preservar o ciclo de vida atual.
5. Executar a matriz de testes direcionados por lote e registrar resultados.
6. Rodar gate do frontend, submeter QA e code review.
7. Atualizar o audit frontend com o status final da Dimensão 2.

## Tasks derivadas

| Task      | Descrição                                                 | Agente   | Status |
| --------- | --------------------------------------------------------- | -------- | ------ |
| TASKS-017 | Corrigir memory leaks pendentes da Dimensão 2 no frontend | FRONTEND | todo   |

## Riscos e dependências

### Riscos

| Risco                                                                                        | Probabilidade | Impacto | Mitigação                                                                                                                   |
| -------------------------------------------------------------------------------------------- | ------------- | ------- | --------------------------------------------------------------------------------------------------------------------------- |
| Cleanup encerrar stream antes do esperado em modal/página                                    | Média         | Média   | Aplicar `takeUntilDestroyed()` apenas em subscriptions vinculadas ao ciclo de vida do host e validar specs do fluxo afetado |
| Service com cleanup global quebrar reuso após reconnect                                      | Média         | Alta    | Validar reconexão e reuso no `realtime.service` com spec dedicada                                                           |
| Refactor manual `unsubscribe()` → `takeUntilDestroyed()` em store introduzir regressão sutil | Baixa         | Média   | Tratar stores com lote separado e validar specs específicas de resync/streaming                                             |

### Dependências

- `.context/DOCS/AUDITS/AUDIT-FRONTEND-001.md`
- `AGENTS.md`
- Specs existentes em `app/src/app/**/*.spec.ts` dos arquivos afetados

### Matriz mínima de testes

- Lote services: `app/src/app/core/services/realtime.service.spec.ts` e `app/src/app/pages/chat/services/chat-recorder.service.spec.ts`
- Lote páginas/componentes: `app/src/app/pages/chat/campaigns/campaigns.spec.ts`, `app/src/app/pages/dashboard/dashboard.spec.ts`, `app/src/app/pages/ai/pages/knowledge/knowledge-upload/knowledge-upload.spec.ts` e `app/src/app/pages/chat/chat-sidebar/crm-section/deal-edit-modal/deal-edit-modal.component.spec.ts` se necessário após alteração do modal
- Lote refactor baixo risco: `app/src/app/pages/chat/store/chat-store.spec.ts`, `app/src/app/pages/chat/store/chat-store.resync.spec.ts`, `app/src/app/pages/chat/store/chat-store.streaming.spec.ts` e `app/src/app/pages/chat/chat-sidebar/crm-section/crm-section.spec.ts`
- Gate final: `pnpm run gate:all`
- Baseline pré-execução conhecido: gate atual do frontend falha por erros preexistentes em `user-chat.spec.ts`; durante Validation, distinguir claramente falhas herdadas das introduzidas por esta task

## Estimativa

| Item                          | Valor    |
| ----------------------------- | -------- |
| Complexidade                  | Média    |
| Camadas afetadas              | Frontend |
| Migrações necessárias         | Não      |
| Impacto em módulos existentes | Sim      |
