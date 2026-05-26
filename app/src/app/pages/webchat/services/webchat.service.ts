import { type OnDestroy, Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable, Subject, throwError } from 'rxjs';
import { catchError, finalize, map, tap } from 'rxjs/operators';
import { io, type Socket } from 'socket.io-client';
import {
  type WebChatMessage,
  type WebChatMessageRequest,
  type WebChatMessageResponse,
  type WebChatMediaUploadResponse,
  type WebChatMessageType,
  type WebChatSessionResponse,
  type WebChatCloseResponse,
  type WebChatConnectionState,
  type WebChatTicketStatus,
  type WebChatTenantInfo,
  type WebChatSessionDetail,
} from '../webchat.model';
import { environment } from '@env/environment';

const WEBCHAT_PATH = '/ws';

/**
 * Serviço de webchat responsável por gerenciar criação de sessão, comunicação
 * via WebSocket e estado das mensagens para o widget de chat público.
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
  private readonly _ticketClosedByAgent$ = new Subject<void>();

  readonly aiResponse$ = this._aiResponse$.asObservable();
  readonly messageSent$ = this._messageSent$.asObservable();
  readonly joined$ = this._joined$.asObservable();
  readonly ticketClosedByAgent$ = this._ticketClosedByAgent$.asObservable();

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
   * Cria uma nova sessão de webchat para um tenant.
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
   * Envia uma mensagem de texto via REST API (WebSocket é usado para respostas em streaming).
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
   * Faz upload de um arquivo de mídia para o armazenamento do webchat.
   * POST /api/webchat/media
   */
  uploadMedia(file: File): Observable<WebChatMediaUploadResponse> {
    const token = this.sessionToken?.trim();
    if (!token) {
      return throwError(() => new Error('Sessão inválida'));
    }
    const form = new FormData();
    form.append('token', token);
    form.append('file', file);
    return this.http.post<unknown>(`${this.apiBase}/api/webchat/media`, form).pipe(
      map((r) => this.unwrapData<WebChatMediaUploadResponse>(r)),
      catchError((err) => {
        this._error.set(err?.error?.message ?? 'Falha ao enviar arquivo');
        throw err;
      }),
    );
  }

  /**
   * Envia uma mensagem de mídia após upload bem-sucedido.
   * POST /api/webchat/messages  { token, file_url, mime_type, type }
   */
  sendFileMessage(
    sessionId: string,
    fileUrl: string,
    mimeType: string,
    messageType: WebChatMessageType,
    fileName?: string,
    tempId?: string,
  ): Observable<WebChatMessageResponse> {
    const token = this.sessionToken?.trim();
    if (!token) {
      return throwError(() => new Error('Sessão inválida'));
    }

    const body: WebChatMessageRequest = {
      token,
      file_url: fileUrl,
      mime_type: mimeType,
      type: messageType,
      ...(fileName ? { file_name: fileName } : {}),
    };

    return this.http.post<unknown>(`${this.apiBase}/api/webchat/messages`, body).pipe(
      map((response) => this.unwrapData<WebChatMessageResponse>(response)),
      tap((response) => {
        const optimisticMessage: WebChatMessage = {
          id: tempId ?? response.messageId,
          content: fileName ?? '',
          direction: 'outgoing',
          source: 'visitor',
          type: messageType === 'document' ? 'file' : messageType,
          status: 'pending',
          createdAt: new Date().toISOString(),
          sessionId,
          fileUrl,
          mimeType,
          fileName,
        };
        this.addMessage(optimisticMessage);
        this._messageSent$.next({ messageId: response.messageId, tempId });
      }),
      catchError((err) => {
        this._error.set(err?.error?.message ?? 'Falha ao enviar arquivo');
        throw err;
      }),
    );
  }

  /**
   * Encerra o ticket de webchat ativo via endpoint público.
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
   * Conecta ao gateway WebSocket e entra na sala da sessão.
   * Utiliza o token de createSession(), ou recebe sessionId explicitamente ao restaurar sessão.
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
    // When restoring from sessionStorage, sessionId is not set via createSession() tap.
    if (sessionId && typeof sessionId === 'string' && sessionId.trim() !== '') {
      this.sessionId = sessionId.trim();
    }

    const gatewayUrl = `${this.apiBase}/webchat`;

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
   * Desconecta do WebSocket e limpa todos os dados da sessão em memória.
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
   * Carrega o histórico de mensagens da sessão a partir do banco de dados.
   * Substitui as mensagens em memória, preservando qualquer mensagem local
   * que ainda não tenha ID persistido.
   *
   * GET /api/webchat/sessions/{sessionId}/messages?token={jwt}
   */
  fetchSessionMessages(sessionId: string, token: string): Observable<WebChatMessage[]> {
    const params = new URLSearchParams({ token });
    return this.http
      .get<unknown>(`${this.apiBase}/api/webchat/sessions/${sessionId}/messages?${params}`)
      .pipe(
        map((response) => this.unwrapData<unknown[]>(response)),
        map((items) =>
          items.map((raw) => {
            const msg = raw as Record<string, unknown>;
            return {
              ...(msg as unknown as WebChatMessage),
              // Backend serializa em snake_case — normalizar para camelCase
              fileUrl:
                (msg['file_url'] as string | undefined) ??
                (msg['fileUrl'] as string | undefined),
              mimeType:
                (msg['mime_type'] as string | undefined) ??
                (msg['mimeType'] as string | undefined),
              fileName:
                (msg['file_name'] as string | undefined) ??
                (msg['fileName'] as string | undefined),
            } as WebChatMessage;
          }),
        ),
        tap((messages) => {
          if (messages.length > 0) {
            const sorted = messages.sort(compareWebChatMessagesAsc);
            this._messages.set(sorted);
            this.persistMessages(sorted);
          }
        }),
        catchError((err) => {
          // Não sobrescreve erro global — falha silenciosa para não bloquear UI
          return throwError(() => err);
        }),
      );
  }

  /**
   * Busca informações públicas do tenant para validação antes de iniciar sessão.
   * GET /api/webchat/tenant/:tenantId
   */
  getTenantInfo(tenantId: string): Observable<WebChatTenantInfo> {
    return this.http
      .get<unknown>(`${this.apiBase}/api/webchat/tenant/${tenantId}`)
      .pipe(
        map((response) => this.unwrapData<WebChatTenantInfo>(response)),
        catchError((err) => {
          return throwError(() => err);
        }),
      );
  }

  /**
   * Busca detalhes de uma sessão existente (status, ticket, últimas mensagens).
   * GET /api/webchat/sessions/:id?tenant_id=:tenantId
   */
  getSession(sessionId: string, tenantId: string): Observable<WebChatSessionDetail> {
    const params = new URLSearchParams({ tenant_id: tenantId });
    return this.http
      .get<unknown>(`${this.apiBase}/api/webchat/sessions/${sessionId}?${params}`)
      .pipe(
        map((response) => this.unwrapData<WebChatSessionDetail>(response)),
        catchError((err) => {
          return throwError(() => err);
        }),
      );
  }

  /**
   * Adiciona uma mensagem à lista local e persiste no sessionStorage.
   */
  addMessage(message: WebChatMessage): void {
    this._messages.update((msgs) => {
      // Avoid duplicates
      if (msgs.some((m) => m.id === message.id)) {
        return msgs;
      }
      const updated = [...msgs, message].sort(compareWebChatMessagesAsc);
      this.persistMessages(updated);
      return updated;
    });
  }

  /**
   * Persiste a lista atual de mensagens na entrada de sessão do sessionStorage.
   * Ignorado silenciosamente se nenhuma sessão estiver armazenada.
   */
  private persistMessages(messages: WebChatMessage[]): void {
    try {
      const raw = sessionStorage.getItem('webchat_session');
      if (!raw) return;
      const session = JSON.parse(raw) as object;
      sessionStorage.setItem('webchat_session', JSON.stringify({ ...session, messages }));
    } catch {
      // Ignore serialization errors (e.g. quota exceeded)
    }
  }

  /**
   * Atualiza o status de uma mensagem pelo seu ID.
   */
  updateMessageStatus(messageId: string, status: WebChatMessage['status']): void {
    this._messages.update((msgs) => msgs.map((m) => (m.id === messageId ? { ...m, status } : m)));
  }

  /**
   * Recupera a sessão persistida do sessionStorage.
   * Quando `expectedSessionId` é fornecido (ex.: parâmetro de URL ?s=),
   * retorna a sessão somente se o sessionId coincidir.
   */
  restoreSession(expectedSessionId?: string): {
    token: string;
    sessionId: string;
    contactName?: string;
    contactPhone?: string;
    protocol?: string;
  } | null {
    try {
      const raw = sessionStorage.getItem('webchat_session');
      if (!raw) return null;
      const parsed = JSON.parse(raw) as {
        token?: string;
        sessionId?: string;
        expiresAt?: number;
        messages?: unknown[];
        contactName?: string;
        contactPhone?: string;
        protocol?: string;
      };
      if (typeof parsed.expiresAt !== 'number' || Date.now() > parsed.expiresAt) {
        sessionStorage.removeItem('webchat_session');
        return null;
      }
      if (
        typeof parsed.token !== 'string' ||
        parsed.token.trim() === '' ||
        typeof parsed.sessionId !== 'string' ||
        parsed.sessionId.trim() === ''
      ) {
        sessionStorage.removeItem('webchat_session');
        return null;
      }
      // parsed.messages is validated below before use
      // If the caller provided an expected sessionId (from URL), reject mismatches.
      if (
        expectedSessionId !== undefined &&
        expectedSessionId.trim() !== '' &&
        parsed.sessionId !== expectedSessionId.trim()
      ) {
        return null;
      }
      const messages = Array.isArray(parsed.messages) ? (parsed.messages as WebChatMessage[]) : [];
      // Restore persisted messages immediately so components see them on mount.
      if (messages.length > 0) {
        this._messages.set(messages);
      }
      return {
        token: parsed.token,
        sessionId: parsed.sessionId,
        contactName: parsed.contactName,
        contactPhone: parsed.contactPhone,
        protocol: parsed.protocol,
      };
    } catch {
      return null;
    }
  }

  /**
   * Persiste a sessão no sessionStorage com expiração de 4 horas.
   * Mensagens anteriores são descartadas ao iniciar uma nova sessão.
   */
  saveSession(
    token: string,
    sessionId: string,
    contactInfo?: { contactName?: string; contactPhone?: string; protocol?: string },
  ): void {
    const expiresAt = Date.now() + 4 * 60 * 60 * 1000; // 4 hours
    sessionStorage.setItem(
      'webchat_session',
      JSON.stringify({ token, sessionId, expiresAt, messages: [], ...contactInfo }),
    );
  }

  /**
   * Remove a sessão persistida do sessionStorage.
   */
  clearSession(): void {
    sessionStorage.removeItem('webchat_session');
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
    // Gateway emits { tenant_id, session_id, message: { ...WebChatMessage } }
    // so we unwrap the nested `message` field if present.
    this.socket.on('webchat:ai_response', (raw: unknown) => {
      this._isAiTyping.set(false);
      const envelope = raw as Record<string, unknown>;
      const msgData =
        envelope['message'] !== null && typeof envelope['message'] === 'object'
          ? (envelope['message'] as Record<string, unknown>)
          : (raw as Record<string, unknown>);
      const message: WebChatMessage = {
        ...(msgData as unknown as WebChatMessage),
        direction: 'incoming',
        source: (msgData['source'] as WebChatMessage['source']) ?? 'ai',
        status: 'delivered',
        // Backend serializa em snake_case — mapear explicitamente para camelCase
        fileUrl:
          (msgData['file_url'] as string | undefined) ?? (msgData['fileUrl'] as string | undefined),
        mimeType:
          (msgData['mime_type'] as string | undefined) ??
          (msgData['mimeType'] as string | undefined),
        fileName:
          (msgData['file_name'] as string | undefined) ?? (msgData['fileName'] as string | undefined),
        createdAt:
          (msgData['created_at'] as string | undefined) ??
          (msgData['createdAt'] as string | undefined) ??
          new Date().toISOString(),
      };
      this.addMessage(message);
      this._aiResponse$.next(message);
    });

    // Mensagem do atendente humano para o visitante webchat
    // Gateway emits { tenant_id, session_id, message: { ...WebChatMessage } }
    this.socket.on('webchat:agent_message', (raw: unknown) => {
      const envelope = raw as Record<string, unknown>;
      const msgData =
        envelope['message'] !== null && typeof envelope['message'] === 'object'
          ? (envelope['message'] as Record<string, unknown>)
          : (raw as Record<string, unknown>);
      const message: WebChatMessage = {
        ...(msgData as unknown as WebChatMessage),
        direction: 'incoming',
        source: 'agent',
        status: 'delivered',
        // Backend serializa em snake_case — mapear explicitamente para camelCase
        fileUrl:
          (msgData['file_url'] as string | undefined) ?? (msgData['fileUrl'] as string | undefined),
        mimeType:
          (msgData['mime_type'] as string | undefined) ??
          (msgData['mimeType'] as string | undefined),
        fileName:
          (msgData['file_name'] as string | undefined) ?? (msgData['fileName'] as string | undefined),
        createdAt:
          (msgData['created_at'] as string | undefined) ??
          (msgData['createdAt'] as string | undefined) ??
          new Date().toISOString(),
      };
      this.addMessage(message);
    });

    // Typing indicator
    this.socket.on('webchat:typing', (data: { isTyping: boolean; source?: 'ai' | 'agent' }) => {
      this._isAiTyping.set(data.isTyping);
    });

    // Error event
    this.socket.on('webchat:error', (data: { code: string; message: string }) => {
      this._error.set(data.message);
    });

    // Ticket encerrado pelo atendente
    this.socket.on('webchat:ticket_closed', () => {
      this._ticketStatus.set('closed');
      this._ticketClosedByAgent$.next();
    });
  }
}

/**
 * Compara duas WebChatMessages para ordenação estável ASC (cronológica).
 * Regra: `createdAt` ASC com desempate determinístico por `id` ASC.
 */
function compareWebChatMessagesAsc(a: WebChatMessage, b: WebChatMessage): number {
  const tsA = Date.parse(a.createdAt ?? '');
  const tsB = Date.parse(b.createdAt ?? '');
  const tsDiff = (Number.isNaN(tsA) ? 0 : tsA) - (Number.isNaN(tsB) ? 0 : tsB);
  if (tsDiff !== 0) {
    return tsDiff;
  }
  // Desempate por id: numérico quando possível (evita "10" < "9"), lexicográfico para UUIDs
  const numA = Number(a.id);
  const numB = Number(b.id);
  if (Number.isFinite(numA) && Number.isFinite(numB)) {
    return numA - numB;
  }
  return a.id < b.id ? -1 : a.id > b.id ? 1 : 0;
}
