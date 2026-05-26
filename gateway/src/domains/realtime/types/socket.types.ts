import { Socket } from 'socket.io';

/**
 * Payload JWT extraído do token de autenticação WebSocket.
 */
export interface JwtPayload {
  sub: string;
  tenant_id: string;
  email?: string;
  exp?: number;
  session_id?: string;
}

/**
 * Dados do socket anexados durante a autenticação bem-sucedida.
 */
export interface SocketData {
  user?: JwtPayload;
  tenantId?: string;
}

/**
 * Socket autenticado com propriedade `data` tipada.
 */
export interface AuthenticatedSocket extends Socket {
  data: SocketData;
}

/**
 * Handshake do Socket.IO com a propriedade `auth` tipada.
 */
export interface AuthenticatedHandshake {
  auth?: {
    token?: string;
  };
  query?: Record<string, string | string[]>;
  headers?: Record<string, string | string[]>;
}
