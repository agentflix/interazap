# PLAN-025-enviar-nome-ia-mensagem — Estender Flag Existente de Nome em Mensagens do Chat

## Objetivo

Estender o comportamento da flag existente `send_attendant_name` em Chat/Integracoes para que ela continue prefixando o nome do atendente humano e passe a prefixar tambem o nome do agente de IA nas mensagens de texto enviadas automaticamente.

## Modulo relacionado

- Chat
- Ai

## PRD relacionado (se existir): nao encontrado

## Escopo

### Incluido

- Validacao e reaproveitamento da flag existente `send_attendant_name` no fluxo de Chat/Integracoes
- Preservacao do comportamento atual para mensagens de texto enviadas por atendente humano (`source=agent`)
- Injecao do campo `agent_name` no contexto de execucao do agente de IA (Autopilot)
- Mapeamento deste nome via `SendMessageTool`, repassando-o como metadata para a `ChatMessageDTO`
- Alteracao da condicional de formatacao em `ChatMessageActions` para permitir formatar tanto usuarios reais (`agent`) quanto agentes virtuais (`ai` e `bot`)
- Cobertura de testes para o cenario novo de IA e regressao do cenario existente do atendente humano

### Excluido

- Criacao de nova flag no front-end ou no backend, porque a flag ja existe no formulario, na validacao da request e no `settings_json` da instancia
- Modificacoes no Gateway Node.js, porque a logica fica a cargo do backend Laravel gerar o texto final
- Aplicacao da regra para mensagens que nao sejam do tipo `text`

## Evidencias da codebase

- Frontend ja expoe a flag em Chat/Integracoes em `app/src/app/pages/chat/integration/components/integration-form/integration-form.ts` e `app/src/app/pages/chat/integration/components/integration-form/integration-form.html`
- Backend ja valida a flag via `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php`
- A persistencia acontece em `settings_json` de `chat_instances`
- O prefixo para atendente humano ja existe em `api/src/Domain/Chat/Actions/ChatMessageActions.php`, hoje restrito a `ChatMessageDTO::SOURCE_AGENT`
- Ja existe teste cobrindo o caso humano em `api/tests/Unit/Chat/ChatMessageActionsTest.php`

## Etapas propostas

1. Consolidar o contrato existente da flag `send_attendant_name` e registrar no plano que nao havera criacao de nova configuracao.
2. Alterar o listener do Autopilot para prover o nome do agente de IA no contexto de execucao.
3. Alterar o `SendMessageTool` para propagar o nome do agente de IA nos metadados da mensagem.
4. Ajustar a formatacao outbound em `ChatMessageActions` para aceitar IA sem quebrar o fluxo atual do atendente humano.
5. Adicionar ou ajustar testes cobrindo IA e regressao positiva do caso humano.

## Entregas derivadas

**Entregas:** 1 | **Tasks:** 4

| Entrega | Descricao                                           | Tasks                       | Esforco | Status |
| ------- | --------------------------------------------------- | --------------------------- | ------- | ------ |
| 1       | Estender flag existente para IA sem regredir humano | TASK-025.1.1 - TASK-025.1.4 | XS      | todo   |

## Riscos e dependencias

### Riscos

| Risco                                                                                             | Probabilidade | Impacto | Mitigacao                                                                           |
| ------------------------------------------------------------------------------------------------- | ------------- | ------- | ----------------------------------------------------------------------------------- |
| Regressao no envio de mensagens do atendente humano ao flexibilizar `shouldPrefixAttendantName()` | Baixa         | Medio   | Manter teste existente do humano e adicionar cobertura especifica para IA           |
| Ambiguidade de origem entre mensagens `bot` e `ai` ao resolver o nome de exibicao                 | Media         | Medio   | Definir prioridade de resolucao via metadata (`ai_agent_name`) e fallback explicito |

### Dependencias

- Nenhuma dependencia externa identificada.

## Estimativa

| Item                          | Valor   |
| ----------------------------- | ------- |
| Complexidade                  | Baixa   |
| Camadas afetadas              | Backend |
| Migracoes necessarias         | Nao     |
| Impacto em modulos existentes | Sim     |
