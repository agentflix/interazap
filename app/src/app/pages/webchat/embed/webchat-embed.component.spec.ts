import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { WebChatEmbedComponent } from './webchat-embed.component';
import { WebChatService } from '../services/webchat.service';
import { PreChatComponent } from '../components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from '../components/chat-window/chat-window.component';
import { type WebChatMessage } from '../webchat.model';
import { of } from 'rxjs';

describe('WebChatEmbedComponent', () => {
  let component: WebChatEmbedComponent;
  let fixture: ComponentFixture<WebChatEmbedComponent>;

  // Signals for mock service state
  const mockMessagesSignal = signal<WebChatMessage[]>([]);
  const mockIsConnectedSignal = signal(false);
  const mockIsAiTypingSignal = signal(false);
  const mockErrorSignal = signal<string | null>(null);
  const mockConnectionStateSignal = signal<'connected' | 'disconnected' | 'connecting' | 'error'>(
    'disconnected',
  );

  const mockWebChatService = {
    messages: mockMessagesSignal.asReadonly(),
    isConnected: mockIsConnectedSignal.asReadonly(),
    isAiTyping: mockIsAiTypingSignal.asReadonly(),
    error: mockErrorSignal.asReadonly(),
    connectionState: mockConnectionStateSignal.asReadonly(),
    restoreSession: vi.fn().mockReturnValue(null),
    connectWebSocket: vi.fn(),
    disconnect: vi.fn(),
    aiResponse$: of(),
    messageSent$: of(),
    ticketClosedByAgent$: of(),
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    updateMessageStatus: vi.fn(),
    fetchSessionMessages: vi.fn().mockReturnValue(of([])),
    ticketStatus: signal<'open' | 'closed'>('open').asReadonly(),
    isClosing: signal(false).asReadonly(),
    closeError: signal<string | null>(null).asReadonly(),
    isClosed: () => false,
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    mockMessagesSignal.set([]);
    mockIsConnectedSignal.set(false);

    TestBed.configureTestingModule({
      imports: [WebChatEmbedComponent],
      providers: [
        { provide: WebChatService, useValue: mockWebChatService },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: { get: () => 'tenant-test' } } },
        },
      ],
    });

    fixture = TestBed.createComponent(WebChatEmbedComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should show pre-chat when no session', () => {
    expect(component.showPreChat()).toBe(true);
  });

  it('should disconnect service on destroy (embed cleanup)', () => {
    component.ngOnDestroy();
    expect(mockWebChatService.disconnect).toHaveBeenCalled();
  });

  it('should connect WebSocket on session ready', () => {
    component.onSessionReady({ token: 'tok', sessionId: 'sess' });
    expect(component.hasSession()).toBe(true);
  });

  describe('computed states', () => {
    it('showPreChat is true when no session and not restoring', () => {
      component.hasSession.set(false);
      component.isRestoring.set(false);
      expect(component.showPreChat()).toBe(true);
      expect(component.showChat()).toBe(false);
    });

    it('showChat is true when session exists', () => {
      component.hasSession.set(true);
      component.isRestoring.set(false);
      expect(component.showChat()).toBe(true);
      expect(component.showPreChat()).toBe(false);
    });

    it('both false while restoring', () => {
      component.hasSession.set(false);
      component.isRestoring.set(true);
      expect(component.showPreChat()).toBe(false);
      expect(component.showChat()).toBe(false);
    });
  });

  describe('session restore', () => {
    it('should attempt session restore on init', () => {
      expect(mockWebChatService.restoreSession).toHaveBeenCalled();
    });

    it('should restore session and connect WebSocket when session exists', () => {
      vi.mocked(mockWebChatService.restoreSession).mockReturnValue({
        token: 'restored-token',
        sessionId: 'restored-session',
      });

      // Re-create component to trigger ngOnInit with mocked restoreSession
      TestBed.resetTestingModule();
      TestBed.configureTestingModule({
        imports: [WebChatEmbedComponent],
        providers: [
          { provide: WebChatService, useValue: mockWebChatService },
          {
            provide: ActivatedRoute,
            useValue: { snapshot: { paramMap: { get: () => 'tenant-test' } } },
          },
        ],
      });

      fixture = TestBed.createComponent(WebChatEmbedComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();

      expect(mockWebChatService.connectWebSocket).toHaveBeenCalledWith(
        'restored-token',
        'restored-session',
      );
      expect(component.hasSession()).toBe(true);
    });
  });
});
