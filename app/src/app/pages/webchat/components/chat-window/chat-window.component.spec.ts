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
  const mockConnectionStateSignal = signal<'connected' | 'disconnected' | 'connecting' | 'error'>(
    'disconnected',
  );
  const mockTicketStatusSignal = signal<'open' | 'closed'>('open');
  const mockIsClosingSignal = signal(false);
  const mockCloseErrorSignal = signal<string | null>(null);

  const mockWebChatService = {
    messages: mockMessagesSignal.asReadonly(),
    isConnected: mockIsConnectedSignal.asReadonly(),
    isAiTyping: mockIsAiTypingSignal.asReadonly(),
    error: mockErrorSignal.asReadonly(),
    connectionState: mockConnectionStateSignal.asReadonly(),
    ticketStatus: mockTicketStatusSignal.asReadonly(),
    isClosing: mockIsClosingSignal.asReadonly(),
    closeError: mockCloseErrorSignal.asReadonly(),
    isClosed: () => mockTicketStatusSignal() === 'closed',
    aiResponse$: aiResponseSubject.asObservable(),
    messageSent$: messageSentSubject.asObservable(),
    sendMessage: vi.fn().mockReturnValue(of({ messageId: 'msg-new' })),
    closeTicket: vi.fn().mockReturnValue(of({ ticketId: 'ticket-1', status: 'closed' })),
    updateMessageStatus: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    mockMessagesSignal.set([]);
    mockTicketStatusSignal.set('open');
    mockIsClosingSignal.set(false);
    mockCloseErrorSignal.set(null);

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

    it('should not send when ticket is closed', () => {
      component.init('session-1', 'João');
      mockTicketStatusSignal.set('closed');

      component.onMessageSent('mensagem');

      expect(mockWebChatService.sendMessage).not.toHaveBeenCalled();
    });
  });

  describe('close ticket flow', () => {
    it('should render close button when ticket is open', () => {
      component.init('session-1', 'João');
      mockTicketStatusSignal.set('open');
      fixture.detectChanges();

      const buttons = fixture.nativeElement.querySelectorAll(
        'button',
      ) as NodeListOf<HTMLButtonElement>;
      const closeButton = Array.from(buttons).find((btn) =>
        btn.textContent?.includes('Encerrar chamado'),
      );

      expect(closeButton).toBeTruthy();
    });

    it('should hide composer and show closed message when ticket is closed', () => {
      component.init('session-1', 'João');
      mockTicketStatusSignal.set('closed');
      fixture.detectChanges();

      const composer = fixture.nativeElement.querySelector('af-chat-composer');
      expect(composer).toBeNull();
      expect(fixture.nativeElement.textContent).toContain('Este chamado foi encerrado.');
    });

    it('should open and close modal through handlers', () => {
      expect(component.isCloseModalOpen()).toBe(false);

      component.openCloseModal();
      expect(component.isCloseModalOpen()).toBe(true);

      component.closeCloseModal();
      expect(component.isCloseModalOpen()).toBe(false);
    });

    it('should confirm close and hide modal on success', () => {
      component.openCloseModal();
      expect(component.isCloseModalOpen()).toBe(true);

      component.confirmClose();

      expect(mockWebChatService.closeTicket).toHaveBeenCalled();
      expect(component.isCloseModalOpen()).toBe(false);
    });

    it('should not open modal while closing', () => {
      mockIsClosingSignal.set(true);

      component.openCloseModal();

      expect(component.isCloseModalOpen()).toBe(false);
    });

    it('should render close error banner when closeError is set', () => {
      mockCloseErrorSignal.set('Falha ao encerrar');
      fixture.detectChanges();

      expect(fixture.nativeElement.textContent).toContain('Falha ao encerrar');
    });

    // CA-002 — evidência explícita: histórico visível + composer oculto após fechamento
    it('should keep message history visible and hide composer after successful close', () => {
      // Arrange: pré-carregar mensagens históricas e inicializar sessão
      mockMessagesSignal.set(mockMessages);
      component.init('session-1', 'João');
      fixture.detectChanges();

      // Confirma que as mensagens estão sendo renderizadas antes do fechamento
      const bubblesBeforeClose = fixture.nativeElement.querySelectorAll(
        'af-chat-bubble',
      ) as NodeListOf<Element>;
      expect(bubblesBeforeClose.length).toBe(mockMessages.length);

      // Act: simular fechamento bem-sucedido (ticketStatus → 'closed')
      mockTicketStatusSignal.set('closed');
      fixture.detectChanges();

      // Assert 1: histórico de mensagens permanece renderizado
      const bubblesAfterClose = fixture.nativeElement.querySelectorAll(
        'af-chat-bubble',
      ) as NodeListOf<Element>;
      expect(bubblesAfterClose.length).toBe(mockMessages.length);

      // Assert 2: composer está oculto
      const composer = fixture.nativeElement.querySelector('af-chat-composer');
      expect(composer).toBeNull();

      // Assert 3: mensagem de encerramento exibida
      expect(fixture.nativeElement.textContent).toContain('Este chamado foi encerrado.');
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
