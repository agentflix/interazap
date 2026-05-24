import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { WebChatPageComponent } from './webchat-page.component';
import { WebChatService } from './services/webchat.service';
import { type WebChatMessage } from './webchat.model';
import { ActivatedRoute, Router } from '@angular/router';
import { PreChatComponent } from './components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from './components/chat-window/chat-window.component';
import { of } from 'rxjs';

function createMockService(restoreSessionReturn: unknown = null) {
  const mockMessagesSignal = signal<WebChatMessage[]>([]);
  const mockIsConnectedSignal = signal(false);
  const mockIsAiTypingSignal = signal(false);
  const mockErrorSignal = signal<string | null>(null);
  const mockConnectionStateSignal = signal<'connected' | 'disconnected' | 'connecting' | 'error'>('disconnected');
  const mockTicketStatusSignal = signal<'open' | 'closed'>('open');
  const mockIsClosingSignal = signal(false);
  const mockCloseErrorSignal = signal<string | null>(null);

  return {
    messages: mockMessagesSignal.asReadonly(),
    isConnected: mockIsConnectedSignal.asReadonly(),
    isAiTyping: mockIsAiTypingSignal.asReadonly(),
    error: mockErrorSignal.asReadonly(),
    connectionState: mockConnectionStateSignal.asReadonly(),
    ticketStatus: mockTicketStatusSignal.asReadonly(),
    isClosing: mockIsClosingSignal.asReadonly(),
    closeError: mockCloseErrorSignal.asReadonly(),
    isClosed: () => mockTicketStatusSignal() === 'closed',
    restoreSession: vi.fn().mockReturnValue(restoreSessionReturn),
    connectWebSocket: vi.fn(),
    disconnect: vi.fn(),
    clearSession: vi.fn(),
    aiResponse$: of(),
    messageSent$: of(),
    ticketClosedByAgent$: of(),
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    updateMessageStatus: vi.fn(),
    fetchSessionMessages: vi.fn().mockReturnValue(of([])),
  };
}

const mockRouter = {
  navigate: vi.fn().mockResolvedValue(true),
};

describe('WebChatPageComponent', () => {
  let component: WebChatPageComponent;
  let fixture: ComponentFixture<WebChatPageComponent>;

  describe('initial state', () => {
    beforeEach(async () => {
      vi.clearAllMocks();
      const mockService = createMockService();

      TestBed.configureTestingModule({
        imports: [WebChatPageComponent],
        providers: [
          { provide: WebChatService, useValue: mockService },
          {
            provide: ActivatedRoute,
            useValue: {
              snapshot: {
                paramMap: { get: () => 'test-tenant' },
                queryParamMap: { get: () => null },
              },
            },
          },
          { provide: Router, useValue: mockRouter },
        ],
      });

      fixture = TestBed.createComponent(WebChatPageComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();
    });

    it('should start without a session', () => {
      expect(component.hasSession()).toBe(false);
    });

    it('should show pre-chat initially when no session restored', () => {
      expect(component.showPreChat()).toBe(true);
    });

    it('should not show chat initially', () => {
      expect(component.showChat()).toBe(false);
    });
  });

  describe('session restoration', () => {
    it('should attempt to restore session on init', () => {
      vi.clearAllMocks();
      const mockService = createMockService();

      TestBed.configureTestingModule({
        imports: [WebChatPageComponent],
        providers: [
          { provide: WebChatService, useValue: mockService },
          {
            provide: ActivatedRoute,
            useValue: {
              snapshot: {
                paramMap: { get: () => 'test-tenant' },
                queryParamMap: { get: () => null },
              },
            },
          },
          { provide: Router, useValue: mockRouter },
        ],
      });

      fixture = TestBed.createComponent(WebChatPageComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();

      expect(mockService.restoreSession).toHaveBeenCalled();
    });

    it('should restore session from URL query param when provided', () => {
      vi.clearAllMocks();
      const mockService = createMockService({
        token: 'restored-token',
        sessionId: 'session-from-url',
        contactName: 'Test User',
        contactPhone: '+5511999999999',
        protocol: 'ABC123',
      });

      TestBed.configureTestingModule({
        imports: [WebChatPageComponent],
        providers: [
          { provide: WebChatService, useValue: mockService },
          {
            provide: ActivatedRoute,
            useValue: {
              snapshot: {
                paramMap: { get: () => 'test-tenant' },
                queryParamMap: { get: (key: string) => key === 's' ? 'session-from-url' : null },
              },
            },
          },
          { provide: Router, useValue: mockRouter },
        ],
      });

      fixture = TestBed.createComponent(WebChatPageComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();

      expect(mockService.restoreSession).toHaveBeenCalledWith('session-from-url');
      expect(mockService.connectWebSocket).toHaveBeenCalledWith('restored-token', 'session-from-url');
      expect(component.hasSession()).toBe(true);
    });
  });

  describe('onSessionReady', () => {
    beforeEach(async () => {
      vi.clearAllMocks();
      const mockService = createMockService();

      TestBed.configureTestingModule({
        imports: [WebChatPageComponent],
        providers: [
          { provide: WebChatService, useValue: mockService },
          {
            provide: ActivatedRoute,
            useValue: {
              snapshot: {
                paramMap: { get: () => 'test-tenant' },
                queryParamMap: { get: () => null },
              },
            },
          },
          { provide: Router, useValue: mockRouter },
        ],
      });

      fixture = TestBed.createComponent(WebChatPageComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();
    });

    it('should set hasSession to true', () => {
      component.onSessionReady({ token: 'tok', sessionId: 'sess' });
      expect(component.hasSession()).toBe(true);
    });

    it('should set visitorName from session data', () => {
      component.onSessionReady({
        token: 'tok',
        sessionId: 'sess',
        contactName: 'Maria Silva',
        contactPhone: '+5511988887777',
        protocol: 'XYZ789',
      });

      expect(component.visitorName()).toBe('Maria Silva');
      expect(component.visitorPhone()).toBe('+5511988887777');
      expect(component.protocol()).toBe('XYZ789');
    });
  });

  describe('onTicketClosed', () => {
    beforeEach(async () => {
      vi.clearAllMocks();
      const mockService = createMockService();

      TestBed.configureTestingModule({
        imports: [WebChatPageComponent],
        providers: [
          { provide: WebChatService, useValue: mockService },
          {
            provide: ActivatedRoute,
            useValue: {
              snapshot: {
                paramMap: { get: () => 'test-tenant' },
                queryParamMap: { get: () => null },
              },
            },
          },
          { provide: Router, useValue: mockRouter },
        ],
      });

      fixture = TestBed.createComponent(WebChatPageComponent);
      component = fixture.componentInstance;
      fixture.detectChanges();
    });

    it('should clear session and disconnect', () => {
      component.hasSession.set(true);
      component.onTicketClosed();

      const service = TestBed.inject(WebChatService);
      expect(service.clearSession).toHaveBeenCalled();
      expect(service.disconnect).toHaveBeenCalled();
      expect(component.hasSession()).toBe(false);
    });

    it('should navigate to remove session param from URL', () => {
      component.onTicketClosed();

      expect(mockRouter.navigate).toHaveBeenCalledWith(
        [],
        expect.objectContaining({
          queryParams: { s: null },
          queryParamsHandling: 'merge',
          replaceUrl: true,
        }),
      );
    });
  });
});
