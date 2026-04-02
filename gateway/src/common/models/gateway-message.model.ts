/**
 * Modelos canônicos de mensagem entre Laravel e Gateway via Redis Streams.
 */

/** Domínios suportados no envelope de mensagem do Gateway. */
export type GatewayDomain = 'ai' | 'whatsapp' | 'payment' | 'webhook';

/** Metadados opcionais anexados à mensagem. */
export interface GatewayMessageMetadata {
  tenantId?: string;
  instanceId?: string;
  ticketId?: string;
  retryCount?: number;
  maxRetries?: number;
  [key: string]: unknown;
}

/** Envelope padrão de mensagem publicada/consumida pelo Gateway. */
export interface GatewayMessage<T = unknown> {
  correlationId: string;
  timestamp: string;
  domain: GatewayDomain;
  action: string;
  provider: string;
  payload: T;
  metadata?: GatewayMessageMetadata;
}
