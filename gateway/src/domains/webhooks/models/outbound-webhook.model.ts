/**
 * Modelos de contratos para despacho de webhooks de saída.
 */

/** Métodos HTTP suportados para entrega de webhook. */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/** Requisição de envio de webhook para sistema externo. */
export interface OutboundWebhookRequest {
  id: string;
  tenantId: string;
  url: string;
  method: HttpMethod;
  headers?: Record<string, string>;
  body?: Record<string, unknown>;
  retryCount?: number;
  maxRetries?: number;
  timeoutMs?: number;
  signatureSecret?: string;
}

/** Resultado final da execução de um webhook de saída. */
export interface OutboundWebhookResult {
  success: boolean;
  webhookId: string;
  statusCode?: number;
  responseBody?: string;
  error?: string;
  duration: number;
  retryCount: number;
  completedAt: Date;
}

/** Tentativa individual de entrega de webhook. */
export interface WebhookDeliveryAttempt {
  attemptNumber: number;
  statusCode?: number;
  error?: string;
  duration: number;
  attemptedAt: Date;
}

/** Configuração de inscrição de webhook por evento. */
export interface WebhookConfig {
  url: string;
  events: string[];
  secret?: string;
  isActive: boolean;
  headers?: Record<string, string>;
}
