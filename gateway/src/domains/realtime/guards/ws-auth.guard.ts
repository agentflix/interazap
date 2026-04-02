import {
  CanActivate,
  ExecutionContext,
  Injectable,
  Logger,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { WsException } from '@nestjs/websockets';
import jwt, { Algorithm } from 'jsonwebtoken';
import { Socket } from 'socket.io';
import {
  AuthenticatedSocket,
  AuthenticatedHandshake,
  JwtPayload,
} from '../types/socket.types';

/**
 * WsAuthGuard
 *
 * Guard de autenticação para conexões WebSocket.
 * Valida o token JWT presente no handshake ou nos headers da conexão
 * e anexa o payload ao socket para uso downstream.
 */
@Injectable()
export class WsAuthGuard implements CanActivate {
  private readonly logger = new Logger(WsAuthGuard.name);

  constructor(private readonly configService: ConfigService) {}

  /**
   * Verifica a autenticação do cliente WebSocket.
   * Extrai e valida o token JWT, anexando o payload ao socket.
   *
   * @param context - Contexto de execução NestJS (WebSocket)
   * @returns `true` se o token é válido
   * @throws WsException se o token estiver ausente ou inválido
   */
  canActivate(context: ExecutionContext): boolean {
    const client = context.switchToWs().getClient<Socket>();
    const token = this.extractToken(client);

    if (!token) {
      throw new WsException('Missing authentication token');
    }

    try {
      const payload = this.verifyToken(token);
      // Attach user info to socket data
      const authClient = client as AuthenticatedSocket;
      authClient.data.user = payload;
      authClient.data.tenantId = payload.tenant_id;
      return true;
    } catch (error: unknown) {
      this.logger.warn(
        `WebSocket auth failed: ${error instanceof Error ? error.message : String(error)}`,
      );
      throw new WsException('Invalid authentication token');
    }
  }

  /**
   * Extrai o token de autenticação do handshake do socket.
   * Tenta em ordem: objeto `auth`, query params e cabeçalho `Authorization`.
   *
   * @param client - Socket.IO Socket com handshake HTTP
   * @returns Token Bearer ou `null` se não encontrado
   */
  private extractToken(client: Socket): string | null {
    // Try auth object first (socket.io auth)
    const handshake = client.handshake as unknown as AuthenticatedHandshake;
    const authToken = handshake.auth?.token;
    if (typeof authToken === 'string') return authToken;

    // Try query params
    const queryToken = client.handshake.query?.token;
    if (typeof queryToken === 'string') return queryToken;

    // Try Authorization header
    const authHeader = client.handshake.headers?.authorization;
    if (typeof authHeader === 'string' && authHeader.startsWith('Bearer ')) {
      return authHeader.slice(7);
    }

    return null;
  }

  /**
   * Verifica e decodifica o token JWT usando as configurações de `jwt.secret` e `jwt.algorithm`.
   *
   * @param token - Token JWT em formato string
   * @returns Payload decodificado com as claims `sub`, `tenant_id` e opcionalmente `email`
   * @throws Error se o token for inválido ou se claims obrigatórias estiverem ausentes
   */
  private verifyToken(token: string): JwtPayload {
    try {
      const secret = this.configService.get<string>('jwt.secret');
      if (!secret) {
        this.logger.error('JWT secret is not configured');
        throw new Error('JWT secret not configured');
      }

      const algorithm =
        (this.configService.get<string>('jwt.algorithm') as Algorithm) ||
        'HS256';
      const decoded = jwt.verify(token, secret, {
        algorithms: [algorithm],
      });

      if (typeof decoded !== 'object' || decoded === null) {
        throw new Error('Invalid token payload');
      }

      const payload = decoded as Record<string, unknown>;
      const sub = payload['sub'];
      const tenantId = payload['tenant_id'];

      if (
        (typeof sub !== 'string' && typeof sub !== 'number') ||
        (typeof tenantId !== 'string' && typeof tenantId !== 'number')
      ) {
        throw new Error('Missing required claims');
      }

      const email = payload['email'];

      return {
        sub: String(sub),
        tenant_id: String(tenantId),
        ...(typeof email === 'string' ? { email } : {}),
      };
    } catch (error: unknown) {
      this.logger.error(
        `Token verification error: ${error instanceof Error ? error.message : String(error)}`,
      );
      throw new Error('Invalid token');
    }
  }
}
