import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, ActivatedRoute } from '@angular/router';
import { of, throwError } from 'rxjs';
import { TicketDetailPage } from './ticket-detail-page';
import {
  CalledService,
  type Called,
  type CalledMessagesResponse,
} from '@core/services/called.service';
import { ToastService } from '@core/services/toast.service';

function buildTicket(overrides: Partial<Called> = {}): Called {
  return {
    id: 'ticket-1',
    company_id: 'company-1',
    protocol: 'PROTO-001',
    status: 'in_progress',
    channel: 'whatsapp',
    queued_at: '2026-03-01T10:00:00Z',
    started_at: '2026-03-01T10:02:00Z',
    closed_at: null,
    closed_mode: null,
    wait_duration_seconds: 120,
    service_duration_seconds: 600,
    contact: { id: 'c1', name: 'Maria Silva', phone: '+5511999999999' },
    user: { id: 'u1', name: 'Agente João', email: 'joao@test.com' },
    evaluation: {
      has_evaluation: true,
      rating: 4,
      comment: 'Bom',
      submitted_at: '2026-03-01T11:00:00Z',
    },
    sentiment: 'positive',
    ...overrides,
  };
}

function buildMessagesResponse(): CalledMessagesResponse {
  return {
    data: [
      {
        id: 'msg-1',
        ticket_id: 'ticket-1',
        content: 'Olá',
        direction: 'incoming',
        created_at: '2026-03-01T10:02:30Z',
        user: null,
      },
      {
        id: 'msg-2',
        ticket_id: 'ticket-1',
        content: 'Ajuda?',
        direction: 'outgoing',
        created_at: '2026-03-01T10:03:00Z',
        user: { id: 'u1', name: 'João', email: 'j@t.com' },
      },
    ],
    meta: { current_page: 1, total: 2, per_page: 20, last_page: 1 },
  };
}

interface Ctx {
  fixture: ComponentFixture<TicketDetailPage>;
  component: TicketDetailPage;
  calledMock: {
    get: ReturnType<typeof vi.fn>;
    close: ReturnType<typeof vi.fn>;
    getMessages: ReturnType<typeof vi.fn>;
  };
  toastMock: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };
}

function newCtx(): Ctx {
  return {
    fixture: undefined as unknown as ComponentFixture<TicketDetailPage>,
    component: undefined as unknown as TicketDetailPage,
    calledMock: {
      get: vi.fn().mockReturnValue(of({ data: buildTicket() })),
      close: vi
        .fn()
        .mockReturnValue(of({ data: buildTicket({ status: 'closed', closed_mode: 'forced' }) })),
      getMessages: vi.fn().mockReturnValue(of(buildMessagesResponse())),
    },
    toastMock: { success: vi.fn(), error: vi.fn() },
  };
}

async function init(ctx: Ctx): Promise<void> {
  TestBed.overrideComponent(TicketDetailPage, {
    set: {
      template: '<div></div>',
    },
  });

  await TestBed.configureTestingModule({
    imports: [TicketDetailPage],
    providers: [
      provideRouter([]),
      { provide: CalledService, useValue: ctx.calledMock },
      { provide: ToastService, useValue: ctx.toastMock },
      {
        provide: ActivatedRoute,
        useValue: {
          snapshot: { paramMap: { get: (k: string) => (k === 'ticketId' ? 'ticket-1' : null) } },
        },
      },
    ],
  }).compileComponents();
  ctx.fixture = TestBed.createComponent(TicketDetailPage);
  ctx.component = ctx.fixture.componentInstance;
  ctx.fixture.detectChanges();
}

describe('TicketDetailPage — load', () => {
  const ctx = newCtx();
  beforeEach(async () => {
    Object.assign(ctx, newCtx());
    await init(ctx);
  });

  it('loads ticket details on init', () => {
    expect(ctx.calledMock.get).toHaveBeenCalledWith('ticket-1');
    expect(ctx.component.isLoading()).toBe(false);
    expect(ctx.component.ticket()?.id).toBe('ticket-1');
  });

  it('shows correct page title', () => {
    expect(ctx.component.pageTitle()).toBe('Atendimento #PROTO-001');
  });

  it('shows error state on load failure', () => {
    ctx.calledMock.get.mockReturnValueOnce(throwError(() => new Error('fail')));
    const f = TestBed.createComponent(TicketDetailPage);
    f.detectChanges();
    expect(f.componentInstance.hasError()).toBe(true);
  });
});

describe('TicketDetailPage — data', () => {
  const ctx = newCtx();
  beforeEach(async () => {
    Object.assign(ctx, newCtx());
    await init(ctx);
  });

  it('builds ticket info items', () => {
    const items = ctx.component.ticketInfoItems();
    expect(items.length).toBe(9);
    expect(items[0]).toEqual({ term: 'Protocolo', detail: 'PROTO-001' });
  });

  it('builds metrics items', () => {
    const items = ctx.component.metricsItems();
    expect(items.length).toBe(5);
    expect(items[0]).toEqual({ term: 'Tempo de espera', detail: '2m' });
  });
});

describe('TicketDetailPage — force close', () => {
  const ctx = newCtx();
  beforeEach(async () => {
    Object.assign(ctx, newCtx());
    await init(ctx);
  });

  it('opens modal', () => {
    ctx.component.openForceClose();
    expect(ctx.component.showForceCloseModal()).toBe(true);
  });

  it('executes force close', () => {
    ctx.component.openForceClose();
    ctx.component.handleForceCloseConfirmed();
    expect(ctx.calledMock.close).toHaveBeenCalledWith('ticket-1', { mode: 'forced' });
    expect(ctx.toastMock.success).toHaveBeenCalled();
  });

  it('handles error', () => {
    ctx.calledMock.close.mockReturnValueOnce(throwError(() => new Error('denied')));
    ctx.component.openForceClose();
    ctx.component.handleForceCloseConfirmed();
    expect(ctx.toastMock.error).toHaveBeenCalled();
  });
});
