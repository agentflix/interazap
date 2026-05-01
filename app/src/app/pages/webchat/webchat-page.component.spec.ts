import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { WebChatPageComponent } from './webchat-page.component';
import { WebChatService } from './services/webchat.service';
import { type WebChatMessage } from './webchat.model';
import { ActivatedRoute } from '@angular/router';
import { PreChatComponent } from './components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from './components/chat-window/chat-window.component';
import { of } from 'rxjs';

describe('WebChatPageComponent', () => {
  let component: WebChatPageComponent;
  let fixture: ComponentFixture<WebChatPageComponent>;

  // Signals for mutable state
  const mockMessagesSignal = signal<WebChatMessage[]>([]);
  const mockIsConnectedSignal = signal(false);
  const mockIsAiTypingSignal = signal(false);
  const mockErrorSignal = signal<string | null>(null);
  const mockConnectionStateSignal = signal<'connected' | 'disconnected' | 'connecting' | 'error'>('disconnected');

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
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    updateMessageStatus: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    mockMessagesSignal.set([]);
    mockIsConnectedSignal.set(false);

    TestBed.configureTestingModule({
      imports: [WebChatPageComponent],
      providers: [
        { provide: WebChatService, useValue: mockWebChatService },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: () => 'test-tenant' },
              queryParamMap: { get: () => null },
            },
          },
        },
      ],
    });

    fixture = TestBed.createComponent(WebChatPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  describe('initial state', () => {
    it('should create', () => {
      expect(component).toBeTruthy();
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
      expect(mockWebChatService.restoreSession).toHaveBeenCalled();
    });
  });

  describe('onSessionReady', () => {
    it('should set hasSession to true', () => {
      component.onSessionReady({ token: 'tok', sessionId: 'sess' });

      expect(component.hasSession()).toBe(true);
    });

    it('should set hasSession to true with provided sessionId', () => {
      component.onSessionReady({ token: 'tok', sessionId: 'sess' });

      expect(component.hasSession()).toBe(true);
    });
  });
});
