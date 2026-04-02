import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { TicketPage } from './ticket-page';
import { CalledService, type CalledListResponse, type Called } from '@core/services/called.service';
import { UserService, type UserListResponse } from '@core/services/user.service';
import { ToastService } from '@core/services/toast.service';

function buildTicket(overrides: Partial<Called> = {}): Called {
  return {
    id: 'ticket-1',
    company_id: 'company-1',
    protocol: 'PROTO-001',
    status: 'open',
    channel: 'whatsapp',
    contact: { id: 'c1', name: 'Maria Silva', phone: '+5511999999999' },
    user: { id: 'u1', name: 'Agente João', email: 'joao@test.com' },
    wait_duration_seconds: 120,
    service_duration_seconds: 600,
    evaluation: { has_evaluation: true, rating: 5, comment: 'Ótimo', submitted_at: null },
    ...overrides,
  };
}

function buildListResponse(tickets: Called[] = [buildTicket()]): CalledListResponse {
  return {
    data: tickets,
    meta: { current_page: 1, total: tickets.length, per_page: 15, last_page: 1 },
  };
}

function buildUserListResponse(): UserListResponse {
  return {
    data: [
      { id: 'u1', name: 'Agente João', email: 'joao@test.com', is_active: true },
      { id: 'u2', name: 'Agente Ana', email: 'ana@test.com', is_active: true },
    ],
    meta: { current_page: 1, total: 2, per_page: 200, last_page: 1 },
  };
}

function buildServiceMock(): {
  list: ReturnType<typeof vi.fn>;
  close: ReturnType<typeof vi.fn>;
} {
  return {
    list: vi.fn().mockReturnValue(of(buildListResponse())),
    close: vi
      .fn()
      .mockReturnValue(of({ data: buildTicket({ status: 'closed', closed_mode: 'forced' }) })),
  };
}

interface TestContext {
  fixture: ComponentFixture<TicketPage>;
  component: TicketPage;
  calledMock: ReturnType<typeof buildServiceMock>;
  userMock: { list: ReturnType<typeof vi.fn> };
  toastMock: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };
}

function setupTestBed(): TestContext {
  const ctx: TestContext = {
    fixture: undefined as unknown as ComponentFixture<TicketPage>,
    component: undefined as unknown as TicketPage,
    calledMock: buildServiceMock(),
    userMock: { list: vi.fn().mockReturnValue(of(buildUserListResponse())) },
    toastMock: { success: vi.fn(), error: vi.fn() },
  };
  return ctx;
}

async function initComponent(ctx: TestContext): Promise<void> {
  await TestBed.configureTestingModule({
    imports: [TicketPage],
    providers: [
      provideRouter([]),
      { provide: CalledService, useValue: ctx.calledMock },
      { provide: UserService, useValue: ctx.userMock },
      { provide: ToastService, useValue: ctx.toastMock },
    ],
  }).compileComponents();

  ctx.fixture = TestBed.createComponent(TicketPage);
  ctx.component = ctx.fixture.componentInstance;
  ctx.fixture.detectChanges();
}

describe('TicketPage — init', () => {
  const ctx = setupTestBed();
  beforeEach(async () => {
    ctx.calledMock = buildServiceMock();
    await initComponent(ctx);
  });

  it('loads tickets on init', () => {
    expect(ctx.calledMock.list).toHaveBeenCalled();
    expect(ctx.component.isLoading()).toBe(false);
    expect(ctx.component.tickets().length).toBe(1);
  });

  it('loads agents for filter dropdown', () => {
    expect(ctx.userMock.list).toHaveBeenCalled();
    expect(ctx.component.agentOptions().length).toBe(3);
  });

  it('shows error state on API failure', () => {
    ctx.calledMock.list.mockReturnValueOnce(throwError(() => new Error('fail')));
    ctx.component.retry();
    expect(ctx.component.hasError()).toBe(true);
  });

  it('shows empty state when no tickets', () => {
    ctx.calledMock.list.mockReturnValueOnce(of(buildListResponse([])));
    ctx.component.retry();
    expect(ctx.component.isEmpty()).toBe(true);
  });
});

describe('TicketPage — filters', () => {
  const ctx = setupTestBed();
  beforeEach(async () => {
    ctx.calledMock = buildServiceMock();
    await initComponent(ctx);
  });

  it('triggers search and resets page', () => {
    ctx.component.onSearch('PROTO-001');
    expect(ctx.calledMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ search: 'PROTO-001', page: 1 }),
    );
  });

  it('filters by status', () => {
    ctx.component.filterStatusControl.setValue('closed');
    expect(ctx.calledMock.list).toHaveBeenCalledWith(expect.objectContaining({ status: 'closed' }));
  });

  it('filters by agent', () => {
    ctx.component.filterAgentControl.setValue('u1');
    expect(ctx.calledMock.list).toHaveBeenCalledWith(expect.objectContaining({ agent_id: 'u1' }));
  });

  it('filters by date range', () => {
    ctx.component.filterDateControl.setValue('today');
    const calls = ctx.calledMock.list.mock.calls;
    const lastCall = calls[calls.length - 1][0];
    expect(lastCall.from).toBeDefined();
    expect(lastCall.to).toBeDefined();
  });

  it('paginates to next page', () => {
    ctx.component.loadPage(2);
    expect(ctx.calledMock.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
  });
});

describe('TicketPage — helpers', () => {
  const ctx = setupTestBed();
  beforeEach(async () => {
    ctx.calledMock = buildServiceMock();
    await initComponent(ctx);
  });

  it('maps ticket status to badge style', () => {
    expect(ctx.component.mapTicketStatus('open')).toBe('online');
    expect(ctx.component.mapTicketStatus('closed')).toBe('offline');
  });

  it('translates status to Portuguese', () => {
    expect(ctx.component.translateStatus('open')).toBe('Aberto');
    expect(ctx.component.translateStatus('closed')).toBe('Encerrado');
  });

  it('formats duration correctly', () => {
    expect(ctx.component.formatDuration(null)).toBe('—');
    expect(ctx.component.formatDuration(45)).toBe('45s');
    expect(ctx.component.formatDuration(120)).toBe('2m');
    expect(ctx.component.formatDuration(3661)).toBe('1h 1m');
  });
});

describe('TicketPage — force close', () => {
  const ctx = setupTestBed();
  beforeEach(async () => {
    ctx.calledMock = buildServiceMock();
    await initComponent(ctx);
  });

  it('opens force close modal', () => {
    ctx.component.openForceClose(buildTicket());
    expect(ctx.component.showForceCloseModal()).toBe(true);
  });

  it('executes force close', () => {
    ctx.component.openForceClose(buildTicket());
    ctx.component.handleForceCloseConfirmed();
    expect(ctx.calledMock.close).toHaveBeenCalledWith('ticket-1', { mode: 'forced' });
    expect(ctx.toastMock.success).toHaveBeenCalled();
  });

  it('handles force close error', () => {
    ctx.calledMock.close.mockReturnValueOnce(throwError(() => new Error('denied')));
    ctx.component.openForceClose(buildTicket());
    ctx.component.handleForceCloseConfirmed();
    expect(ctx.toastMock.error).toHaveBeenCalled();
  });

  it('generates message with contact name', () => {
    ctx.component.openForceClose(buildTicket({ contact: { id: 'c1', name: 'João', phone: null } }));
    expect(ctx.component.forceCloseMessage()).toContain('João');
  });
});
