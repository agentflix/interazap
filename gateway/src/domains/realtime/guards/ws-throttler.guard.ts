import { ExecutionContext, Injectable } from '@nestjs/common';
import { ThrottlerGuard } from '@nestjs/throttler';
import { SocketWithConn, HandshakeData } from '../models/ws-throttler.model';

/**
 * WsThrottlerGuard
 *
 * Guard de rate-limiting para conexões WebSocket e HTTP.
 * Herda de `ThrottlerGuard` e substitui a extração de IP/tracker
 * para funcionar corretamente em contextos WebSocket (socket.io).
 */
@Injectable()
export class WsThrottlerGuard extends ThrottlerGuard {
  /**
   * Constrói o objeto `req`/`res` virtual para o mecanismo de throttling.
   * Em contexto WebSocket, monta um objeto fake com IP do cliente e dados do usuário.
   * Em contexto HTTP, delega ao comportamento padrão do ThrottlerGuard.
   *
   * @param context - Contexto de execução NestJS (WS ou HTTP)
   * @returns Par `{ req, res }` usado pelo throttler para identificar o cliente
   */
  protected getRequestResponse(context: ExecutionContext) {
    if (context.getType() === 'ws') {
      const wsContext = context.switchToWs();
      const client = wsContext.getClient<SocketWithConn>();
      const handshake = (client.handshake ?? {}) as HandshakeData;
      const headers = handshake.headers ?? {};
      const forwarded = headers['x-forwarded-for'];
      const forwardedIp = Array.isArray(forwarded) ? forwarded[0] : forwarded;
      const remoteAddress = client.conn?.remoteAddress;
      const ip =
        forwardedIp ??
        handshake.address ??
        remoteAddress ??
        client.id ??
        'unknown';

      return {
        req: {
          headers: {
            ...headers,
            'user-agent': headers['user-agent'] ?? 'ws-client',
          },
          ip,
          clientId: client.id,
          user: client.data?.user,
        },
        res: {
          header: (): void => undefined,
        },
      };
    }

    return super.getRequestResponse(context);
  }

  /**
   * Determina a chave de rastreamento do throttler para uma requisição.
   * Usa `sub:tenant_id` para usuários autenticados ou o ID do socket como fallback.
   *
   * @param req - Objeto de requisição virtual com dados do cliente WebSocket
   * @returns Chave única para rastreamento de taxa de requisições
   */
  protected getTracker(req: Record<string, unknown>): Promise<string> {
    const user = req?.user as { sub?: string; tenant_id?: string } | undefined;
    if (user?.sub) {
      const tenant = user.tenant_id ?? 'tenant';
      return Promise.resolve(`${user.sub}:${tenant}`);
    }

    const clientId = req?.clientId;
    if (typeof clientId === 'string') {
      return Promise.resolve(clientId);
    }

    return super.getTracker(req);
  }
}
