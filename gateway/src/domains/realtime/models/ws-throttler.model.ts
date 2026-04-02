/**
 * Modelos auxiliares do guard de throttling WebSocket.
 */

/** Socket com metadados necessários para identificar cliente e origem. */
export interface SocketWithConn {
  id: string;
  conn?: { remoteAddress?: string };
  data: { user?: { sub?: string; tenant_id?: string } };
  handshake?: {
    headers?: Record<string, string | string[] | undefined>;
    address?: string;
  };
}

/** Recorte tipado do handshake usado na extração de IP/origem. */
export interface HandshakeData {
  headers?: Record<string, string | string[] | undefined>;
  address?: string;
}
