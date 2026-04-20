import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { DestroyRef } from '@angular/core';
import { WebChatService } from './webchat.service';

describe('WebChatService', () => {
  let service: WebChatService;
  let httpMock: HttpTestingController;
  let destroyRef: DestroyRef;

  const mockResponse = {
    token: 'jwt-token-123',
    sessionId: 'session-abc',
    ticketId: 'ticket-xyz',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [WebChatService],
    });

    service = TestBed.inject(WebChatService);
    httpMock = TestBed.inject(HttpTestingController);
    destroyRef = TestBed.inject(DestroyRef);
  });

  afterEach(() => {
    httpMock.verify();
    service.disconnect();
    service.clearSession();
  });

  describe('createSession', () => {
    it('should create a session and store token/sessionId', () => {
      let result: unknown;

      service
        .createSession('my-tenant', 'João Silva', '+5511999999999')
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe((res) => {
          result = res;
        });

      const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/sessions`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({
        tenant_id: 'my-tenant',
        visitor_name: 'João Silva',
        visitor_phone: '+5511999999999',
      });

      req.flush({ success: true, data: mockResponse });

      expect(result).toEqual(mockResponse);
      expect(service['sessionToken']).toBe(mockResponse.token);
      expect(service['sessionId']).toBe(mockResponse.sessionId);
    });

    it('should set error signal on failure', () => {
      let error: unknown;

      service
        .createSession('tenant', 'Name', '+5511')
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe({
          next: () => {},
          error: (err) => {
            error = err;
          },
        });

      const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/sessions`);
      req.error(new ProgressEvent('error'), { status: 500, statusText: 'Server Error' });

      expect(error).toBeTruthy();
      expect(service.error()).toBeTruthy();
    });
  });

  describe('sendMessage', () => {
    it('should send a message and add it optimistically to messages list', () => {
      service['sessionId'] = 'session-abc';
      service['sessionToken'] = 'jwt-token-123';

      let result: unknown;

      service
        .sendMessage('session-abc', 'Olá, precisa de ajuda')
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe((res) => {
          result = res;
        });

      const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/messages`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({
        token: 'jwt-token-123',
        content: 'Olá, precisa de ajuda',
      });

      const messageResponse = { messageId: 'msg-123' };
      req.flush({ success: true, data: messageResponse });

      expect(result).toEqual(messageResponse);
      expect(service.messages().length).toBe(1);
      expect(service.messages()[0].content).toBe('Olá, precisa de ajuda');
      expect(service.messages()[0].status).toBe('pending');
    });

    it('should use tempId when provided for optimistic message', () => {
      service['sessionId'] = 'session-abc';
      service['sessionToken'] = 'jwt-token-123';
      const tempId = 'temp-123';

      service
        .sendMessage('session-abc', 'Test message', tempId)
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe(() => {});

      const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/messages`);
      req.flush({ success: true, data: { messageId: 'msg-real' } });

      expect(service.messages()[0].id).toBe(tempId);
    });

    it('should fail fast with friendly error when token is missing', () => {
      service['sessionId'] = 'session-abc';
      service['sessionToken'] = null;

      let capturedError: unknown = null;

      service
        .sendMessage('session-abc', 'Mensagem sem token')
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe({
          next: () => {},
          error: (err) => {
            capturedError = err;
          },
        });

      httpMock.expectNone(`${service['apiBase']}/api/webchat/messages`);
      expect(capturedError).toBeTruthy();
      expect(capturedError instanceof Error).toBe(true);
      expect((capturedError as Error).message).toBe(
        'Sessão inválida ou expirada. Inicie uma nova conversa para continuar.',
      );
      expect(service.error()).toBe(
        'Sessão inválida ou expirada. Inicie uma nova conversa para continuar.',
      );
    });
  });

  describe('session persistence', () => {
    it('should save and restore session from localStorage', () => {
      service.saveSession('token-xyz', 'session-123');

      // Clear internal state to simulate fresh load
      service.disconnect();

      const restored = service.restoreSession();
      expect(restored).toEqual({ token: 'token-xyz', sessionId: 'session-123' });
    });

    it('should return null for expired sessions', () => {
      const expired = {
        token: 'old-token',
        sessionId: 'old-session',
        expiresAt: Date.now() - 1000, // expired 1 second ago
      };
      localStorage.setItem('webchat_session', JSON.stringify(expired));

      const result = service.restoreSession();
      expect(result).toBeNull();
    });

    it('should clear session from localStorage', () => {
      service.saveSession('token', 'session');
      service.clearSession();

      const result = service.restoreSession();
      expect(result).toBeNull();
    });

    it('should return null and clear storage for malformed persisted session', () => {
      localStorage.setItem(
        'webchat_session',
        JSON.stringify({ sessionId: 'session-123', expiresAt: Date.now() + 60000 }),
      );

      const result = service.restoreSession();
      expect(result).toBeNull();
      expect(localStorage.getItem('webchat_session')).toBeNull();
    });
  });

  describe('connectWebSocket', () => {
    it('should set error state and not create socket when token is invalid', () => {
      service.connectWebSocket('   ');

      expect(service.connectionState()).toBe('error');
      expect(service.error()).toBe('Token de sessão inválido para conexão WebSocket');
      expect(service['socket']).toBeNull();
    });
  });

  describe('connection state signals', () => {
    it('should initialize with disconnected state', () => {
      expect(service.connectionState()).toBe('disconnected');
      expect(service.isConnected()).toBe(false);
    });

    it('should update message status', () => {
      service['sessionId'] = 'session-abc';
      service['sessionToken'] = 'jwt-token-123';

      service
        .sendMessage('session-abc', 'Hello')
        .pipe(takeUntilDestroyed(destroyRef))
        .subscribe(() => {});

      const req = httpMock.expectOne(`${service['apiBase']}/api/webchat/messages`);
      req.flush({ success: true, data: { messageId: 'msg-1' } });

      expect(service.messages()[0].status).toBe('pending');

      service.updateMessageStatus('msg-1', 'sent');
      expect(service.messages()[0].status).toBe('sent');

      service.updateMessageStatus('msg-1', 'delivered');
      expect(service.messages()[0].status).toBe('delivered');
    });
  });

  describe('addMessage', () => {
    it('should add message and avoid duplicates', () => {
      const msg1 = {
        id: 'msg-1',
        content: 'Hello',
        direction: 'incoming' as const,
        type: 'text' as const,
        createdAt: new Date().toISOString(),
        sessionId: 's1',
      };

      service.addMessage(msg1);
      expect(service.messages().length).toBe(1);

      // Duplicate should be ignored
      service.addMessage(msg1);
      expect(service.messages().length).toBe(1);

      const msg2 = { ...msg1, id: 'msg-2' };
      service.addMessage(msg2);
      expect(service.messages().length).toBe(2);
    });
  });
});
