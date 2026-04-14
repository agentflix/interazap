import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { ChatWindowComponent } from './chat-window.component';
import { WebChatService } from '../../services/webchat.service';
import { type WebChatMessage } from '../../webchat.model';
import { of, Subject } from 'rxjs';

describe('ChatWindowComponent', () => {
  let component: ChatWindowComponent;
  let fixture: ComponentFixture<ChatWindowComponent>;

  const mockMessages: WebChatMessage[] = [
    {
      id: 'msg-1',
      content: 'Olá! Como posso ajudar?',
      direction: 'incoming',
      source: 'ai',
      type: 'text',
      status: 'delivered',
      createdAt: '2026-04-11T14:32:00.000Z',
      sessionId: 'session-1',
    },
    {
      id: 'msg-2',
      content: 'Preciso de ajuda com meu pedido',
      direction: 'outgoing',
      source: 'visitor',
      type: 'text',
      status: 'sent',
      createdAt: '2026-04-11T14:33:00.000Z',
      sessionId: 'session-1',
    },
  ];

  const aiResponseSubject = new Subject<WebChatMessage>();
  const messageSentSubject = new Subject<{ messageId: string; tempId?: string }>();

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
    aiResponse$: aiResponseSubject.asObservable(),
    messageSent$: messageSentSubject.asObservable(),
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    updateMessageStatus: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    mockMessagesSignal.set([]);

    TestBed.configureTestingModule({
      imports: [ChatWindowComponent],
      providers: [{ provide: WebChatService, useValue: mockWebChatService }],
    });

    fixture = TestBed.createComponent(ChatWindowComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  describe('initialization', () => {
    it('should create', () => {
      expect(component).toBeTruthy();
    });

    it('should initialize with empty sessionId', () => {
      expect(component.sessionId()).toBeNull();
    });

    it('should have default visitor name', () => {
      expect(component.visitorName()).toBe('Visitante');
    });
  });

  describe('init', () => {
    it('should set sessionId and visitorName when called', () => {
      component.init('session-abc', 'Maria');

      expect(component.sessionId()).toBe('session-abc');
      expect(component.visitorName()).toBe('Maria');
    });
  });

  describe('formatTime', () => {
    it('should format ISO string to HH:mm in pt-BR', () => {
      // Use a date in UTC to avoid timezone issues
      const result = component.formatTime('2026-04-11T12:00:00.000Z');
      // Result should be in HH:mm format (pt-BR), exact value depends on timezone
      expect(result).toMatch(/^\d{2}:\d{2}$/);
    });

    it('should return empty string for invalid date', () => {
      const result = component.formatTime('invalid-date');
      // Invalid dates should result in empty string
      expect(result).toBe('');
    });
  });

  describe('getBubbleDirection', () => {
    it('should return out for outgoing messages', () => {
      expect(component.getBubbleDirection('outgoing')).toBe('out');
    });

    it('should return in for incoming messages', () => {
      expect(component.getBubbleDirection('incoming')).toBe('in');
    });
  });

  describe('getStatusForBubble', () => {
    it('should return sent for sent status', () => {
      expect(component.getStatusForBubble('sent')).toBe('sent');
    });

    it('should return delivered for delivered status', () => {
      expect(component.getStatusForBubble('delivered')).toBe('delivered');
    });

    it('should return read for read status', () => {
      expect(component.getStatusForBubble('read')).toBe('read');
    });

    it('should return sent for pending status', () => {
      expect(component.getStatusForBubble('pending')).toBe('sent');
    });

    it('should return sent for failed status', () => {
      expect(component.getStatusForBubble('failed')).toBe('sent');
    });
  });

  describe('trackByMessageId', () => {
    it('should return the message id as track key', () => {
      const msg = { id: 'msg-123' } as WebChatMessage;
      expect(component.trackByMessageId(0, msg)).toBe('msg-123');
    });
  });

  describe('onMessageSent', () => {
    it('should not send if sessionId is null', () => {
      component.sessionId.set(null);
      component.onMessageSent('Hello');

      expect(mockWebChatService.sendMessage).not.toHaveBeenCalled();
    });

    it('should call webchatService.sendMessage with correct params', () => {
      component.init('session-1', 'João');
      component.onMessageSent('Nova mensagem');

      expect(mockWebChatService.sendMessage).toHaveBeenCalledWith(
        'session-1',
        'Nova mensagem',
        expect.any(String),
      );
    });

    it('should trim empty content', () => {
      component.init('session-1', 'João');
      component.onMessageSent('   ');

      expect(mockWebChatService.sendMessage).not.toHaveBeenCalled();
    });
  });

  describe('connection state computed', () => {
    it('should return correct status dot class for connected', () => {
      mockConnectionStateSignal.set('connected');
      fixture.detectChanges();
      expect(component.connectionStatusDot()).toContain('bg-green');
    });
  });
});
