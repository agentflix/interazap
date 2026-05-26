import { Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  OnGatewayInit,
  OnGatewayConnection,
  OnGatewayDisconnect,
  WebSocketGateway,
  WebSocketServer,
  SubscribeMessage,
  WsException,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import jwt, { Algorithm } from 'jsonwebtoken';

interface JwtPayload {
  sub: string;
  tenant_id: string;
  email?: string;
}

interface ClientInfo {
  userId: string;
  tenantId: string;
}

interface RateLimitEntry {
  count: number;
  resetAt: number;
}

interface TelegramMessagePayload {
  chatId: string;
  text: string;
  replyToMessageId?: string;
}

interface TelegramTypingPayload {
  chatId: string;
}

/**
 * TelegramWebSocketGateway
 *
 * Gateway WebSocket dedicado ao bounded context Telegram.
 * Responsável por:
 * - Autenticação JWT na conexão (handshake)
 * - Isolamento por tenant (eventos só são emitidos para clientes do mesmo tenant)
 * - Rate limiting (100 eventos/min por conexão)
 * - Heartbeat a cada 30s
 * - CORS restrito a allowlist de origens configuráveis
 */
@WebSocketGateway({
  namespace: '/telegram',
  cors: {
    origin: (
      origin: string | undefined,
      callback: (err: Error | null, allow?: boolean) => void,
    ) => {
      const allowedStr = process.env.TELEGRAM_WS_ALLOWED_ORIGINS ?? '';
      const allowed = allowedStr
        ? allowedStr
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean)
        : ['http://localhost:4200', 'http://127.0.0.1:4200'];

      if (!origin || allowed.includes(origin)) {
        callback(null, true);
      } else {
        callback(
          new Error(`Origin ${origin} not allowed by CORS policy`),
          false,
        );
      }
    },
    credentials: true,
  },
  transports: ['websocket', 'polling'],
})
export class TelegramWebSocketGateway
  implements OnGatewayInit, OnGatewayConnection, OnGatewayDisconnect
{
  @WebSocketServer()
  server!: Server;

  private readonly logger = new Logger(TelegramWebSocketGateway.name);
  private readonly connectedClients = new Map<string, ClientInfo>();
  private readonly rateLimiter = new Map<string, RateLimitEntry>();
  private heartbeatInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private readonly configService: ConfigService) {}

  /** Inicializa o gateway e configura o heartbeat de 30 segundos. */
  afterInit(server: Server): void {
    this.logger.log('Telegram WebSocket Gateway initialized');
    this.heartbeatInterval = setInterval(() => {
      server.emit('ping');
    }, 30_000);
  }

  /**
   * Autentica o cliente via JWT no handshake e registra as informações do tenant.
   * Conexões sem token ou com token inválido são desconectadas imediatamente.
   * @param client Socket do cliente conectado
   */
  async handleConnection(client: Socket): Promise<void> {
    try {
      const token = this.extractToken(client);
      if (!token) {
        this.logger.warn(`Connection rejected (no token): ${client.id}`);
        client.emit('error', { message: 'Missing authentication token' });
        client.disconnect(true);
        return;
      }

      const payload = this.verifyToken(token);

      this.connectedClients.set(client.id, {
        userId: payload.sub,
        tenantId: payload.tenant_id,
      });

      this.logger.log(
        `Client connected: ${client.id} (tenant: ${payload.tenant_id})`,
      );
    } catch (error: unknown) {
      const message =
        error instanceof Error ? error.message : 'Authentication failed';
      this.logger.warn(`Connection rejected: ${client.id} — ${message}`);
      client.emit('error', { message: 'Invalid authentication token' });
      client.disconnect(true);
    }
  }

  /**
   * Remove o cliente dos mapas de conexão e rate limiter ao desconectar.
   * @param client Socket do cliente desconectado
   */
  handleDisconnect(client: Socket): void {
    this.connectedClients.delete(client.id);
    this.rateLimiter.delete(client.id);
    this.logger.log(`Client disconnected: ${client.id}`);
  }

  /** Limpa o intervalo de heartbeat e os mapas internos ao destruir o módulo, evitando vazamentos de memória. */
  onModuleDestroy(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
    this.connectedClients.clear();
    this.rateLimiter.clear();
  }

  // ──────────────────────────────────────────────
  // Eventos Server → Client (isolados por tenant)
  // ──────────────────────────────────────────────

  /** Emite o evento de mensagem recebida para todos os clientes do tenant. */
  emitMessageReceived(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.message.received', data);
  }

  /** Emite o evento de mensagem enviada para todos os clientes do tenant. */
  emitMessageSent(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.message.sent', data);
  }

  /** Emite o evento de mensagem editada para todos os clientes do tenant. */
  emitMessageEdited(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.message.edited', data);
  }

  /** Emite o evento de ticket criado para todos os clientes do tenant. */
  emitTicketCreated(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.ticket.created', data);
  }

  /** Emite o evento de início de digitação para todos os clientes do tenant. */
  emitTypingStart(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.typing.start', data);
  }

  /** Emite o evento de fim de digitação para todos os clientes do tenant. */
  emitTypingStop(tenantId: string, data: Record<string, unknown>): void {
    this.emitToTenant(tenantId, 'telegram.typing.stop', data);
  }

  // ──────────────────────────────────────────────
  // Eventos Client → Server
  // ──────────────────────────────────────────────

  /**
   * Processa o envio de uma mensagem pelo cliente via WebSocket.
   * Aplica rate limiting e repropaga o evento para o tenant isolado.
   * @param client Socket do cliente remetente
   * @param payload Dados da mensagem (chatId, text, replyToMessageId opcional)
   * @returns ACK com status 'queued' ou void se bloqueado pelo rate limiter
   */
  @SubscribeMessage('telegram.send.message')
  handleSendMessage(
    client: Socket,
    payload: TelegramMessagePayload,
  ): { event: string; data: { status: string } } | void {
    if (!this.enforceRateLimit(client)) return;

    const clientInfo = this.connectedClients.get(client.id);
    if (!clientInfo) {
      client.emit('error', { message: 'Not authenticated' });
      return;
    }

    this.logger.debug(
      `Send message request from ${client.id} to chat ${payload.chatId}`,
    );

    this.emitToTenant(clientInfo.tenantId, 'telegram.message.outgoing', {
      userId: clientInfo.userId,
      tenantId: clientInfo.tenantId,
      chatId: payload.chatId,
      text: payload.text,
      replyToMessageId: payload.replyToMessageId,
    });

    return {
      event: 'telegram.send.message.ack',
      data: { status: 'queued' },
    };
  }

  /**
   * Processa o início de digitação enviado pelo cliente via WebSocket.
   * @param client Socket do cliente
   * @param payload Dados do chat onde está digitando
   */
  @SubscribeMessage('telegram.typing.start')
  handleTypingStart(client: Socket, payload: TelegramTypingPayload): void {
    if (!this.enforceRateLimit(client)) return;

    const clientInfo = this.connectedClients.get(client.id);
    if (!clientInfo) {
      client.emit('error', { message: 'Not authenticated' });
      return;
    }

    this.emitToTenant(clientInfo.tenantId, 'telegram.typing.outgoing', {
      userId: clientInfo.userId,
      tenantId: clientInfo.tenantId,
      chatId: payload.chatId,
    });
  }

  // ──────────────────────────────────────────────
  // Private helpers
  // ──────────────────────────────────────────────

  /**
   * Emite um evento para todos os clientes conectados de um tenant específico.
   * @param tenantId Identificador do tenant
   * @param event Nome do evento Socket.IO
   * @param data Payload do evento
   */
  private emitToTenant(
    tenantId: string,
    event: string,
    data: Record<string, unknown>,
  ): void {
    for (const [clientId, info] of this.connectedClients) {
      if (info.tenantId === tenantId) {
        this.server.to(clientId).emit(event, data);
      }
    }
  }

  /**
   * Extrai o token JWT do handshake do socket (auth, query ou header Authorization).
   * @param client Socket do cliente
   * @returns Token JWT como string ou null se não encontrado
   */
  private extractToken(client: Socket): string | null {
    const auth = client.handshake.auth as Record<string, unknown> | undefined;
    const authToken = auth?.['token'];
    if (typeof authToken === 'string') return authToken;

    const queryToken = client.handshake.query?.['token'];
    if (typeof queryToken === 'string') return queryToken;

    const authHeader = client.handshake.headers?.authorization;
    if (typeof authHeader === 'string' && authHeader.startsWith('Bearer ')) {
      return authHeader.slice(7);
    }

    return null;
  }

  /**
   * Verifica e decodifica o token JWT usando o segredo configurado.
   * @param token Token JWT a ser verificado
   * @returns Payload decodificado com sub, tenant_id e email opcional
   * @throws WsException se o token for inválido ou os claims obrigatórios estiverem ausentes
   */
  private verifyToken(token: string): JwtPayload {
    const secret = this.configService.get<string>('jwt.secret');
    if (!secret) {
      this.logger.error('JWT secret is not configured');
      throw new WsException('Server misconfiguration');
    }

    const algorithm =
      (this.configService.get<string>('jwt.algorithm') as Algorithm) || 'HS256';

    const decoded = jwt.verify(token, secret, {
      algorithms: [algorithm],
    });

    if (typeof decoded !== 'object' || decoded === null) {
      throw new WsException('Invalid token payload');
    }

    const payload = decoded as Record<string, unknown>;
    const sub = payload['sub'];
    const tenantId = payload['tenant_id'];

    if (
      (typeof sub !== 'string' && typeof sub !== 'number') ||
      (typeof tenantId !== 'string' && typeof tenantId !== 'number')
    ) {
      throw new WsException('Missing required JWT claims (sub, tenant_id)');
    }

    return {
      sub: String(sub),
      tenant_id: String(tenantId),
      ...(typeof payload['email'] === 'string'
        ? { email: payload['email'] }
        : {}),
    };
  }

  /**
   * Verifica o rate limit do cliente e emite erro se excedido.
   * @param client Socket do cliente
   * @returns true se a mensagem deve ser processada, false se bloqueada
   */
  private enforceRateLimit(client: Socket): boolean {
    if (!this.checkRateLimit(client.id)) {
      client.emit('error', { message: 'Rate limit exceeded' });
      return false;
    }
    return true;
  }

  /**
   * Implementa a janela deslizante de rate limit (100 eventos/min por conexão).
   * @param clientId Identificador do socket do cliente
   * @returns true se o evento está dentro do limite, false se excedido
   */
  private checkRateLimit(clientId: string): boolean {
    const now = Date.now();
    const limit = this.rateLimiter.get(clientId);

    if (!limit || now > limit.resetAt) {
      this.rateLimiter.set(clientId, { count: 1, resetAt: now + 60_000 });
      return true;
    }

    limit.count++;
    return limit.count <= 100;
  }
}
