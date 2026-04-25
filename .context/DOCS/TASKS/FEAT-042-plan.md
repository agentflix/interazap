# FEAT-042 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o visitante encerre o ticket no webchat público e sincronizar o estado fechado na tela do atendente sem refresh manual.

**Architecture:** Criar um endpoint público específico de fechamento autenticado pelo JWT da sessão webchat, reaproveitando `UpdateChatTicketAction` para o fechamento normal e a mensagem automática já existente. No frontend público, o estado final será dirigido pelo retorno HTTP; no frontend interno, o sincronismo continuará por `ticket.updated` via realtime.

**Tech Stack:** Laravel 12, Angular 20, Pest, Vitest/Jasmine, Socket.IO, realtime via `ws.events`.

---

## File Map

- Create: `api/src/Domain/Chat/Http/Controllers/WebChatCloseController.php`
- Modify: `api/src/Domain/Chat/Routes/webchat.php`
- Modify: `api/src/Domain/Chat/Actions/UpdateChatTicketAction.php`
- Test: `api/tests/Feature/Chat/WebChatCloseControllerTest.php`
- Test: `api/tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`
- Modify: `app/src/app/pages/webchat/webchat.model.ts`
- Modify: `app/src/app/pages/webchat/services/webchat.service.ts`
- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`
- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.scss`
- Test: `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`
- Test: `app/src/app/pages/webchat/services/webchat.service.spec.ts`
- Modify/Test: `app/src/app/pages/chat/store/chat.store.ts`
- Test: `app/src/app/pages/chat/store/chat-store.spec.ts`

---

### Task 1: Backend close endpoint

**Files:**

- Create: `api/src/Domain/Chat/Http/Controllers/WebChatCloseController.php`
- Modify: `api/src/Domain/Chat/Routes/webchat.php`
- Test: `api/tests/Feature/Chat/WebChatCloseControllerTest.php`

- [ ] **Step 1: Write the failing feature tests**

```php
it('closes a webchat ticket using the session token', function (): void {
    $session = ChatSession::factory()->forOpenTicket()->create();
    $token = app(WebChatJwtService::class)->generateToken(
        sessionId: (string) $session->id,
        tenantId: (string) $session->tenant_id,
        contactId: (string) $session->contact_id,
        ticketId: (string) $session->ticket_id,
    );

    $response = $this->postJson('/api/webchat/close', ['token' => $token]);

    $response
        ->assertOk()
        ->assertJsonPath('data.ticketId', (string) $session->ticket_id)
        ->assertJsonPath('data.status', 'closed');
});

it('returns unauthorized for an invalid webchat token', function (): void {
    $this->postJson('/api/webchat/close', ['token' => 'invalid-token'])
        ->assertUnauthorized();
});
```

- [ ] **Step 2: Run the feature test to verify failure**

Run: `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Feature/Chat/WebChatCloseControllerTest.php`
Expected: FAIL because route/controller do not exist yet.

- [ ] **Step 3: Implement the minimal controller and route**

```php
final class WebChatCloseController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
        private readonly UpdateChatTicketAction $updateAction,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        $payload = $this->jwtService->validateToken($token);

        if ($payload === null) {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $ticket = $this->updateAction->find((string) $payload['tenant_id'], (string) $payload['ticket_id']);

        if ($ticket->status !== 'closed') {
            $ticket = $this->updateAction->updateStatus($ticket, 'closed', null, 'normal', null);
        }

        return $this->success([
            'ticketId' => (string) $ticket->id,
            'status' => $ticket->status,
            'closedAt' => $ticket->closed_at?->toIso8601String(),
        ], 'Ticket fechado');
    }
}
```

- [ ] **Step 4: Add the route**

```php
Route::post('/webchat/close', [WebChatCloseController::class, 'store']);
```

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Feature/Chat/WebChatCloseControllerTest.php`
Expected: PASS.

---

### Task 2: Backend idempotency and attendant realtime

**Files:**

- Modify: `api/src/Domain/Chat/Actions/UpdateChatTicketAction.php`
- Test: `api/tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`

- [ ] **Step 1: Write the failing unit tests for broadcast behavior**

```php
it('broadcasts ticket.updated when a ticket is closed from webchat', function (): void {
    $ticket = ChatTicket::factory()->open()->create();

    $broadcast = mock(ChatActivityBroadcastService::class);
    $broadcast->shouldReceive('emit')->once()->withArgs(function (string $ticketId, array $events, string $tenantId): bool {
        return $ticketId !== ''
            && $tenantId !== ''
            && $events[0]['type'] === 'ticket.updated'
            && $events[0]['data']['ticket']['status'] === 'closed';
    });

    app()->instance(ChatActivityBroadcastService::class, $broadcast);

    app(UpdateChatTicketAction::class)->updateStatus($ticket, 'closed', null, 'normal', null);
});
```

- [ ] **Step 2: Run the unit test to verify failure**

Run: `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`
Expected: FAIL because close flow does not emit `ticket.updated` yet.

- [ ] **Step 3: Add close broadcast to the action**

```php
$ticket->load(['latestMessage', 'contact', 'user', 'extended', 'evaluation']);

$this->activityBroadcast->emit(
    (string) $ticket->id,
    [[
        'type' => 'ticket.updated',
        'data' => [
            'ticket_id' => (string) $ticket->id,
            'tenant_id' => (string) $ticket->tenant_id,
            'ticket' => $ticket->toArray(),
            'event_type' => 'ticket_closed',
        ],
    ]],
    (string) $ticket->tenant_id,
);
```

- [ ] **Step 4: Add idempotency coverage in the feature test**

```php
it('returns the final closed state when the webchat ticket is already closed', function (): void {
    $session = ChatSession::factory()->forClosedTicket()->create();
    $token = app(WebChatJwtService::class)->generateToken(...);

    $this->postJson('/api/webchat/close', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});
```

- [ ] **Step 5: Run backend scope tests**

Run: `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Feature/Chat/WebChatCloseControllerTest.php tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`
Expected: PASS.

---

### Task 3: Webchat service state

**Files:**

- Modify: `app/src/app/pages/webchat/webchat.model.ts`
- Modify: `app/src/app/pages/webchat/services/webchat.service.ts`
- Test: `app/src/app/pages/webchat/services/webchat.service.spec.ts`

- [ ] **Step 1: Write the failing service tests**

```ts
it('should close the webchat ticket and set local status to closed', () => {
    service.closeTicket().subscribe();

    const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/close`);
    expect(req.request.method).toBe('POST');

    req.flush({ data: { ticketId: 'ticket-1', status: 'closed' } });

    expect(service.ticketStatus()).toBe('closed');
});
```

- [ ] **Step 2: Run frontend service test to verify failure**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/webchat/services/webchat.service.spec.ts'`
Expected: FAIL because `closeTicket` and `ticketStatus` do not exist.

- [ ] **Step 3: Extend the webchat model**

```ts
export type WebChatTicketStatus = 'open' | 'closed';

export interface WebChatCloseResponse {
    ticketId: string;
    status: WebChatTicketStatus;
    closedAt?: string | null;
}
```

- [ ] **Step 4: Implement service state and close call**

```ts
private readonly _ticketStatus = signal<WebChatTicketStatus>('open');
private readonly _isClosing = signal(false);

readonly ticketStatus = this._ticketStatus.asReadonly();
readonly isClosing = this._isClosing.asReadonly();
readonly isClosed = computed(() => this._ticketStatus() === 'closed');

closeTicket(): Observable<WebChatCloseResponse> {
  const token = this.sessionToken?.trim();
  if (!token) {
    return throwError(() => new Error('Sessão inválida'));
  }

  this._isClosing.set(true);

  return this.http.post<unknown>(`${this.apiBase}/api/webchat/close`, { token }).pipe(
    map((response) => this.unwrapData<WebChatCloseResponse>(response)),
    tap((response) => this._ticketStatus.set(response.status)),
    finalize(() => this._isClosing.set(false)),
  );
}
```

- [ ] **Step 5: Run the service test again**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/webchat/services/webchat.service.spec.ts'`
Expected: PASS.

---

### Task 4: Public chat UI

**Files:**

- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`
- Modify: `app/src/app/pages/webchat/components/chat-window/chat-window.component.scss`
- Test: `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`

- [ ] **Step 1: Write the failing component tests**

```ts
it('should render the close button when the ticket is open', () => {
    mockWebChatService.isClosed = signal(false);
    fixture.detectChanges();

    expect(screen.getByText('Encerrar chamado')).toBeTruthy();
});

it('should hide the composer after a successful close', async () => {
    mockWebChatService.closeTicket.mockReturnValue(of({ ticketId: 'ticket-1', status: 'closed' }));

    component.confirmClose();
    fixture.detectChanges();

    expect(screen.queryByPlaceholderText('Digite sua mensagem...')).toBeNull();
});
```

- [ ] **Step 2: Run the component tests to verify failure**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts'`
Expected: FAIL because the button/modal/final state are missing.

- [ ] **Step 3: Add UI state and confirm handler**

```ts
readonly isClosed = this.webchatService.isClosed;
readonly isClosing = this.webchatService.isClosing;
readonly isCloseModalOpen = signal(false);

openCloseModal(): void {
  if (this.isClosed() || this.isClosing()) return;
  this.isCloseModalOpen.set(true);
}

confirmClose(): void {
  this.webchatService.closeTicket().subscribe({
    next: () => this.isCloseModalOpen.set(false),
  });
}
```

- [ ] **Step 4: Update the template**

```html
@if (!isClosed()) {
<button type="button" (click)="openCloseModal()">Encerrar chamado</button>
} @if (isCloseModalOpen()) {
<af-confirm-dialog
    title="Encerrar chamado"
    description="Deseja encerrar este atendimento?"
    [loading]="isClosing()"
    (confirmed)="confirmClose()"
    (cancelled)="isCloseModalOpen.set(false)"
/>
} @if (!isClosed() && sessionId()) {
<af-chat-composer ... />
} @else {
<div>Este chamado foi encerrado.</div>
}
```

- [ ] **Step 5: Run the component tests again**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts'`
Expected: PASS.

---

### Task 5: Attendant sync and scope validation

**Files:**

- Modify/Test: `app/src/app/pages/chat/store/chat.store.ts`
- Test: `app/src/app/pages/chat/store/chat-store.spec.ts`

- [ ] **Step 1: Write the failing store test for remote close**

```ts
it('should merge status closed when ticket.updated comes from realtime', () => {
    store['applyBatchEvent'](
        {
            type: 'ticket.updated',
            data: {
                ticket_id: 'ticket-1',
                ticket: { id: 'ticket-1', status: 'closed', close_reason: null },
                event_type: 'ticket_closed',
            },
        },
        changes,
        flags,
    );

    expect(changes.tickets.get('ticket-1')?.status).toBe('closed');
});
```

- [ ] **Step 2: Run the store test to verify failure or gap**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/chat/store/chat-store.spec.ts'`
Expected: FAIL or expose missing merge behavior for the closed payload.

- [ ] **Step 3: Adjust the merge only if needed**

```ts
if (type === 'ticket.updated') {
    const incoming = ticketData.ticket;
    changes.tickets.set(ticketId, {
        ...existingTicket,
        ...incoming,
        status: incoming?.status ?? existingTicket.status,
    });
}
```

- [ ] **Step 4: Run scoped frontend validation**

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run test:run -- --include='src/app/pages/chat/store/chat-store.spec.ts' && npm run test:run -- --include='src/app/pages/webchat/**/*.spec.ts'`
Expected: PASS for scoped tests.

- [ ] **Step 5: Run final gates for changed layers**

Run: `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Feature/Chat/WebChatCloseControllerTest.php tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`
Expected: PASS.

Run: `cd /Users/rafael.silva/Documents/interazap/app && npm run gate:test`
Expected: PASS or identify unrelated blockers explicitly.

---

## Self-Review

- Spec coverage: endpoint público, reaproveitamento do fechamento normal, estado final no webchat, sincronização do atendente e testes estão cobertos nas Tasks 1-5.
- Placeholder scan: nenhum `TODO`/`TBD` deixado no plano.
- Type consistency: o plano usa `status='closed'`, `ticket.updated` e `POST /api/webchat/close` de forma consistente em backend e frontend.
