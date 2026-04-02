/**
 * Modelos de autenticação de sessão WebSocket.
 */

import type { JwtPayload } from '../types/socket.types';

/** Entrada de cache para token validado via Sanctum/JWT. */
export interface TokenCacheEntry {
  payload: JwtPayload;
  expiresAt: number;
}
