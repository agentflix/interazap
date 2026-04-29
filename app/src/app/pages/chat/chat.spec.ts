import { signal } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Observable, Subject, of } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AppShellService } from 'src/app/core/services/app-shell.service';
import { CalledService } from 'src/app/core/services/called.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { type ChatQuickAnswer } from './models/chat-quick-answer.model';
import { ChatQuickAnswerService } from './services/chat-quick-answer.service';
import { ChatRecorderService } from './services/chat-recorder.service';
import { Chat } from './chat';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { ChatPresenceService } from 'src/app/core/services/chat-presence.service';
import { ChatMediaBatchService } from 'src/app/core/services/chat-media-batch.service';
import { NativeBridgeService } from 'src/app/core/services/platform/native-bridge.service';
import { PlatformService } from 'src/app/core/services/platform/platform.service';
import { MessageSendService } from './services/message-send.service';

class AppShellServiceStub {
  hideFooter = vi.fn();
  disableContentScroll = vi.fn();
  showFooter = vi.fn();
  enableContentScroll = vi.fn();
}

class CalledMessageServiceStub {
  list = vi.fn().mockReturnValue(of({ data: { messages: [] } }));
}

class CalledServiceStub {
  list = vi
    .fn()
    .mockReturnValue(of({ data: [], meta: { current_page: 1, total: 0, last_page: 1 } }));
  counts = vi
    .fn()
    .mockReturnValue(of({ data: { all: 0, pending: 0, open: 0, closed: 0, in_progress: 0 } }));
  get = vi.fn().mockReturnValue(of({ data: null }));
  open = vi.fn().mockReturnValue(of({ data: null }));
  transferToUser = vi.fn().mockReturnValue(of({ data: { id: 'transfer-1' } }));
}

class ChatRefreshServiceStub {
  request = vi.fn();
}

class ChatPresenceServiceStub {
  startTyping = vi.fn().mockReturnValue(of(null));
  startRecording = vi.fn().mockReturnValue(of(null));
  stop = vi.fn().mockReturnValue(of(null));
}

class ChatMediaBatchServiceStub {
  dispatchBatch = vi.fn().mockReturnValue(of(void 0));
}

class ChatQuickAnswerServiceStub {
  list = vi.fn().mockReturnValue(of([]));
}

class ChatRecorderServiceStub {
  recordingCompleted$ = of();
  state = signal('text');
  duration = signal(0);
  waveforms = signal<number[]>([]);
  start = vi.fn().mockResolvedValue(true);
  pause = vi.fn();
  resume = vi.fn();
  cancel = vi.fn();
  stop = vi.fn();
}

class RealtimeServiceStub {
  private readonly subjects = new Map<string, Subject<unknown>>();
  connect = vi.fn();

  on<T = unknown>(eventName: string): Observable<T> {
    let subject = this.subjects.get(eventName);
    if (!subject) {
      subject = new Subject<unknown>();
      this.subjects.set(eventName, subject);
    }

    return subject.asObservable() as Observable<T>;
  }

  emit(eventName: string, payload: unknown): void {
    this.subjects.get(eventName)?.next(payload);
  }
}

class ActivatedRouteStub {
  paramMap = of(convertToParamMap({}));
  snapshot = { paramMap: convertToParamMap({}) };
}

class NativeBridgeServiceStub {
  capturePhoto = vi.fn();
  pickPhotoFromGallery = vi.fn();
}

class PlatformServiceStub {
  get isMobile(): boolean {
    return false;
  }
}

class MessageSendServiceStub {
  initialize = vi.fn(async () => undefined);
  pendingCountForTicket = vi.fn().mockReturnValue(0);
  queueDelivered$ = new Subject<{
    queueId: string;
    calledId: string;
    clientMessageId: string;
    message: { id: string; direction?: 'incoming' | 'outgoing'; status?: string };
  }>();
  queueFailed$ = new Subject<{ queueId: string; calledId: string; clientMessageId: string }>();
  isFlushing = signal(false);
  sendText = vi.fn().mockReturnValue(
    of({
      status: 'sent',
      clientMessageId: 'temp-1',
      message: { id: 'msg-1', direction: 'outgoing', status: 'sent' },
    }),
  );
}

describe('Chat', () => {
  let component: Chat;
  let quickAnswerService: ChatQuickAnswerServiceStub;
  let appShellService: AppShellServiceStub;

  const answers: ChatQuickAnswer[] = [
    { id: '1', shortcut: 'oi', content: 'Olá, tudo bem?', is_active: true },
    { id: '2', shortcut: 'pix', content: 'Chave PIX: 12.345', is_active: true },
  ];

  beforeEach(() => {
    vi.useFakeTimers();

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AppShellService, useClass: AppShellServiceStub },
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: ChatRefreshService, useClass: ChatRefreshServiceStub },
        { provide: ChatPresenceService, useClass: ChatPresenceServiceStub },
        { provide: ChatMediaBatchService, useClass: ChatMediaBatchServiceStub },
        { provide: ChatQuickAnswerService, useClass: ChatQuickAnswerServiceStub },
        { provide: ChatRecorderService, useClass: ChatRecorderServiceStub },
        { provide: RealtimeService, useClass: RealtimeServiceStub },
        { provide: ActivatedRoute, useClass: ActivatedRouteStub },
        { provide: NativeBridgeService, useClass: NativeBridgeServiceStub },
        { provide: PlatformService, useClass: PlatformServiceStub },
        { provide: MessageSendService, useClass: MessageSendServiceStub },
      ],
    });

    quickAnswerService = TestBed.inject(
      ChatQuickAnswerService,
    ) as unknown as ChatQuickAnswerServiceStub;
    appShellService = TestBed.inject(AppShellService) as unknown as AppShellServiceStub;

    quickAnswerService.list.mockReturnValue(of(answers));

    component = TestBed.runInInjectionContext(() => new Chat());
    component.ngOnInit();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('creates component and initializes shell behavior', () => {
    expect(component).toBeTruthy();
    expect(appShellService.disableContentScroll).toHaveBeenCalled();
  });

  it('should call dedicated transfer endpoint with reason payload', () => {
    const calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;

    component.onTransferConfirmed({
      ticketId: 'ticket-1',
      toUserId: 'user-2',
      reason: 'Repasse interno',
    });

    expect(calledService.transferToUser).toHaveBeenCalledWith('ticket-1', {
      to_user_id: 'user-2',
      reason: 'Repasse interno',
    });
  });

  it('should refetch ticket after dedicated transfer succeeds', () => {
    const calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    calledService.get.mockReturnValue(of({ data: { id: 'ticket-1', status: 'open' } }));

    component.onTransferConfirmed({
      ticketId: 'ticket-1',
      toUserId: 'user-2',
      reason: 'Repasse interno',
    });

    expect(calledService.get).toHaveBeenCalledWith('ticket-1');
  });

  it('should wire transfer loading and error state from container to modal', () => {
    const calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    calledService.transferToUser.mockReturnValueOnce(
      new Observable((subscriber) => {
        subscriber.error(new Error('transfer failed'));
      }),
    );

    component.onTransferConfirmed({
      ticketId: 'ticket-1',
      toUserId: 'user-2',
      reason: 'Repasse interno',
    });

    expect(component.isTransferLoading()).toBe(false);
    expect(component.transferError()).toBe(
      'Não foi possível transferir o chamado. Tente novamente.',
    );
  });

  it('should request ticket list refresh when chat.activity contains ticket.new', () => {
    const realtime = TestBed.inject(RealtimeService) as unknown as RealtimeServiceStub;
    const chatRefresh = TestBed.inject(ChatRefreshService) as unknown as ChatRefreshServiceStub;
    chatRefresh.request.mockClear();

    realtime.emit('chat.activity', {
      subevents: [
        { type: 'msg.received', data: {} },
        { type: 'ticket.new', data: { ticket_id: 'ticket-123' } },
      ],
    });

    expect(chatRefresh.request).toHaveBeenCalledTimes(1);
  });

  it('should request ticket list refresh when chat.activity contains ticket.updated', () => {
    const realtime = TestBed.inject(RealtimeService) as unknown as RealtimeServiceStub;
    const chatRefresh = TestBed.inject(ChatRefreshService) as unknown as ChatRefreshServiceStub;
    chatRefresh.request.mockClear();

    realtime.emit('chat.activity', {
      subevents: [
        {
          type: 'ticket.updated',
          data: {
            ticket_id: 'ticket-123',
            event_type: 'ticket_closed',
            ticket: { id: 'ticket-123', status: 'closed' },
          },
        },
      ],
    });

    expect(chatRefresh.request).toHaveBeenCalledTimes(1);
  });
});
