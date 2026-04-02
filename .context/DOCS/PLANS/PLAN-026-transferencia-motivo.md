# PLAN-026-transferencia-motivo — Registrar motivo interno na transferência entre atendentes

## Contexto carregado

- Memórias encontradas: documentação em PT-BR por padrão, workflow PREVC obrigatório antes de concluir a tarefa.
- Estado do projeto: `.context/ARCHITECTURE/project-state.yaml` permanece em `in_progress` e indica ADRs ainda pendentes, então esta decisão precisa ser registrada formalmente.
- Histórico relevante: a codebase já possui dois fluxos de transferência no módulo Chat: um fluxo legado em `POST /chat/tickets/{id}/transfer`, hoje consumido pela tela principal, e um fluxo dedicado em `POST /chat/tickets/{ticketId}/transfers`, que já persiste `reason` em `chat_ticket_transfers`.

## Análise da task

- Task: exigir motivo ao transferir chamado na tela de chat, usando `textarea`, e exibir esse motivo como mensagem oculta para o cliente.
- Módulo: Chat.
- PRD: não encontrado.
- Plano existente: este artefato substitui a primeira versão, reprovada por QA/REVIEWER por ambiguidade de contrato, falta de evidências da codebase e tasks fora do template oficial.
- Descrição resumida: a solução precisa registrar o motivo em uma fonte de verdade única, refletir esse motivo na timeline interna do atendimento e garantir que a mensagem não seja enviada ao provedor externo.

## Objetivo

Permitir a transferência de um ticket entre atendentes com motivo obrigatório, capturado via `textarea` na UI, persistido no histórico formal de transferências e espelhado na timeline do chat como `internal_note`, visível apenas para operadores internos e nunca para o cliente final.

## Módulo relacionado

Chat

## PRD relacionado (se existir): não encontrado

## Escopo

### Incluído

- Migrar todos os fluxos de transferência entre atendentes ainda expostos na tela de chat para o endpoint dedicado `POST /chat/tickets/{ticketId}/transfers` com payload `{ to_user_id, reason }`.
- Tornar `reason` obrigatório para a transferência entre atendentes no backend e no frontend.
- Reaproveitar `chat_ticket_transfers.reason` como fonte de verdade do motivo e espelhar o mesmo conteúdo na timeline do ticket como `ChatMessage.type = internal_note`.
- Garantir que `internal_note` não seja enviado ao gateway/provedor externo, mas continue aparecendo no realtime interno.
- Ajustar a renderização do chat para diferenciar visualmente mensagens `internal_note`, seguindo design tokens e componentes compartilhados.
- Atualizar testes backend e frontend para cobrir contrato, persistência, realtime interno e UX do modal.

### Excluído

- Transferência por departamento iniciada via `chat-navbar`; o fluxo atual usa contrato diferente (`department_id`) e o histórico formal de transferências não modela destino por departamento. O escopo deste plano cobre apenas o ramo de transferência para usuário dentro dessa mesma UI.
- Mudanças de schema em `chat_ticket_transfers` para suportar histórico por departamento.
- Alterações em relatórios, dashboards ou analytics de transferência.
- Mudanças customer-facing no canal externo.

## Technical Approach

### Decisão de contrato

- O fluxo dedicado `POST /chat/tickets/{ticketId}/transfers` passa a ser a fonte de verdade para transferências entre atendentes com motivo.
- O endpoint legado `POST /chat/tickets/{id}/transfer` permanece fora deste escopo e segue reservado ao fluxo antigo e aos casos de departamento até existir plano específico de consolidação.
- A action de transferência dedicada deve persistir o registro formal em `chat_ticket_transfers` e, no mesmo fluxo de aplicação, criar uma mensagem interna na timeline com metadados mínimos do handoff.
- O runtime de mensagens deve tratar `internal_note` como mensagem interna: persiste normalmente, não chama `sendToGateway`, e continua emitindo atualização interna em tempo real.

### Skills obrigatórias antes da implementação frontend

- `.claude/skills/design/SKILL.md`
- `.claude/skills/frontend-flow/SKILL.md`
- `.github/skills/angular-architect/SKILL.md`
- `.github/skills/coding-guidelines/SKILL.md`

### Handoff obrigatório

- `@DESIGNER` define a spec do `textarea` e do estado visual de `internal_note` no artefato `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md`, aplicável a qualquer fluxo usuário → usuário na tela de chat.
- `@BACKEND` congela e implementa o contrato dedicado de transferência entre atendentes.
- `@FRONTEND` consome o contrato congelado e aplica a spec visual aprovada.
- `@QA` valida estados, gates e não regressão.
- `@REVIEWER` faz o sign-off final.

### Shared components e padrões a reaproveitar

- `modal`
- `select-input`
- `textarea-input`
- `button`
- Angular com `ChangeDetectionStrategy.OnPush`, `signal()`, `computed()`, `inject()` e `takeUntilDestroyed`

## Evidências da codebase

### Backend (Chat)

- `api/src/Domain/Chat/Http/Requests/ChatTicketTransferRequest.php` já aceita `to_user_id` e `reason`, mas `reason` ainda é `nullable`.
- `api/src/Domain/Chat/Http/Controllers/ChatTicketTransferController.php` já expõe o endpoint dedicado `/chat/tickets/{ticketId}/transfers`.
- `api/src/Domain/Chat/Actions/ChatTicketTransferActions.php` já persiste o histórico formal da transferência com `reason`, porém ainda não cria mensagem interna na timeline.
- `api/src/Domain/Chat/Models/ChatTicketTransfer.php` já contém o campo `reason`, o que evita migração para o escopo entre atendentes.
- `api/src/Domain/Chat/Actions/ChatMessageActions.php` hoje envia qualquer mensagem `outgoing` ao gateway e só emite `emitNewMessageEvent()` para mensagens `incoming`; será necessário abrir exceção controlada para `internal_note`.
- `api/tests/Feature/ChatTicketTransferControllerTest.php` e `api/tests/Unit/Chat/ChatTicketTransferActionsTest.php` já cobrem a transferência dedicada e são a base natural para extensão de testes.

### Frontend (Chat)

- `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.ts` hoje emite apenas `{ ticketId, userId }` e não possui campo de motivo.
- `app/src/app/core/services/called.service.ts` ainda chama o endpoint legado `POST /chat/tickets/{id}/transfer` com `{ user_id }`.
- `app/src/app/pages/chat/chat.ts` consome o serviço legado e precisa ser migrado para o contrato dedicado.
- `app/src/app/pages/chat/components/chat-navbar/chat-navbar.ts` tem outro fluxo de transferência com `department_id` e `user_id`; o ramo `user_id` também precisa passar a exigir `reason`, enquanto o ramo `department_id` permanece fora deste plano.
- `app/src/app/pages/chat/chat.html` hoje monta `app-chat-transfer-modal` sem contrato explícito de `loading`/`error`; esse wiring precisa ser congelado entre container e componente apresentacional.
- `app/src/app/pages/chat/components/message-bubble/message-bubble.component.ts` estiliza mensagens apenas por `direction`, sem estado visual específico para `internal_note`.
- `app/src/app/pages/chat/components/user-chat-thread/user-chat-message-bubble.component.ts` é o ponto correto para identificar `message.type === 'internal_note'` e aplicar rótulo/estilo interno.
- `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.spec.ts` já cobre a emissão do modal e deve ser expandido para `reason` obrigatório.
- `app/src/app/pages/chat/chat.spec.ts` é o alvo adequado para validar a estratégia de atualização local após a migração para o endpoint dedicado.
- `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.spec.ts` é o alvo adequado para validar a renderização visual de `internal_note` no thread.

### Shared Components

- `app/src/app/shared/components/textarea-input/textarea-input.ts` já existe e deve ser reutilizado no modal.

## Etapas propostas

1. `@DESIGNER`: produzir a spec visual do `textarea` obrigatório e do estado visual da `internal_note` no thread do chat. `(XS)`
2. `@BACKEND`: tornar `reason` obrigatório no fluxo dedicado e espelhar o motivo em `internal_note` sem enviar ao provedor externo. `(S)`
3. `@FRONTEND`: migrar os fluxos usuário → usuário da tela de chat para o endpoint dedicado `/transfers`, emitindo `{ ticketId, toUserId, reason }`. `(S)`
   Estratégia congelada para estado local: após sucesso da transferência dedicada, o frontend deve fazer `refetch` do ticket usando o contrato já existente de leitura, em vez de inferir shape de ticket a partir do retorno de `ChatTicketTransferResource`.
4. `@FRONTEND`: renderizar `internal_note` com estilo e rotulagem específicos, seguindo a spec aprovada e cobrindo testes de UI. `(S)`
5. `@QA` + `@REVIEWER`: validar gates, evidências e ausência de regressão no fluxo atual de mensagens e transferência. `(XS)`

## Entregas derivadas

**Entregas:** 4 | **Tasks:** 9

| Entrega | Descrição                                                          | Tasks                       | Esforço | Status |
| ------- | ------------------------------------------------------------------ | --------------------------- | ------- | ------ |
| 1       | Backend: contrato dedicado de transferência com motivo obrigatório | TASK-026.1.1 - TASK-026.1.3 | S       | todo   |
| 2       | Frontend: fluxos usuário → usuário migram para o endpoint dedicado | TASK-026.2.1 - TASK-026.2.3 | S       | todo   |
| 3       | Frontend: timeline interna diferencia `internal_note`              | TASK-026.3.1 - TASK-026.3.2 | S       | todo   |
| 4       | Validação final com gates e sign-off                               | TASK-026.4.1 - TASK-026.4.1 | XS      | todo   |

## Tarefas derivadas para execução paralela

| Task         | Descrição                                                      | Agente          | Paralelo com                                                  |
| ------------ | -------------------------------------------------------------- | --------------- | ------------------------------------------------------------- |
| TASK-026.1.x | Congelar e implementar contrato backend                        | @BACKEND        | -                                                             |
| TASK-026.2.x | Consumir contrato nos fluxos usuário → usuário da tela de chat | @FRONTEND       | TASK-026.3.x após conclusão da Entrega 1                      |
| TASK-026.3.x | Renderizar `internal_note` no thread                           | @FRONTEND       | TASK-026.2.x após spec de `@DESIGNER` e contrato da Entrega 1 |
| TASK-026.4.1 | Validar gates e aprovar                                        | @QA / @REVIEWER | Após Entregas 1, 2 e 3                                        |

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                              | Ação      | Caminho                                                         |
| ------------------------------------ | --------- | --------------------------------------------------------------- |
| ChatTicketTransferRequest.php        | modificar | api/src/Domain/Chat/Http/Requests/ChatTicketTransferRequest.php |
| ChatTicketTransferActions.php        | modificar | api/src/Domain/Chat/Actions/ChatTicketTransferActions.php       |
| ChatMessageActions.php               | modificar | api/src/Domain/Chat/Actions/ChatMessageActions.php              |
| ChatTicketTransferControllerTest.php | modificar | api/tests/Feature/ChatTicketTransferControllerTest.php          |
| ChatTicketTransferActionsTest.php    | modificar | api/tests/Unit/Chat/ChatTicketTransferActionsTest.php           |
| ChatMessageActionsTest.php           | modificar | api/tests/Unit/Chat/ChatMessageActionsTest.php                  |

### Frontend (Angular)

| Arquivo                               | Ação      | Caminho                                                                                  |
| ------------------------------------- | --------- | ---------------------------------------------------------------------------------------- |
| chat-transfer-modal.ts                | modificar | app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.ts             |
| chat-transfer-modal.html              | modificar | app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.html           |
| chat-transfer-modal.spec.ts           | modificar | app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.spec.ts        |
| called.service.ts                     | modificar | app/src/app/core/services/called.service.ts                                              |
| chat.html                             | modificar | app/src/app/pages/chat/chat.html                                                         |
| chat.ts                               | modificar | app/src/app/pages/chat/chat.ts                                                           |
| chat.spec.ts                          | modificar | app/src/app/pages/chat/chat.spec.ts                                                      |
| chat-navbar.ts                        | modificar | app/src/app/pages/chat/components/chat-navbar/chat-navbar.ts                             |
| chat-navbar.html                      | modificar | app/src/app/pages/chat/components/chat-navbar/chat-navbar.html                           |
| chat-navbar.spec.ts                   | modificar | app/src/app/pages/chat/components/chat-navbar/chat-navbar.spec.ts                        |
| message-bubble.component.ts           | modificar | app/src/app/pages/chat/components/message-bubble/message-bubble.component.ts             |
| message-bubble.component.spec.ts      | modificar | app/src/app/pages/chat/components/message-bubble/message-bubble.component.spec.ts        |
| user-chat-message-bubble.component.ts | modificar | app/src/app/pages/chat/components/user-chat-thread/user-chat-message-bubble.component.ts |

## Riscos e dependências

### Riscos

| Risco                                                                                          | Probabilidade | Impacto | Mitigação                                                                                                                 |
| ---------------------------------------------------------------------------------------------- | ------------- | ------- | ------------------------------------------------------------------------------------------------------------------------- |
| Implementar no endpoint legado e duplicar regra de negócio entre `/transfer` e `/transfers`    | Média         | Alto    | Congelar no plano que o fluxo entre atendentes usa apenas `/transfers` neste escopo.                                      |
| `internal_note` vazar para o provedor externo                                                  | Baixa         | Alto    | Cobrir com teste unitário em `ChatMessageActions` garantindo que `sendToGateway` não execute para `type = internal_note`. |
| Realtime interno deixar de refletir a nota por causa da regra atual de `emitNewMessageEvent()` | Média         | Médio   | Adicionar caso explícito para `internal_note` e cobrir com teste focado em emissão interna.                               |
| Requisito ser interpretado como obrigatório também para transferências por departamento        | Média         | Médio   | Manter isso explicitamente fora do escopo e abrir plano futuro caso o negócio confirme a necessidade.                     |

### Dependências

- Aprovação prévia da spec de `@DESIGNER` em `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` para o estado visual de `internal_note`.
- Entrega 1 concluída antes de consumir o contrato no frontend.

## Validação e Gates

- [ ] Backend: `composer gate:all` em `api/`
- [ ] Frontend: `pnpm run gate:all` em `app/`
- [ ] ADR-009 referenciada como evidência arquitetural em `.context/DOCS/MEMORY/architecture-decisions.md`
- [ ] QA: validar modal com motivo obrigatório e estado visual de `internal_note`
- [ ] QA: validar estado vazio (`sem atendentes disponíveis`) e contrato `loading/error` entre container e modal
- [ ] REVIEWER: validar aderência ao contrato dedicado e ausência de regressão no chat

## Estimativa

| Item                          | Valor              |
| ----------------------------- | ------------------ |
| Complexidade                  | Média              |
| Camadas afetadas              | Backend / Frontend |
| Migrações necessárias         | Não                |
| Impacto em módulos existentes | Sim                |
