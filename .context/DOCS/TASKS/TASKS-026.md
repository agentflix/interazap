# TASKS-026 — Motivo interno na transferência entre atendentes

**Entregas:** 4 | **Tasks:** 9

| Entrega | Descrição                                                          | Tasks                       | Status |
| ------- | ------------------------------------------------------------------ | --------------------------- | ------ |
| 1       | Backend: contrato dedicado de transferência com motivo obrigatório | TASK-026.1.1 - TASK-026.1.3 | done   |
| 2       | Frontend: fluxos usuário → usuário migram para o endpoint dedicado | TASK-026.2.1 - TASK-026.2.3 | done   |
| 3       | Frontend: timeline interna diferencia `internal_note`              | TASK-026.3.1 - TASK-026.3.2 | done   |
| 4       | Validação final com gates e sign-off                               | TASK-026.4.1 - TASK-026.4.1 | done*  |

---

## Entrega 1 — Backend: contrato dedicado de transferência com motivo obrigatório ✅ testável

**Entrega:** Tornar o endpoint dedicado de transferência entre atendentes a fonte de verdade do motivo, espelhando esse motivo na timeline como `internal_note` sem envio ao provedor externo. | **Agente:** @BACKEND

**Gate:** Todos os testes da transferência dedicada e de `ChatMessageActions` passam, e `composer gate:all` fica verde no escopo alterado.

### TASK-026.1.1 — Tornar `reason` obrigatório no request dedicado

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Garantir que a transferência entre atendentes não seja aceita sem motivo no endpoint dedicado `/chat/tickets/{ticketId}/transfers`.

**Constraints**

- Não alterar o endpoint legado `/chat/tickets/{id}/transfer`
- Manter tenant isolation e policy `chat.tickets.transfer`
- Não introduzir migração de banco

**Context**

- Módulos afetados: Chat
- Dependências: nenhuma

**Context References**

- Referências: `api/src/Domain/Chat/Http/Requests/ChatTicketTransferRequest.php` _(required in context)_
- Referências: `api/tests/Feature/ChatTicketTransferControllerTest.php` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```php
// Current code (problem)
return [
    'to_user_id' => ['required', 'uuid'],
    'reason' => ['nullable', 'string'],
];
```

```php
// Expected code (solution)
return [
    'to_user_id' => ['required', 'uuid'],
    'reason' => ['required', 'string'],
];
```

</details>

**Etapas**

- [x]   1. Alterar `api/src/Domain/Chat/Http/Requests/ChatTicketTransferRequest.php`
- [x]   2. Ajustar `api/tests/Feature/ChatTicketTransferControllerTest.php` para cobrir rejeição sem motivo
- [x]   3. Verificar `composer gate:all` no escopo backend

**Critérios de conclusão**

- [x] Transferência sem `reason` retorna erro de validação no endpoint dedicado.
      -> `test_transfer_requires_reason_on_dedicated_endpoint`
- [x] Transferência com `reason` continua retornando `201 Created`.
      -> `test_list_and_create_ticket_transfers`
- [x] Usuário sem permissão de transferência recebe resposta negada.
      -> `test_transfer_requires_transfer_permission`
- [x] Usuário de outro tenant não pode ser usado como destino da transferência.
      -> `test_transfer_rejects_target_user_from_another_tenant`

**Evidências**

- Gates: suíte de escopo backend verde (24 testes / 65 assertions)
- Review: aprovado por escopo (após ajuste de `reason` non-whitespace)
- Commit: pendente

---

### TASK-026.1.2 — Espelhar o motivo da transferência como `internal_note`

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Persistir o motivo em `chat_ticket_transfers.reason` e criar, no mesmo fluxo, uma mensagem `internal_note` na timeline do ticket para uso interno da operação.

**Constraints**

- Reaproveitar o endpoint e o model dedicados já existentes
- Não duplicar a regra no controller legado
- A nota interna deve carregar contexto mínimo de transferência em `metadata`

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.1.1

**Context References**

- Referências: `api/src/Domain/Chat/Actions/ChatTicketTransferActions.php` _(required in context)_
- Referências: `api/src/Domain/Chat/Models/ChatTicketTransfer.php` _(required in context)_
- Referências: `api/tests/Unit/Chat/ChatTicketTransferActionsTest.php` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```php
// Current code (problem)
$transfer = ChatTicketTransfer::query()->create([
    'tenant_id' => $ticket->tenant_id,
    'ticket_id' => $ticket->id,
    'from_user_id' => $ticket->assigned_to,
    'to_user_id' => $toUserId,
    'reason' => $reason,
    'status' => 'completed',
    'transferred_at' => now(),
]);

$ticket->assigned_to = $toUserId;
$ticket->save();
```

```php
// Expected code (solution)
$transfer = ChatTicketTransfer::query()->create([...]);

$ticket->assigned_to = $toUserId;
$ticket->save();

$this->chatMessageActions->create(
    (string) $ticket->tenant_id,
    ChatMessageDTO::fromArray([
	  'ticket_id' => (string) $ticket->id,
	  'user_id' => $transfer->from_user_id,
	  'content' => $reason,
	  'type' => 'internal_note',
	  'direction' => 'outgoing',
	  'source' => ChatMessageDTO::SOURCE_SYSTEM,
	  'metadata' => [
		'transfer_id' => (string) $transfer->id,
		'from_user_id' => $transfer->from_user_id,
		'to_user_id' => $transfer->to_user_id,
	  ],
    ]),
);
```

</details>

**Etapas**

- [x]   1. Alterar `api/src/Domain/Chat/Actions/ChatTicketTransferActions.php`
- [x]   2. Injetar a dependência necessária para criação da mensagem interna no mesmo fluxo
- [x]   3. Ajustar `api/tests/Unit/Chat/ChatTicketTransferActionsTest.php` para validar histórico + nota interna

**Critérios de conclusão**

- [x] Transferência dedicada cria histórico com `reason` e atualiza `assigned_to` do ticket.
      -> `test_transfer_updates_ticket_and_creates_history`
- [x] Transferência dedicada cria uma mensagem `internal_note` associada ao ticket.
      -> `test_transfer_creates_internal_note_with_reason`

**Evidências**

- Gates: suíte de escopo backend verde (24 testes / 65 assertions)
- Review: aprovado por escopo
- Commit: pendente

---

### TASK-026.1.3 — Bloquear envio externo de `internal_note` e manter realtime interno

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Garantir que mensagens `internal_note` fiquem apenas na operação interna: persistem, não chamam `sendToGateway`, mas ainda atualizam o realtime do painel.

**Constraints**

- Alteração cirúrgica em `ChatMessageActions`
- Não regressar o comportamento atual de mensagens `incoming` e `outgoing` normais
- Não tocar em código de gateway NestJS

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.1.2

**Context References**

- Referências: `api/src/Domain/Chat/Actions/ChatMessageActions.php` _(required in context)_
- Referências: `api/tests/Unit/Chat/ChatMessageActionsTest.php` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```php
// Current code (problem)
if ($dto->direction === 'outgoing') {
    if (! $ticket->relationLoaded('contact')) {
	  $ticket->load('contact');
    }
    $this->sendToGateway($message, $ticket);
}

if ($dto->direction === 'incoming') {
    $this->emitNewMessageEvent($message, $ticket);
}
```

```php
// Expected code (solution)
if ($dto->direction === 'outgoing' && $dto->type !== 'internal_note') {
    if (! $ticket->relationLoaded('contact')) {
	  $ticket->load('contact');
    }
    $this->sendToGateway($message, $ticket);
}

if ($dto->direction === 'incoming' || $dto->type === 'internal_note') {
    $this->emitNewMessageEvent($message, $ticket);
}
```

</details>

**Etapas**

- [x]   1. Alterar `api/src/Domain/Chat/Actions/ChatMessageActions.php`
- [x]   2. Cobrir o caso `internal_note` em `api/tests/Unit/Chat/ChatMessageActionsTest.php`
- [x]   3. Verificar `composer gate:all` no escopo backend

**Critérios de conclusão**

- [x] `internal_note` não dispara envio ao gateway externo.
      -> `test_internal_note_does_not_send_message_to_gateway`
- [x] `internal_note` continua emitindo atualização interna em realtime.
      -> `test_internal_note_emits_new_message_event_for_internal_clients`

**Evidências**

- Gates: suíte de escopo backend verde (24 testes / 65 assertions)
- Review: aprovado por escopo
- Commit: pendente

---

## Entrega 2 — Frontend: fluxos usuário → usuário migram para o endpoint dedicado ✅ testável

**Entrega:** Capturar motivo obrigatório nos fluxos usuário → usuário da tela de chat e enviar o contrato dedicado `{ to_user_id, reason }` para o backend, com wiring explícito de loading/error entre container e componente apresentacional. | **Agente:** @FRONTEND

**Gate:** Spec de `@DESIGNER` aprovada, testes do modal passando e consumo do endpoint dedicado implementado sem uso do endpoint legado.

### TASK-026.2.1 — Adicionar `textarea` obrigatório e emitir o novo payload

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Atualizar o modal de transferência para coletar `reason` em `textarea-input`, com preenchimento obrigatório, e emitir `{ ticketId, toUserId, reason }`.

**Constraints**

- Ler antes de implementar: `.claude/skills/design/SKILL.md`, `.claude/skills/frontend-flow/SKILL.md`, `.github/skills/angular-architect/SKILL.md`, `.github/skills/coding-guidelines/SKILL.md`
- Reutilizar `textarea-input`; não usar elemento bruto fora do padrão do projeto
- Manter `ChangeDetectionStrategy.OnPush`
- Não iniciar antes da aprovação da spec em `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md`

**Context**

- Módulos afetados: Chat
- Dependências: spec de `@DESIGNER` aprovada

**Context References**

- Referências: `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.html` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.spec.ts` _(required in context)_
- Referências: `app/src/app/shared/components/textarea-input/textarea-input.ts` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```ts
// Current code (problem)
readonly confirmed = output<{ ticketId: string; userId: string }>();

confirm(): void {
  const ticket = this.ticket();
  const selectedUserId = this.transferUserControl.value;
  if (!ticket || !selectedUserId) {
    return;
  }

  this.confirmed.emit({ ticketId: String(ticket.id), userId: selectedUserId });
}
```

```ts
// Expected code (solution)
readonly confirmed = output<{ ticketId: string; toUserId: string; reason: string }>();

confirm(): void {
  const ticket = this.ticket();
  const selectedUserId = this.transferUserControl.value;
  const reason = this.transferReasonControl.value.trim();
  if (!ticket || !selectedUserId || reason.length === 0) {
    return;
  }

  this.confirmed.emit({
    ticketId: String(ticket.id),
    toUserId: selectedUserId,
    reason,
  });
}
```

</details>

**Etapas**

- [x]   1. Alterar `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.ts`
- [x]   2. Alterar `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.html`
- [x]   3. Ajustar `app/src/app/pages/chat/components/chat-transfer-modal/chat-transfer-modal.spec.ts`

**Critérios de conclusão**

- [x] O botão de confirmar fica bloqueado até existir usuário de destino e motivo preenchido.
      -> `it('should disable confirm until user and reason are provided')`
- [x] O modal emite `{ ticketId, toUserId, reason }` ao confirmar.
      -> `it('should emit reason and selected user on confirm')`
- [x] Durante submissão, o modal bloqueia inputs e troca o CTA para `Transferindo...`.
      -> `it('should block inputs and show loading label while submitting transfer')`
- [x] Em erro, o modal mantém os dados preenchidos e exibe feedback visível sem perder contexto.
      -> `it('should preserve form data and show error message when transfer fails')`
- [x] Sem atendentes disponíveis, o modal exibe estado vazio e mantém o CTA desabilitado.
      -> `it('should show empty state when no transfer users are available')`

**Evidências**

- Gates: validação de escopo frontend aprovada; gate global com 1 falha externa
- Review: aprovado por escopo
- Commit: pendente

---

### TASK-026.2.2 — Consumir o endpoint dedicado no serviço e no container

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Trocar o consumo do endpoint legado pelo endpoint dedicado `/transfers` no fluxo do modal principal do chat.

**Constraints**

- Não alterar o fluxo de `chat-navbar` nesta task; ele é tratado separadamente na `TASK-026.2.3`
- Remover apenas o uso do endpoint legado neste fluxo específico
- Estratégia congelada: após sucesso em `/transfers`, refazer a leitura do ticket com o contrato já existente (`get`) em vez de inferir shape de ticket a partir de `ChatTicketTransferResource`

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.2.1, Entrega 1 concluída

**Context References**

- Referências: `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` _(required in context)_
- Referências: `app/src/app/core/services/called.service.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/chat.html` _(required in context)_
- Referências: `app/src/app/pages/chat/chat.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/chat.spec.ts` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```ts
// Current code (problem)
transfer(id: string | number, target: { user_id?: string | number }): Observable<{ data: Called }> {
  return this.http.post<{ data: Called }>(`${this.baseUrl}/${id}/transfer`, target);
}

this.calledService.transfer(event.ticketId, { user_id: event.userId })
```

```ts
// Expected code (solution)
<app-chat-transfer-modal
  [isOpen]="isTransferModalOpen()"
  [isSubmitting]="isTransferLoading()"
  [submitError]="transferError()"
  ...
/>

transferToUser(
  id: string | number,
  payload: { to_user_id: string | number; reason: string },
): Observable<{ data: ChatTicketTransfer }> {
  return this.http.post<{ data: ChatTicketTransfer }>(`${this.baseUrl}/${id}/transfers`, payload);
}

this.calledService.transferToUser(event.ticketId, {
  to_user_id: event.toUserId,
  reason: event.reason,
});

this.calledService.get(event.ticketId).subscribe(...)
```

</details>

**Etapas**

- [x]   1. Alterar `app/src/app/core/services/called.service.ts`
- [x]   2. Alterar `app/src/app/pages/chat/chat.html`
- [x]   3. Alterar `app/src/app/pages/chat/chat.ts`
- [x]   4. Ajustar `app/src/app/pages/chat/chat.spec.ts`
- [x]   5. Verificar que o modal principal não chama mais `/transfer` e faz `refetch` do ticket após sucesso

**Critérios de conclusão**

- [x] O fluxo do modal principal chama `POST /chat/tickets/{ticketId}/transfers` com `to_user_id` e `reason`.
      -> `it('should call dedicated transfer endpoint with reason payload')`
- [x] Após o sucesso da transferência dedicada, o ticket é relido pelo contrato de leitura existente antes de atualizar o estado local.
      -> `it('should refetch ticket after dedicated transfer succeeds')`
- [x] O container repassa `loading` e `error` para o modal sem mover o assíncrono para o componente apresentacional.
      -> `it('should wire transfer loading and error state from container to modal')`

**Evidências**

- Gates: validação de escopo frontend aprovada; gate global com 1 falha externa
- Review: aprovado por escopo
- Commit: pendente

---

### TASK-026.2.3 — Eliminar bypass sem motivo no `chat-navbar` para transferência entre atendentes

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Garantir que o fluxo usuário → usuário exposto em `chat-navbar` também exija `reason` e use o contrato dedicado, evitando bypass funcional sem motivo.

**Constraints**

- O ramo `department_id` permanece fora deste plano
- O ramo `user_id` não pode continuar chamando o endpoint legado sem motivo
- Reutilizar a mesma copy e os mesmos estados definidos na `SPEC-026`

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.2.1, Entrega 1 concluída

**Context References**

- Referências: `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-navbar/chat-navbar.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-navbar/chat-navbar.html` _(required in context)_
- Referências: `app/src/app/pages/chat/components/chat-navbar/chat-navbar.spec.ts` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```ts
// Current code (problem)
const payload = userId ? { user_id: userId } : { department_id: deptId || undefined };

this.calledService.transfer(calledId, payload).subscribe(...)
```

```ts
// Expected code (solution)
if (userId) {
  this.calledService.transferToUser(calledId, {
    to_user_id: userId,
    reason: transferReason,
  }).subscribe(...);
  return;
}

this.calledService.transfer(calledId, { department_id: deptId || undefined }).subscribe(...)
```

</details>

**Etapas**

- [x]   1. Alterar `app/src/app/pages/chat/components/chat-navbar/chat-navbar.ts`
- [x]   2. Alterar `app/src/app/pages/chat/components/chat-navbar/chat-navbar.html`
- [x]   3. Ajustar `app/src/app/pages/chat/components/chat-navbar/chat-navbar.spec.ts`

**Critérios de conclusão**

- [x] O fluxo usuário → usuário do `chat-navbar` exige motivo antes de confirmar.
      -> `it('should require reason for user to user transfer in chat navbar')`
- [x] O fluxo usuário → usuário do `chat-navbar` usa o endpoint dedicado `/transfers`.
      -> `it('should call dedicated transfer endpoint from chat navbar user flow')`
- [x] O fluxo por departamento continua isolado e não sofre regressão neste plano.
      -> `it('should preserve department transfer flow in chat navbar')`

**Evidências**

- Gates: validação de escopo frontend aprovada; gate global com 1 falha externa
- Review: aprovado por escopo
- Commit: pendente

---

## Entrega 3 — Frontend: timeline interna diferencia `internal_note` ✅ testável

**Entrega:** Exibir a nota interna de transferência na timeline com tratamento visual claro para operadores, sem aparência de mensagem enviada ao cliente. | **Agente:** @FRONTEND

**Gate:** Spec de `@DESIGNER` aprovada, componentes de mensagem atualizados sem quebrar mensagens comuns e testes de apresentação passando.

### TASK-026.3.1 — Adaptar a bolha base para suportar estado interno

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Adicionar suporte visual na `MessageBubbleComponent` para mensagens internas, usando tokens do design system em vez de cores ad hoc.

**Constraints**

- Seguir `.claude/skills/design/SKILL.md`
- Não hardcodear hex colors
- Não regressar a apresentação de `incoming` e `outgoing`
- Não iniciar antes da aprovação da spec em `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md`

**Context**

- Módulos afetados: Chat
- Dependências: spec de `@DESIGNER` aprovada

**Context References**

- Referências: `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` _(required in context)_
- Referências: `app/src/app/pages/chat/components/message-bubble/message-bubble.component.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/components/message-bubble/message-bubble.component.spec.ts` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```ts
// Current code (problem)
readonly direction = input<'incoming' | 'outgoing'>('incoming');
```

```ts
// Expected code (solution)
readonly direction = input<'incoming' | 'outgoing'>('incoming');
readonly isInternal = input(false);
```

</details>

**Etapas**

- [x]   1. Alterar `app/src/app/pages/chat/components/message-bubble/message-bubble.component.ts`
- [x]   2. Ajustar `app/src/app/pages/chat/components/message-bubble/message-bubble.component.spec.ts`
- [x]   3. Verificar estados `incoming`, `outgoing` e `internal`

**Critérios de conclusão**

- [x] A bolha base expõe um estado específico para nota interna sem quebrar os estados existentes.
      -> `it('should apply dedicated tokens for internal messages')`
- [x] Mensagens comuns continuam com o mesmo estilo anterior.
      -> `it('should preserve incoming and outgoing styles')`

**Evidências**

- Gates: validação de escopo frontend aprovada; gate global com 1 falha externa
- Review: aprovado por escopo
- Commit: pendente

---

### TASK-026.3.2 — Rotular `internal_note` no thread do usuário

**Status:** done

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Identificar `message.type === 'internal_note'` no componente de thread e exibir a mensagem com rótulo explícito de nota interna oculta ao cliente.

**Constraints**

- Não alterar o formato dos demais tipos de mensagem
- Reutilizar `MessageBubbleComponent`
- O rótulo deve seguir a spec aprovada por `@DESIGNER`

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.3.1, Entrega 1 concluída

**Context References**

- Referências: `.context/DOCS/SPECS/SPEC-026-chat-transfer-internal-note.md` _(required in context)_
- Referências: `app/src/app/pages/chat/components/user-chat-thread/user-chat-message-bubble.component.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/components/message-bubble/message-bubble.component.ts` _(required in context)_
- Referências: `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.spec.ts` _(required in context)_

**Code Context** _(only if modifying existing code)_

<details>
<summary>Current → Expected</summary>

```ts
// Current code (problem)
<app-message-bubble [direction]="isOutgoing() ? 'outgoing' : 'incoming'">
```

```ts
// Expected code (solution)
<app-message-bubble
  [direction]="isOutgoing() ? 'outgoing' : 'incoming'"
  [isInternal]="isInternalNote()"
>
  @if (isInternalNote()) {
    <p class="text-xs font-medium">Nota interna · oculta para o cliente</p>
  }
```

</details>

**Etapas**

- [x]   1. Alterar `app/src/app/pages/chat/components/user-chat-thread/user-chat-message-bubble.component.ts`
- [x]   2. Implementar helper/computed para identificar `internal_note`
- [x]   3. Ajustar `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.spec.ts`
- [x]   4. Verificar renderização em conjunto com a bolha base

**Critérios de conclusão**

- [x] Mensagens `internal_note` são renderizadas com rótulo e estilo interno no thread.
      -> `it('should render internal transfer note with dedicated style')`
- [x] Mensagens não internas continuam renderizando sem rótulo adicional.
      -> `it('should not render internal label for regular messages')`
- [x] A `internal_note` não exibe affordances de canal externo, status de envio nem avatar de contato.
      -> `it('should hide external delivery affordances for internal notes')`

**Evidências**

- Gates: validação de escopo frontend aprovada; gate global com 1 falha externa
- Review: aprovado por escopo
- Commit: pendente

---

## Entrega 4 — Validação final com gates e sign-off ✅ testável

**Entrega:** Validar que o fluxo dedicado de transferência com motivo obrigatório funciona ponta a ponta sem vazar a nota interna para o cliente. | **Agente:** @QA

**Gate:** Todos os critérios abaixo aprovados e sign-off do `@REVIEWER` anexado.

### TASK-026.4.1 — Executar validação final de QA e Review

**Status:** done*

**Plano origem:** PLAN-026-transferencia-motivo

**PRD relacionado:** não encontrado

**Goal**

Executar a validação final da entrega, garantindo aderência ao plano, ao contrato dedicado e ao fluxo visual aprovado.

**Constraints**

- Não marcar como `done` com gates vermelhos
- Validar apenas após Entregas 1, 2 e 3 concluídas
- Exigir parecer formal do `@REVIEWER`

**Context**

- Módulos afetados: Chat
- Dependências: TASK-026.1.1 até TASK-026.3.2

**Context References**

- Referências: `.context/DOCS/MEMORY/architecture-decisions.md` _(required in context)_
- Referências: `.context/DOCS/PLANS/PLAN-026-transferencia-motivo.md` _(required in context)_
- Referências: `.context/DOCS/TASKS/TASKS-026.md` _(embedded above)_

**Etapas**

- [x]   1. Verificar se a ADR-009 está referenciada como evidência arquitetural em `.context/DOCS/MEMORY/architecture-decisions.md`
- [x]   2. Executar testes/gates de backend no escopo da alteração
- [x]   3. Executar testes/gates de frontend no escopo da alteração
- [x]   4. Validar manualmente motivo obrigatório, estado vazio, contrato `loading/error`, persistência em histórico, nota interna na timeline e ausência de envio ao cliente
- [x]   5. Coletar parecer do `@REVIEWER`

**Critérios de conclusão**

- [x] Backend validado por escopo (suítes de transferência/mensagem verdes).
- [x] Os testes-alvo da entrega foram executados e registrados: `test_transfer_requires_reason_on_dedicated_endpoint`, `test_list_and_create_ticket_transfers`, `test_transfer_requires_transfer_permission`, `test_transfer_rejects_target_user_from_another_tenant`, `test_transfer_updates_ticket_and_creates_history`, `test_transfer_creates_internal_note_with_reason`, `test_internal_note_does_not_send_message_to_gateway`, `test_internal_note_emits_new_message_event_for_internal_clients`, `it('should disable confirm until user and reason are provided')`, `it('should emit reason and selected user on confirm')`, `it('should block inputs and show loading label while submitting transfer')`, `it('should preserve form data and show error message when transfer fails')`, `it('should show empty state when no transfer users are available')`, `it('should wire transfer loading and error state from container to modal')`, `it('should refetch ticket after dedicated transfer succeeds')`, `it('should require reason for user to user transfer in chat navbar')`, `it('should call dedicated transfer endpoint from chat navbar user flow')`, `it('should preserve department transfer flow in chat navbar')`, `it('should apply dedicated tokens for internal messages')`, `it('should preserve incoming and outgoing styles')`, `it('should render internal transfer note with dedicated style')`, `it('should not render internal label for regular messages')`, `it('should hide external delivery affordances for internal notes')`.
- [x] O parecer do `@REVIEWER` está registrado e confirma que a nota interna não vaza para o canal do cliente.

**Evidências**

- Gates: fechamento por escopo aprovado. Backend escopo verde; frontend com 1 falha externa em `integration-form.spec.ts` (fora do escopo TASKS-026).
- Review: aprovado por escopo. Observação residual de docstring divergente no request dedicado.
- Commit: pendente

---

## Notas

- `done*` indica conclusão por escopo, conforme direcionamento explícito para seguir com workspace sujo e não bloquear TASKS-026 por falhas globais externas.

- O endpoint legado `/chat/tickets/{id}/transfer` não faz parte deste plano.
- Se o negócio exigir motivo obrigatório também para transferências por departamento, será necessário novo plano com ajuste de contrato e possivelmente de schema.
