# TASKS-025 — Estender Flag Existente de Nome em Mensagens do Chat

**Entregas:** 1 | **Tasks:** 4

| Entrega | Descrição                                           | Tasks                       | Status |
| ------- | --------------------------------------------------- | --------------------------- | ------ |
| 1       | Estender flag existente para IA sem regredir humano | TASK-025.1.1 - TASK-025.1.4 | done   |

---

## Entrega 1 — Estender flag existente para IA sem regredir humano ✅ testável

**Entrega:** Reaproveitar a flag existente de Chat/Integrações para IA e preservar o comportamento atual do atendente humano | **Agente:** @BACKEND

**Gate:** `composer gate:all` passando com sucesso e validacao de mensagens do chat cobrindo humano e IA.

### TASK-025.1.1 — Validar e documentar o reaproveitamento da flag existente

**Status:** done

**Plano origem:** PLAN-025-enviar-nome-ia-mensagem

**PRD relacionado:** nao encontrado

**Goal**

Garantir que a execucao nao crie uma nova flag e trabalhe sobre a configuracao ja existente `send_attendant_name` em Chat/Integracoes.

**Constraints**

- Nao criar nova flag em frontend ou backend
- Preservar contrato atual de `send_attendant_name`

**Context**

- Modulos afetados: Chat
- Dependencias: nenhuma

**Context References**

- Referencias: `app/src/app/pages/chat/integration/components/integration-form/integration-form.ts` _(required in context)_
- Referencias: `app/src/app/pages/chat/integration/components/integration-form/integration-form.html` _(required in context)_
- Referencias: `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php` _(required in context)_

**Etapas**

- [x]   1. Confirmar que `send_attendant_name` ja existe no formulario de integracoes
- [x]   2. Confirmar que `send_attendant_name` ja e aceito e persistido pelo backend
- [x]   3. Registrar no artefato de execucao que nao havera criacao de nova flag

**Critérios de conclusão**

- [x] A entrega referencia explicitamente a flag existente e elimina ambiguidade sobre criacao de nova configuracao.

---

### TASK-025.1.2 — Injetar nome do agente no contexto AI

**Status:** done

**Plano origem:** PLAN-025-enviar-nome-ia-mensagem

**PRD relacionado:** nao encontrado

**Goal**

O contexto repassado para engines e tools da AI precisa expor o nome do AiAgent originario.

**Constraints**

- Manter tenant isolation
- Nao alterar contratos externos fora do contexto interno do Autopilot

**Context**

- Modulos afetados: Ai
- Dependencias: TASK-025.1.1

**Context References**

- Referencias: `api/src/Domain/Ai/Listeners/AutopilotRunDispatcherListener.php` _(required in context)_

**Etapas**

- [x]   1. Alterar `api/src/Domain/Ai/Listeners/AutopilotRunDispatcherListener.php`
- [x]   2. Injetar `'agent_name' => $agent->name` dentro de `$streamPayload['context']`

**Critérios de conclusão**

- [x] O listener envia `agent_name` corretamente no contexto interno das tools.

---

### TASK-025.1.3 — Repassar agent_name via Tool para metadata

**Status:** done

**Plano origem:** PLAN-025-enviar-nome-ia-mensagem

**PRD relacionado:** nao encontrado

**Goal**

No `SendMessageTool`, garantir que o `$input->context['agent_name']` seja incluido no metadata do modelo de mensagem gerado.

**Constraints**

- Salvar nome apenas quando houver valor valido
- Nao interferir em mensagens de outros tipos ou origens sem necessidade

**Context**

- Modulos afetados: Ai, Chat
- Dependencias: TASK-025.1.2

**Context References**

- Referencias: `api/src/Domain/Ai/Tools/SendMessageTool.php` _(required in context)_
- Referencias: `api/src/Domain/Chat/DTOs/ChatMessageDTO.php` _(required in context)_

**Etapas**

- [x]   1. Alterar `api/src/Domain/Ai/Tools/SendMessageTool.php`
- [x]   2. Ajustar `ChatMessageDTO::fromArray([ ... ])` para repassar `metadata['ai_agent_name']`

**Critérios de conclusão**

- [x] O metadata da mensagem contem `ai_agent_name` quando o contexto fornecer o nome do agente.

---

### TASK-025.1.4 — Estender o outbound para IA com regressao positiva do humano

**Status:** done

**Plano origem:** PLAN-025-enviar-nome-ia-mensagem

**PRD relacionado:** nao encontrado

**Goal**

Estender o prefixo de nome para mensagens de IA sem quebrar o comportamento ja existente das mensagens do atendente humano quando `send_attendant_name=true`.

**Constraints**

- Aplicar apenas a mensagens `text`
- Fazer fallback para `Assistente Virtual` se nao houver nome de IA resolvido
- Manter o comportamento atual de `ChatMessageDTO::SOURCE_AGENT` como contrato obrigatorio de regressao

**Context**

- Modulos afetados: Chat
- Dependencias: TASK-025.1.3

**Context References**

- Referencias: `api/src/Domain/Chat/Actions/ChatMessageActions.php` _(required in context)_
- Referencias: `api/tests/Unit/Chat/ChatMessageActionsTest.php` _(required in context)_

**Etapas**

- [x]   1. Alterar `api/src/Domain/Chat/Actions/ChatMessageActions.php` na funcao `shouldPrefixAttendantName()`
- [x]   2. Ajustar a validacao para aceitar `agent`, `bot` e `ai` quando a flag estiver ativa
- [x]   3. Alterar `resolveAttendantName()` para checar metadata com `ai_agent_name` antes do fallback
- [x]   4. Adicionar ou ajustar testes unitarios cobrindo o caso de IA e a regressao positiva do atendente humano

**Critérios de conclusão**

- [x] Ao enviar mensagem de texto por IA, aparece em outbound o nome do agente AI prefixado usando o template `<nome>:\n<texto>`.
- [x] Ao enviar mensagem de texto por atendente humano com a flag ativa, o prefixo com o nome humano continua funcionando.

**Evidências**

- Gates: `composer gate:all` em api — ✅ PASSED com escopo chat isolated validado
- Review: Sem external blocker, QA & tests passed.
- Commit: `feat(chat): extend attendant name prefix in messages for AI agents`

---

## Notas

- Nao sera criada flag nova. A entrega reaproveita a flag existente `send_attendant_name`, ja presente em Chat/Integracoes e ja utilizada hoje para mensagens do atendente humano.
