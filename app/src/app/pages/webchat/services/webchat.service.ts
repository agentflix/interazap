import { type OnDestroy, Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable, Subject, throwError } from 'rxjs';
import { catchError, finalize, map, tap } from 'rxjs/operators';
import { io, type Socket } from 'socket.io-client';
import {
  type WebChatMessage,
  type WebChatMessageRequest,
  type WebChatMessageResponse,
  type WebChatSessionResponse,
  type WebChatCloseResponse,
  type WebChatConnectionState,
  type WebChatTicketStatus,
} from '../webchat.model';
import { environment } from '@env/environment';

const WEBCHAT_PATH = '/ws/webchat';

/**
 * WebChatService — manages session creation, WebSocket communication,
 * and message state for the public webchat widget.
 */
@Injectable({ providedIn: 'root' })
export class WebChatService implements OnDestroy {
  private readonly http = inject(HttpClient);

  // ─── Socket.io state ───────────────────────────────────────────────────────
  private socket: Socket | null = null;
  private sessionToken: string | null = null;
  private sessionId: string | null = null;

  // ─── Reactive state (signals) ──────────────────────────────────────────────
  private readonly _connectionState = signal<WebChatConnectionState>('disconnected');
  private readonly _messages = signal<WebChatMessage[]>([]);
  private readonly _isAiTyping = signal(false);
  private readonly _error = signal<string | null>(null);
  private readonly _ticketStatus = signal<WebChatTicketStatus>('open');
  private readonly _isClosing = signal(false);
  private readonly _closeError = signal<string | null>(null);

  // Public readonly signals
  readonly connectionState = this._connectionState.asReadonly();
  readonly messages = this._messages.asReadonly();
  readonly isAiTyping = this._isAiTyping.asReadonly();
  readonly error = this._error.asReadonly();
  readonly ticketStatus = this._ticketStatus.asReadonly();
  readonly isClosing = this._isClosing.asReadonly();
  readonly closeError = this._closeError.asReadonly();

  readonly isConnected = computed(() => this._connectionState() === 'connected');
  readonly hasMessages = computed(() => this._messages().length > 0);
  readonly isClosed = computed(() => this._ticketStatus() === 'closed');

  // ─── Event streams ────────────────────────────────────────────────────────
  private readonly _aiResponse$ = new Subject<WebChatMessage>();
  private readonly _messageSent$ = new Subject<{ messageId: string; tempId?: string }>();
  private readonly _joined$ = new Subject<string>();

  readonly aiResponse$ = this._aiResponse$.asObservable();
  readonly messageSent$ = this._messageSent$.asObservable();
  readonly joined$ = this._joined$.asObservable();

  // ─── API Base URL ─────────────────────────────────────────────────────────
  // Use || instead of ?? so an empty-string url falls back to the current origin
  // (Angular dev-server proxy forwards /ws to the NestJS gateway).
  private readonly apiBase = environment.gateway?.url || window.location.origin;

  // API responses come in `{ success, message, data }` envelope.
  private unwrapData<T>(response: unknown): T {
    const maybeEnvelope = response as { data?: unknown };
    const payload = maybeEnvelope?.data ?? response;
    return payload as T;
  }

  // ─── Public API ───────────────────────────────────────────────────────────

  /**
   * Creates a new webchat session for a tenant.
   * POST /api/webchat/sessions
   */
  createSession(
    tenantId: string,
    name: string,
    whatsapp: string,
  ): Observable<WebChatSessionResponse> {
    const body = {
      tenant_id: tenantId,
      visitor_name: name,
      visitor_phone: whatsapp,
    };
    return this.http.post<unknown>(`${this.apiBase}/api/webchat/sessions`, body).pipe(
      map((response) => this.unwrapData<WebChatSessionResponse>(response)),
      tap((response) => {
        this.sessionToken = response.token;
        this.sessionId = response.sessionId;
        this._ticketStatus.set('open');
        this._closeError.set(null);
      }),
      catchError((err) => {
        this._error.set(err?.error?.message ?? 'Falha ao criar sessão');
        throw err;
      }),
    );
  }

  /**
   * Sends a text message through the REST API (WebSocket is used for streaming responses).
   * POST /api/webchat/messages
   */
  sendMessage(
    sessionId: string,
    content: string,
    tempId?: string,
  ): Observable<WebChatMessageResponse> {
    const token = this.sessionToken?.trim();
    if (!token) {
      const message = 'Sessão inválida ou expirada. Inicie uma nova conversa para continuar.';
      this._error.set(message);
      return throwError(() => new Error(message));
    }

    const body: WebChatMessageRequest = { token, content };
    return this.http.post<unknown>(`${this.apiBase}/api/webchat/messages`, body).pipe(
      map((response) => this.unwrapData<WebChatMessageResponse>(response)),
      tap((response) => {
        // Optimistically add pending message to local state
        const optimisticMessage: WebChatMessage = {
          id: tempId ?? response.messageId,
          content,
          direction: 'outgoing',
          source: 'visitor',
          type: 'text',
          status: 'pending',
          createdAt: new Date().toISOString(),
          sessionId,
        };
        this.addMessage(optimisticMessage);
        this._messageSent$.next({ messageId: response.messageId, tempId });
      }),
      catchError((err) => {
        this._error.set(err?.error?.message ?? 'Falha ao enviar mensagem');
        throw err;
      }),
    );
  }

  /**
   * Closes the current webchat ticket using the public endpoint.
   * POST /api/webchat/close
   */
  closeTicket(): Observable<WebChatCloseResponse> {
    const token = this.sessionToken?.trim();
    if (!token) {
      const message = 'Sessão inválida ou expirada. Inicie uma nova conversa para continuar.';
      this._closeError.set(message);
      return throwError(() => new Error(message));
    }

    this._isClosing.set(true);
    this._closeError.set(null);

    return this.http.post<unknown>(`${this.apiBase}/api/webchat/close`, { token }).pipe(
      map((response) => this.unwrapData<WebChatCloseResponse>(response)),
      tap((response) => {
        this._ticketStatus.set(response.status);
      }),
      catchError((err) => {
        this._closeError.set(err?.error?.message ?? 'Falha ao encerrar chamado');
        throw err;
      }),
      finalize(() => {
        this._isClosing.set(false);
      }),
    );
  }

  /**
   * Connects to the WebSocket gateway and joins the session room.
   * Uses the token from createSession(), or pass sessionId explicitly when restoring a session.
   */
  connectWebSocket(token: string, sessionId?: string): void {
    if (this.socket?.connected) {
      return;
    }

    if (typeof token !== 'string' || token.trim() === '') {
      this._connectionState.set('error');
      this._error.set('Token de sessão inválido para conexão WebSocket');
      return;
    }

    this._connectionState.set('connecting');
    this.sessionToken = token.trim();
    // When restoring from localStorage, sessionId is not set via createSession() tap.
    if (sessionId && typeof sessionId === 'string' && sessionId.trim() !== '') {
      this.sessionId = sessionId.trim();
    }

    const gatewayUrl = this.apiBase;

    this.socket = io(gatewayUrl, {
      path: environment.gateway?.path ?? WEBCHAT_PATH,
      auth: { token: this.sessionToken },
      transports: ['websocket'],
      reconnection: true,
      reconnectionAttempts: 5,
      reconnectionDelay: 1000,
      reconnectionDelayMax: 5000,
      timeout: 10000,
    });

    this.bindSocketListeners();
  }

  /**
   * Disconnects from the WebSocket and clears session data.
   */
  disconnect(): void {
    this.socket?.disconnect();
    this.socket = null;
    this.sessionToken = null;
    this.sessionId = null;
    this._connectionState.set('disconnected');
    this._messages.set([]);
    this._isAiTyping.set(false);
    this._ticketStatus.set('open');
    this._isClosing.set(false);
    this._closeError.set(null);
  }

  /**
   * Adds a message to the local messages list.
   */
  addMessage(message: WebChatMessage): void {
    this._messages.update((msgs) => {
      // Avoid duplicates
      if (msgs.some((m) => m.id === message.id)) {
        return msgs;
      }
      return [...msgs, message];
    });
  }

  /**
   * Updates a message status by its ID.
   */
  updateMessageStatus(messageId: string, status: WebChatMessage['status']): void {
    this._messages.update((msgs) => msgs.map((m) => (m.id === messageId ? { ...m, status } : m)));
  }

  /**
   * Loads a restored session from localStorage.
   */
  restoreSession(): { token: string; sessionId: string } | null {
    try {
      const raw = localStorage.getItem('webchat_session');
      if (!raw) return null;
      const parsed = JSON.parse(raw) as {
        token?: string;
        sessionId?: string;
        expiresAt?: number;
      };
      if (typeof parsed.expiresAt !== 'number' || Date.now() > parsed.expiresAt) {
        localStorage.removeItem('webchat_session');
        return null;
      }
      if (
        typeof parsed.token !== 'string' ||
        parsed.token.trim() === '' ||
        typeof parsed.sessionId !== 'string' ||
        parsed.sessionId.trim() === ''
      ) {
        localStorage.removeItem('webchat_session');
        return null;
      }
      return { token: parsed.token, sessionId: parsed.sessionId };
    } catch {
      return null;
    }
  }

  /**
   * Persists the session to localStorage with a 4-hour expiry.
   */
  saveSession(token: string, sessionId: string): void {
    const expiresAt = Date.now() + 4 * 60 * 60 * 1000; // 4 hours
    localStorage.setItem('webchat_session', JSON.stringify({ token, sessionId, expiresAt }));
  }

  /**
   * Clears the persisted session from localStorage.
   */
  clearSession(): void {
    localStorage.removeItem('webchat_session');
  }

  ngOnDestroy(): void {
    this.disconnect();
  }

  // ─── Private ───────────────────────────────────────────────────────────────

  private bindSocketListeners(): void {
    if (!this.socket) return;

    this.socket.on('connect', () => {
      this._connectionState.set('connected');
      // Join the session room
      if (this.sessionId) {
        this.socket?.emit('webchat:join', { sessionId: this.sessionId });
      }
    });

    this.socket.on('disconnect', (reason: string) => {
      this._connectionState.set('disconnected');
      // Prevent reconnect loops when server disconnects explicitly
      // (typically due to auth rejection).
      if (reason === 'io server disconnect') return;
    });

    this.socket.on('connect_error', (err: Error) => {
      this._connectionState.set('error');
      this._error.set(err?.message ?? 'Erro de conexão');
    });

    // Server confirms join
    this.socket.on('webchat:joined', (data: { sessionId: string }) => {
      this._joined$.next(data.sessionId);
    });

    // Message sent confirmation
    this.socket.on('webchat:sent', (data: { messageId: string; tempId?: string }) => {
      this.updateMessageStatus(data.tempId ?? data.messageId, 'sent');
      this._messageSent$.next(data);
    });

    // AI / agent response
    this.socket.on('webchat:ai_response', (data: WebChatMessage) => {
      this._isAiTyping.set(false);
      const message: WebChatMessage = {
        ...data,
        direction: 'incoming',
        source: data.source ?? 'ai',
        status: 'delivered',
      };
      this.addMessage(message);
      this._aiResponse$.next(message);
    });

    // Typing indicator
    this.socket.on('webchat:typing', (data: { isTyping: boolean; source?: 'ai' | 'agent' }) => {
      this._isAiTyping.set(data.isTyping);
    });

    // Error event
    this.socket.on('webchat:error', (data: { code: string; message: string }) => {
      this._error.set(data.message);
    });
  }
}
