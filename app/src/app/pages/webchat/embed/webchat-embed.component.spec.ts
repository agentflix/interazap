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
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    updateMessageStatus: vi.fn(),
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

  it('should create', () => {
    expect(component).toBeTruthy();
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
});
