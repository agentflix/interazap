/**
 * Modelos de sessão WebSocket.
 */

/** Contrato mínimo de logger de arquivo usado na sessão WebSocket. */
export type FileLogger = {
  debug(message: string, payload?: Record<string, unknown>): void;
  info(message: string, payload?: Record<string, unknown>): void;
  error(message: string, payload?: Record<string, unknown>): void;
};
