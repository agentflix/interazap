/**
 * Modelos do fan-out de eventos WebSocket.
 */

/** Envelope canônico recebido no canal ws.events para distribuição em rooms. */
export interface GatewayEvent {
  event: string;
  data: unknown;
  tenant_id?: string;
  rooms?: string[];
  version?: string;
}
