# Tasks: Encerramento pelo cliente no webchat externo

> Decomposição T.A.C.E das tasks da feature

---

## Feature: Encerramento pelo cliente no webchat externo

**ID:** FEAT-042
**Bounded Context:** Chat
**Total Tasks:** 7
**Concluídas:** 7

---

## 🔄 FASE 3: BACKEND

### Tasks

#### TASK-3.042.1 ✅: Criar endpoint público de encerramento do webchat

**T — Tarefa:** Criar um endpoint público específico para encerrar o ticket vinculado a uma sessão webchat usando o token JWT da sessão, sem reaproveitar o controller interno autenticado por usuário.

**A — Arquivo:**

- `api/src/Domain/Chat/Http/Controllers/WebChatCloseController.php`
- `api/src/Domain/Chat/Routes/webchat.php`
- `api/src/Domain/Chat/Services/WebChatJwtService.php`

**C — Comportamento:**

```text
ANTES:
- O webchat público não possui ação de encerramento.
- Apenas o fluxo interno autenticado por atendente consegue fechar ticket.

DEPOIS:
- O webchat público envia POST /api/webchat/close com token da sessão.
- O backend resolve session_id, tenant_id e ticket_id pelo JWT.
- O ticket é encerrado pelo fluxo público de forma idempotente.
```

**E — Evidência:**

- [x] `POST /api/webchat/close` retorna 200 com `status=closed` para ticket aberto.
- [x] `POST /api/webchat/close` retorna estado final consistente para ticket já fechado.
- [x] Token inválido ou expirado retorna erro claro de autenticação.

**Dependências:** Nenhuma.

**Status:** ✅ Concluída

---

#### TASK-3.042.2 ✅: Publicar atualização realtime de ticket fechado para o atendente

**T — Tarefa:** Garantir que o fechamento iniciado pelo cliente publique `ticket.updated` com o ticket já fechado para as telas internas do atendente.

**A — Arquivo:**

- `api/src/Domain/Chat/Actions/UpdateChatTicketAction.php`
- `api/src/Domain/Chat/Services/ChatActivityBroadcastService.php`

**C — Comportamento:**

```text
ANTES:
- O fechamento via fluxo público não existe.
- O atendente depende de refresh ou de fluxo interno para refletir fechamento.

DEPOIS:
- Fechamento originado no webchat publica `ticket.updated` com payload do ticket.
- A tela interna recebe status `closed` em tempo real.
```

**E — Evidência:**

- [x] O backend publica `ticket.updated` quando o fechamento vem do webchat.
- [x] O payload contém `ticket_id`, `tenant_id` e `ticket.status=closed`.
- [x] O fluxo continua reaproveitando `end_service_message` no fechamento normal.

**Dependências:** TASK-3.042.1.

**Status:** ✅ Concluída

---

#### TASK-3.042.3 ✅: Cobrir backend com testes do fechamento público

**T — Tarefa:** Criar testes de feature e unitários cobrindo encerramento público, idempotência, token inválido e emissão do broadcast esperado.

**A — Arquivo:**

- `api/tests/Feature/Chat/WebChatCloseControllerTest.php`
- `api/tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`

**C — Comportamento:**

```text
ANTES:
- Não existem testes para fechamento público do webchat.

DEPOIS:
- O contrato HTTP do endpoint público fica protegido por testes.
- A emissão de `ticket.updated` e o reaproveitamento do fechamento normal ficam cobertos.
```

**E — Evidência:**

- [x] Teste passando para fechamento com token válido.
- [x] Teste passando para ticket já fechado.
- [x] Teste passando para token inválido/expirado.
- [x] Teste passando para emissão de `ticket.updated` no fluxo público.

**Dependências:** TASK-3.042.1, TASK-3.042.2.

**Status:** ✅ Concluída

---

## 🔄 FASE 4: FRONTEND

### Tasks

#### TASK-4.042.1 ✅: Estender estado do webchat para fechamento público

**T — Tarefa:** Adicionar ao serviço e aos modelos do webchat os estados necessários para encerrar sessão pública e refletir `ticket.status`, `isClosing` e erro de fechamento.

**A — Arquivo:**

- `app/src/app/pages/webchat/services/webchat.service.ts`
- `app/src/app/pages/webchat/webchat.model.ts`
- `app/src/app/pages/webchat/webchat-page.component.ts`

**C — Comportamento:**

```text
ANTES:
- O webchat controla apenas mensagens e conexão websocket.
- O componente não sabe se o ticket está aberto ou fechado.

DEPOIS:
- O serviço expõe fechamento do ticket, loading e estado final.
- A página pública consegue renderizar comportamento de ticket encerrado.
```

**E — Evidência:**

- [x] O serviço possui método para chamar o endpoint público de fechamento.
- [x] O estado reativo distingue ticket aberto, fechando e fechado.
- [x] O cliente que iniciou o fechamento atualiza a UI pelo retorno HTTP.

**Dependências:** TASK-3.042.1.

**Status:** ✅ Concluída

---

#### TASK-4.042.2 ✅: Implementar UI pública com botão, modal e estado final

**T — Tarefa:** Atualizar a janela do webchat público para exibir o botão no cabeçalho, abrir modal de confirmação e ocultar o composer após o encerramento.

**A — Arquivo:**

- `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.scss`

**C — Comportamento:**

```text
ANTES:
- O cabeçalho do webchat não possui ação de encerramento.
- O composer continua disponível enquanto existe sessão.

DEPOIS:
- O cabeçalho exibe `Encerrar chamado` para ticket aberto.
- O clique abre modal de confirmação.
- Após sucesso, o histórico permanece visível e o composer deixa de ser renderizado.
```

**E — Evidência:**

- [x] O botão aparece apenas quando o ticket está aberto.
- [x] O modal impede encerramento acidental.
- [x] O composer some após fechamento com o histórico preservado.

**Dependências:** TASK-4.042.1.

**Status:** ✅ Concluída

---

#### TASK-4.042.3 ✅: Garantir sincronização do atendente com fechamento remoto

**T — Tarefa:** Validar e completar a reação do frontend interno ao `ticket.updated` de fechamento originado pelo cliente para que a conversa aberta se comporte como encerrada.

**A — Arquivo:**

- `app/src/app/pages/chat/store/chat.store.ts`
- `app/src/app/pages/chat/store/chat-store.spec.ts`
- `app/src/app/pages/chat/components/chat-conversation-component/chat-conversation-component.html`

**C — Comportamento:**

```text
ANTES:
- A UI interna já trata ticket fechado, mas o fluxo remoto precisa ser garantido por teste.

DEPOIS:
- `ticket.updated` com `status=closed` fecha visualmente a conversa aberta sem refresh.
- O composer e ações de envio seguem a mesma regra do fechamento interno.
```

**E — Evidência:**

- [x] Teste da store cobre `ticket.updated` com `status=closed`.
- [x] A conversa aberta deixa de permitir envio após o update.
- [x] O fluxo remoto não exige reload manual da tela do atendente.

**Dependências:** TASK-3.042.2.

**Status:** ✅ Concluída

---

#### TASK-4.042.4 ✅: Cobrir frontend público com testes do novo estado

**T — Tarefa:** Criar testes para botão, modal, loading de fechamento, estado final e falhas do endpoint público no webchat.

**A — Arquivo:**

- `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`
- `app/src/app/pages/webchat/services/webchat.service.spec.ts`

**C — Comportamento:**

```text
ANTES:
- Os testes do webchat não cobrem encerramento pelo cliente.

DEPOIS:
- A UI pública tem regressão protegida para ação de encerramento.
- O serviço tem contrato testado para sucesso e erro do endpoint.
```

**E — Evidência:**

- [x] Teste passando para exibição do botão no cabeçalho.
- [x] Teste passando para confirmação e sucesso do fechamento.
- [x] Teste passando para erro de fechamento sem quebrar o histórico.

**Dependências:** TASK-4.042.1, TASK-4.042.2.

**Status:** ✅ Concluída

---

## 🔄 FASE 5: INTEGRAÇÃO

### Tasks

#### TASK-5.042.1 ✅: Validar fluxo completo e gates relevantes

**T — Tarefa:** Executar a validação do fluxo completo do webchat público até a tela do atendente e registrar evidências dos gates impactados.

**A — Arquivo:**

- `.context/DOCS/FEATURES/FEAT-042-webchat-client-close-ticket.md`
- `.context/DOCS/TASKS/FEAT-042-tasks.md`

**C — Comportamento:**

```text
ANTES:
- Não há evidência consolidada de que o fechamento público e o sincronismo interno funcionam juntos.

DEPOIS:
- O fluxo cliente → backend → atendente é validado.
- As evidências de lint, testes e build relevantes ficam registradas na task.
```

**E — Evidência:**

- [x] Testes backend do escopo passam.
- [x] Testes frontend do escopo passam.
- [x] Verificação manual confirma que o atendente aberto recebe `status=closed` sem refresh.

**Dependências:** TASK-3.042.3, TASK-4.042.3, TASK-4.042.4.

**Status:** ✅ Concluída

---

## Revisão de Tasks

| Task         | Status | Validada por | Data |
| ------------ | ------ | ------------ | ---- |
| TASK-3.042.1 | ✅     | QA           | 2026-04-21 |
| TASK-3.042.2 | ✅     | QA           | 2026-04-21 |
| TASK-3.042.3 | ✅     | QA           | 2026-04-21 |
| TASK-4.042.1 | ✅     | QA           | 2026-04-21 |
| TASK-4.042.2 | ✅     | QA           | 2026-04-21 |
| TASK-4.042.3 | ✅     | QA           | 2026-04-21 |
| TASK-4.042.4 | ✅     | QA           | 2026-04-21 |
| TASK-5.042.1 | ✅     | QA           | 2026-04-21 |

---

## Progresso

- [7/7] Tasks concluídas
- [x] Feature completa
