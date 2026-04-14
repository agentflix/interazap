import { type OnDestroy, Injectable, DestroyRef, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type Observable, Subject } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';
import { io, type Socket } from 'socket.io-client';
import {
  type WebChatMessage,
  type WebChatMessageRequest,
  type WebChatMessageResponse,
  type WebChatSessionResponse,
  type WebChatConnectionState,
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
  private readonly destroyRef = inject(DestroyRef);

  // ─── Socket.io state ───────────────────────────────────────────────────────
  private socket: Socket | null = null;
  private sessionToken: string | null = null;
  private sessionId: string | null = null;

  // ─── Reactive state (signals) ──────────────────────────────────────────────
  private readonly _connectionState = signal<WebChatConnectionState>('disconnected');
  private readonly _messages = signal<WebChatMessage[]>([]);
  private readonly _isAiTyping = signal(false);
  private readonly _error = signal<string | null>(null);

  // Public readonly signals
  readonly connectionState = this._connectionState.asReadonly();
  readonly messages = this._messages.asReadonly();
  readonly isAiTyping = this._isAiTyping.asReadonly();
  readonly error = this._error.asReadonly();

  readonly isConnected = computed(() => this._connectionState() === 'connected');
  readonly hasMessages = computed(() => this._messages().length > 0);

  // ─── Event streams ────────────────────────────────────────────────────────
  private readonly _aiResponse$ = new Subject<WebChatMessage>();
  private readonly _messageSent$ = new Subject<{ messageId: string; tempId?: string }>();
  private readonly _joined$ = new Subject<string>();

  readonly aiResponse$ = this._aiResponse$.asObservable();
  readonly messageSent$ = this._messageSent$.asObservable();
  readonly joined$ = this._joined$.asObservable();

  // ─── API Base URL ─────────────────────────────────────────────────────────
  private readonly apiBase = environment.gateway?.url ?? window.location.origin;

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
    return this.http
      .post<WebChatSessionResponse>(`${this.apiBase}/api/webchat/sessions`, body)
      .pipe(
        tap((response) => {
          this.sessionToken = response.token;
          this.sessionId = response.sessionId;
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
    const body: WebChatMessageRequest = { sessionId, content };
    return this.http
      .post<WebChatMessageResponse>(`${this.apiBase}/api/webchat/messages`, body)
      .pipe(
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
   * Connects to the WebSocket gateway and joins the session room.
   * Uses the token from createSession().
   */
  connectWebSocket(token: string): void {
    if (this.socket?.connected) {
      return;
    }

    this._connectionState.set('connecting');
    this.sessionToken = token;

    const gatewayUrl = this.apiBase;

    this.socket = io(gatewayUrl, {
      path: environment.gateway?.path ?? WEBCHAT_PATH,
      auth: { token },
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
      const parsed = JSON.parse(raw) as { token: string; sessionId: string; expiresAt: number };
      if (Date.now() > parsed.expiresAt) {
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
      if (reason === 'io server disconnect') {
        this.socket?.connect();
      }
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
