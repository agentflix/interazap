import { Socket } from 'socket.io';

/**
 * JWT Payload extracted from authentication token.
 */
export interface JwtPayload {
  sub: string;
  tenant_id: string;
  email?: string;
  exp?: number;
  session_id?: string;
}

/**
 * Socket data attached during authentication.
 */
export interface SocketData {
  user?: JwtPayload;
  tenantId?: string;
}

/**
 * Authenticated Socket with typed data property.
 */
export interface AuthenticatedSocket extends Socket {
  data: SocketData;
}

/**
 * Socket.IO handshake with typed auth property.
 */
export interface AuthenticatedHandshake {
  auth?: {
    token?: string;
  };
  query?: Record<string, string | string[]>;
  headers?: Record<string, string | string[]>;
}
