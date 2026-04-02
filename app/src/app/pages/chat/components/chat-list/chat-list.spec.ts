import { DOCUMENT } from '@angular/common';
import { NO_ERRORS_SCHEMA, signal } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import {
  type ActivatedRouteSnapshot,
  ActivatedRoute,
  NavigationEnd,
  Router,
  RouterLink,
  convertToParamMap,
} from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { Subject, of } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import {
  type ChatTicketUpdatedEvent,
  ChatRealtimeService,
} from 'src/app/core/services/chat-realtime.service';
import { ButtonComponent } from 'src/app/shared/components/buttons';
import { AfTabsComponent } from 'src/app/shared/components/tabs/tabs';
import { ChatList } from './chat-list';

class CalledServiceStub {
  list = vi.fn();
  counts = vi.fn();
}

class ChatRealtimeServiceStub {
  private newTicketVersion = 0;
  private ticketUpdatedVersion = 0;

  private readonly newTicketSignal = signal<{ event: null; version: number }>({
    event: null,
    version: 0,
  });

  private readonly ticketUpdatedSignal = signal<{
    event: ChatTicketUpdatedEvent | null;
    version: number;
  }>({ event: null, version: 0 });

  readonly connected = signal(false);
  readonly newTicket = this.newTicketSignal.asReadonly();
  readonly ticketUpdated = this.ticketUpdatedSignal.asReadonly();
  readonly ticketSentimentUpdated = signal({ event: null, version: 0 });
  readonly newMessage = signal({ event: null, version: 0 });
  readonly messageStatus = signal({ event: null, version: 0 });
  readonly typing = signal({ event: null, version: 0 });
  readonly ['delete'] = signal({ event: null, version: 0 });
  readonly reaction = signal({ event: null, version: 0 });
  readonly edit = signal({ event: null, version: 0 });
  readonly activity = signal({ event: null, version: 0 });
  readonly contactUpdated = signal({ event: null, version: 0 });
  readonly dealUpdated = signal({ event: null, version: 0 });

  connect = vi.fn();
  disconnect = vi.fn();
  joinTicket = vi.fn();
  leaveTicket = vi.fn();

  triggerNewTicket(): void {
    this.newTicketVersion += 1;
    this.newTicketSignal.set({ event: null, version: this.newTicketVersion });
  }

  triggerTicketUpdated(event: ChatTicketUpdatedEvent): void {
    this.ticketUpdatedVersion += 1;
    this.ticketUpdatedSignal.set({ event, version: this.ticketUpdatedVersion });
  }
}

class RouterStub {
  private readonly subject = new Subject<unknown>();
  readonly events = this.subject.asObservable();

  createUrlTree = vi.fn().mockReturnValue({});
  serializeUrl = vi.fn().mockReturnValue('');

  emit(event: unknown): void {
    this.subject.next(event);
  }
}

class ActivatedRouteStub {
  snapshot: ActivatedRouteSnapshot;

  constructor(snapshot: ActivatedRouteSnapshot) {
    this.snapshot = snapshot;
  }

  setSnapshot(snapshot: ActivatedRouteSnapshot): void {
    this.snapshot = snapshot;
  }
}

const createSnapshot = (calledId: string | null): ActivatedRouteSnapshot => {
  const leaf = {
    paramMap: convertToParamMap({ calledId }),
    firstChild: null,
  } as ActivatedRouteSnapshot;

  return {
    paramMap: convertToParamMap({}),
    firstChild: leaf,
  } as ActivatedRouteSnapshot;
};

const createCalled = (overrides: Partial<Called> = {}): Called => ({
  id: overrides.id ?? 'called-1',
  company_id: overrides.company_id ?? 'tenant-1',
  status: overrides.status ?? 'open',
  channel: overrides.channel ?? 'whatsapp',
  updated_at: overrides.updated_at ?? '2026-01-22T10:00:00.000Z',
  created_at: overrides.created_at ?? '2026-01-22T09:00:00.000Z',
  last_message: overrides.last_message ?? null,
  ...overrides,
});

describe('ChatList', (): void => {
  type ViewTransitionDocument = Document;

  let fixture: ComponentFixture<ChatList>;
  let component: ChatList;
  let calledService: CalledServiceStub;
  let router: RouterStub;
  let route: ActivatedRouteStub;

  let transitionCalled = false;
  let originalStartViewTransition: Document['startViewTransition'] | undefined;

  beforeEach(async (): Promise<void> => {
    transitionCalled = false;

    const patchedDocument = document as ViewTransitionDocument;

    originalStartViewTransition = patchedDocument.startViewTransition;
    patchedDocument.startViewTransition = ((callback: () => void): ViewTransition => {
      transitionCalled = true;
      callback();

      return {
        finished: Promise.resolve(undefined),
        ready: Promise.resolve(undefined),
        updateCallbackDone: Promise.resolve(undefined),
        skipTransition: () => {},
      } as ViewTransition;
    }) as Document['startViewTransition'];

    await TestBed.configureTestingModule({
      imports: [ChatList, RouterLink, NgIcon, ButtonComponent, AfTabsComponent],
      schemas: [NO_ERRORS_SCHEMA],
      providers: [
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: ChatRealtimeService, useClass: ChatRealtimeServiceStub },
        { provide: Router, useClass: RouterStub },
        { provide: ActivatedRoute, useValue: new ActivatedRouteStub(createSnapshot('called-2')) },
        { provide: DOCUMENT, useValue: patchedDocument },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatList);
    component = fixture.componentInstance;

    calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;
    route = TestBed.inject(ActivatedRoute) as unknown as ActivatedRouteStub;
  });

  afterEach((): void => {
    const patchedDocument = document as ViewTransitionDocument;

    if (originalStartViewTransition) {
      patchedDocument.startViewTransition = originalStartViewTransition;
    } else {
      Reflect.deleteProperty(patchedDocument, 'startViewTransition');
    }

    vi.restoreAllMocks();
  });

  it('loads calleds on init, derives counts and sorts by last interaction', (): void => {
    const items = [
      createCalled({
        id: 'called-1',
        status: 'pending',
        updated_at: '2026-01-21T10:00:00.000Z',
      }),
      createCalled({
        id: 'called-2',
        status: 'open',
        updated_at: '2026-01-22T10:00:00.000Z',
      }),
    ];

    calledService.list.mockReturnValue(
      of({
        data: items,
        meta: { current_page: 1, last_page: 1, per_page: 50, total: 2 },
      }),
    );
    fixture.detectChanges();

    expect(component.isLoading()).toBe(false);
    expect(component.counts()).toEqual({
      all: 2,
      pending: 1,
      open: 1,
      closed: 0,
      in_progress: 0,
    });
    expect(component.calleds().map((called) => called.id)).toEqual(['called-2', 'called-1']);
    expect(component.activeCalledId()).toBe('called-2');
    expect(transitionCalled).toBe(true);
  });

  it('filters calleds by active status', (): void => {
    component.calleds.set([
      createCalled({ id: 'pending', status: 'pending' }),
      createCalled({ id: 'open', status: 'open' }),
      createCalled({ id: 'progress', status: 'in_progress' }),
    ]);

    component.setFilter('pending');
    expect(component.filteredCalleds().map((called) => called.id)).toEqual(['pending']);

    component.setFilter('open');
    expect(component.filteredCalleds().map((called) => called.id)).toEqual(['open', 'progress']);

    component.setFilter('all');
    expect(component.filteredCalleds().length).toBe(3);
  });

  it('updates active id on navigation and updates ticket data in list', (): void => {
    calledService.list.mockReturnValue(
      of({
        data: [
          createCalled({
            id: 'called-1',
            updated_at: '2026-01-21T10:00:00.000Z',
            last_message: {
              id: 'msg-1',
              content: 'Oi',
              created_at: '2026-01-21T10:00:00.000Z',
            },
          }),
          createCalled({
            id: 'called-2',
            updated_at: '2026-01-22T08:00:00.000Z',
            last_message: {
              id: 'msg-2',
              content: 'Olá',
              created_at: '2026-01-22T08:00:00.000Z',
            },
          }),
        ],
        meta: { current_page: 1, last_page: 1, per_page: 50, total: 2 },
      }),
    );

    fixture.detectChanges();

    route.setSnapshot(createSnapshot('called-1'));
    router.emit(new NavigationEnd(1, '/chat/1', '/chat/1'));
    expect(component.activeCalledId()).toBe('called-1');

    (
      component as unknown as { updateTicketInList: (ticket: Record<string, unknown>) => void }
    ).updateTicketInList({
      id: 'called-1',
      protocol: 'PROTO-2026-001',
      latest_message: { content: 'Atualizado' },
      updated_at: '2026-01-22T11:00:00.000Z',
    });

    const updated = component.calleds();
    expect(updated[0].id).toBe('called-1');
    expect(updated[0].protocol).toBe('PROTO-2026-001');
    expect(updated[0].last_message?.content).toBe('Atualizado');
  });

  it('formats relative time labels', (): void => {
    const now = new Date();

    expect(component.formatTime(null)).toBe('');
    expect(component.formatTime('invalid-date')).toBe('');
    expect(component.formatTime(new Date(now.getTime() - 30_000).toISOString())).toBe('agora');
    expect(component.formatTime(new Date(now.getTime() - 2 * 60_000).toISOString())).toBe('2 min');
    expect(component.formatTime(new Date(now.getTime() - 3 * 60 * 60_000).toISOString())).toBe(
      '3 h',
    );
    expect(component.formatTime(new Date(now.getTime() - 24 * 60 * 60_000).toISOString())).toBe(
      'ontem',
    );
    expect(component.formatTime(new Date(now.getTime() - 3 * 24 * 60 * 60_000).toISOString())).toBe(
      '3 dias',
    );
  });
});
