# Tasks: fix-webchat-message-order

> Feature: .context/DOCS/FEATURES/fix-webchat-message-order.md
> Total: 2 tasks | Pendentes: 0 | Em progresso: 0 | Concluídas: 2

---

## ✅ FASE 1: PLANNING
> Feature doc aprovada. Causa raiz identificada por investigação direta.

---

## ⏳ FASE 2: FRONTEND

### 2.1 — Correção do mapeamento createdAt nos handlers WebSocket

- [x] **TASK-2.1.1** 🔄
  **T — Tarefa:** Adicionar mapeamento `createdAt` nos handlers `webchat:ai_response` e `webchat:agent_message` em WebChatService

  **A — Arquivo:** `app/src/app/pages/webchat/services/webchat.service.ts` (modificar)

  **Referência:** Mesmo arquivo, linhas 564–570 (`fileUrl`, `mimeType`, `fileName` já mapeados) — replicar padrão exato para `createdAt`

  **C — Comportamento:**
  ANTES: `webchat:ai_response` e `webchat:agent_message` chegam com `created_at` (snake_case do Laravel). O spread `...(msgData as unknown as WebChatMessage)` não renomeia a chave. `message.createdAt` fica `undefined`. `compareWebChatMessagesAsc` trata `undefined` como epoch 0 → mensagens da IA/atendente ordenam sempre primeiro (topo), mensagem do visitante vai para baixo.
  DEPOIS: `createdAt` mapeado via `(msgData['created_at'] as string | undefined) ?? (msgData['createdAt'] as string | undefined)` em ambos os handlers. Mensagens ordenam cronologicamente correto.

  **Alterações exatas:**

  Handler `webchat:ai_response` — linha 570 (após `fileName`), inserir:
  ```typescript
        createdAt:
          (msgData['created_at'] as string | undefined) ??
          (msgData['createdAt'] as string | undefined),
  ```

  Handler `webchat:agent_message` — linha 596 (após `fileName`), inserir:
  ```typescript
        createdAt:
          (msgData['created_at'] as string | undefined) ??
          (msgData['createdAt'] as string | undefined),
  ```

  **E — Evidência:**
  - [ ] `pnpm --filter app build` → sem erro de tipos TypeScript
  - [ ] `pnpm --filter app lint` → sem violações
  - [ ] Abrir webchat, enviar "Oi" como visitante → aguardar resposta da Sofia → confirmar que "Oi" aparece ACIMA da resposta (ordem cronológica correta)
  - [ ] Recarregar página → histórico via REST mantém mesma ordem (já correto via `WebChatMessagesController` linha 93)

  **Status:** ✅ Concluída

---

## ⏳ FASE 3: CHAT INTERNO

### 3.1 — Correção do sort de mensagens no chat interno (agente)

- [ ] **TASK-3.1.1** ⏳
  **T — Tarefa:** Reverter sort de DESC para ASC em `user-chat-thread.store.ts`

  **A — Arquivo:** `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.store.ts` (modificar)

  **Referência:** Commit `f8e86b7` mudou `mergeAndSortMessagesAsc` → `mergeAndSortMessagesDesc`. Layout usa `flex-col` + `scrollToBottom`, então DESC inverte a ordem visual.

  **C — Comportamento:**
  ANTES: `mergeAndSortMessagesDesc` coloca mensagem mais nova no índice 0 = topo do DOM. Scroll para o fundo mostra mensagem mais antiga. Resultado visual: mais novo no topo, mais antigo embaixo = INVERTIDO.
  DEPOIS: `mergeAndSortMessagesAsc` coloca mensagem mais antiga no índice 0 = topo do DOM. Scroll para o fundo mostra mensagem mais nova. Resultado visual: cronológico correto (antiga no topo, nova embaixo).

  **Alterações exatas:**

  Linha 15 (import): trocar `mergeAndSortMessagesDesc` → `mergeAndSortMessagesAsc`
  Linha 223 (mergeAndSort): trocar `mergeAndSortMessagesDesc` → `mergeAndSortMessagesAsc`

  **E — Evidência:**
  - [ ] `pnpm --filter app build` → sem erro de tipos TypeScript
  - [ ] `pnpm --filter app lint` → sem violações
  - [ ] Abrir `/chat/{ticketId}` como agente → confirmar: mensagem mais antiga (visitante) no TOPO, resposta da Sofia ABAIXO
  - [ ] Enviar nova mensagem como agente → aparece no FUNDO (scroll segue)

  **Status:** ✅ Concluída
